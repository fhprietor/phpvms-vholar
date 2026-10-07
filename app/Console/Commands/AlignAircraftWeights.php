<?php

namespace App\Console\Commands;

use App\Contracts\Command;
use App\Models\Aircraft;
use App\Models\Subfleet;
use App\Support\Units\Mass;
use Illuminate\Support\Facades\DB;

/**
 * Alinea los pesos de la flota (`dow`, `zfw`, `mtow`, `mlw`) con los airframes
 * del catalogo de SimBrief que ya tenemos importado.
 *
 * POR QUE
 * -------
 * El sugerido de carga necesita saber cuanto peso puede llevarse el avion
 * (`MZFW - OEW - pasaje - equipaje`). Si el OEW esta por debajo del real, el
 * sugerido pide carga que SimBrief luego recorta al generar, y el margen que
 * prometemos no es el que se cobra.
 *
 * La referencia son los propios airframes de SimBrief (`simbrief_airframes`),
 * normalizados a kg (cada airframe declara su unidad en `wgtunits`). De cada
 * tipo se toma la MEDIANA, para no depender de una variante concreta.
 *
 * OJO CON LOS AIRFRAMES PROPIOS DEL PILOTO: SimBrief usa el airframe que el
 * piloto tenga elegido, que puede ser uno suyo y no estar en el catalogo publico
 * (en las pruebas, el plan salio con OEW 43.799 kg frente a los 45.069 kg de
 * todos los B38M del catalogo). Es decir, esto alinea con el catalogo, no con el
 * airframe de cada piloto; si acaso, la referencia es conservadora (OEW mas alto
 * = menos carga disponible).
 *
 * Sin `--apply` no escribe nada: solo muestra el informe.
 */
class AlignAircraftWeights extends Command
{
    protected $signature = 'phpvms:align-aircraft-weights
        {--type=* : Tipos de subflota a tocar (por defecto, todos los que tengan datos)}
        {--apply : Escribe los cambios (sin esta opcion solo muestra el informe)}';

    protected $description = 'Alinea dow/zfw/mtow/mlw de la flota con los airframes de SimBrief';

    /** Libras por kilo: los airframes del catalogo no siempre vienen en kg. */
    private const LB_PER_KG = 2.20462;

    /**
     * Nuestros tipos que en el catalogo van con otro codigo ICAO.
     */
    private const TYPE_ALIASES = [
        'A330' => 'A332',
        'B777' => 'B772',
    ];

    /**
     * Tipos que comparten codigo con otro avion en el catalogo y donde la mediana
     * elegiria mal. SimBrief mete al carguero y al de pasaje bajo el mismo ICAO
     * (B77L es 777-200LR **y** 777F; A306 es A300-600R **y** A300-600RF), asi que
     * aqui se dice en que grupo buscar y con que nombre quedarse.
     */
    private const AIRFRAME_HINTS = [
        'B77F' => ['icao' => 'B77L', 'name' => '766,800 MTOW'], // el 777F
        'A30F' => ['icao' => 'A306', 'name' => 'A300-600RF'],   // el A300-600F
    ];

    /** Columna nuestra => campo del airframe de SimBrief. */
    private const FIELDS = [
        'dow'  => 'oew',
        'zfw'  => 'mzfw',
        'mtow' => 'mtow',
        'mlw'  => 'mlw',
    ];

    public function handle(): int
    {
        $catalogue = $this->catalogue();
        $types = $this->option('type');
        $subfleets = Subfleet::query()
            ->when($types, fn ($q) => $q->whereIn('type', $types))
            ->orderBy('type')
            ->get();

        if ($subfleets->isEmpty()) {
            $this->error('No hay subflotas que coincidan con los tipos indicados.');

            return 1;
        }

        $apply = (bool) $this->option('apply');
        $rows = [];
        $skipped = [];
        $updated = 0;

        foreach ($subfleets as $subfleet) {
            $reference = $this->referenceFor((string) $subfleet->type, $catalogue);

            if ($reference === null) {
                $skipped[] = $subfleet->type;

                continue;
            }

            $current = $this->currentWeights($subfleet);

            $rows[] = [
                $subfleet->type,
                $reference['airframe'],
                $reference['n'],
                ...$this->reportRow($current, $reference),
            ];

            if ($apply) {
                $updated += $this->apply($subfleet, $reference);
            }
        }

        $headers = ['tipo', 'airframe', 'n', 'dow kg (act->ref)', 'zfw kg (act->ref)', 'mtow kg (act->ref)', 'mlw kg (act->ref)'];
        $this->table($headers, $rows);

        if ($skipped !== []) {
            $this->warn('Sin datos en el catalogo, no se tocan: '.implode(', ', $skipped));
        }

        if ($apply) {
            $this->info("Aplicado a {$updated} aviones.");
        } else {
            $this->info('Informe solamente. Vuelve a lanzarlo con --apply para escribirlo.');
        }

        return 0;
    }

    /**
     * Airframes del catalogo de SimBrief, agrupados por ICAO y en kg.
     *
     * @return array<string, array<int, array{name:string, oew:float, mzfw:float, mtow:float, mlw:float}>>
     */
    private function catalogue(): array
    {
        $byType = [];

        foreach (DB::table('simbrief_airframes')->whereNotNull('airframe_id')->get(['icao', 'name', 'options']) as $row) {
            $options = json_decode((string) $row->options, true);
            if (!is_array($options)) {
                continue;
            }

            $divisor = strtoupper((string) ($options['wgtunits'] ?? 'LBS')) === 'KGS'
                ? 1.0
                : self::LB_PER_KG;

            $weights = ['name' => (string) $row->name];
            foreach (self::FIELDS as $field) {
                $value = (float) ($options[$field] ?? 0);
                if ($value <= 0 || ($field === 'oew' && $value / $divisor < 500)) {
                    // Los airframes incompletos (oew de 0 o de 1 kg) no entran.
                    continue 2;
                }

                $weights[$field] = $value / $divisor;
            }

            $byType[$row->icao][] = $weights;
        }

        return $byType;
    }

    /**
     * Referencia de peso para uno de nuestros tipos: la mediana del catalogo o,
     * si el tipo esta en `AIRFRAME_HINTS`, el airframe que coincide con la pista
     * del nombre (los cargueros, que comparten ICAO con el avion de pasaje).
     *
     * @param array<string, array<int, array{name:string, oew:float, mzfw:float, mtow:float, mlw:float}>> $catalogue
     *
     * @return array{airframe:string, n:int, oew:float, mzfw:float, mtow:float, mlw:float}|null
     */
    private function referenceFor(string $type, array $catalogue): ?array
    {
        $icao = self::TYPE_ALIASES[$type] ?? $type;
        $hint = self::AIRFRAME_HINTS[$type] ?? null;

        if ($hint !== null) {
            $icao = $hint['icao'];
        }

        $list = $catalogue[$icao] ?? [];
        if ($list === []) {
            return null;
        }

        if ($hint !== null) {
            foreach ($list as $airframe) {
                if (stripos($airframe['name'], $hint['name']) !== false) {
                    return ['airframe' => $icao.' / '.$airframe['name'], 'n' => 1] + $airframe;
                }
            }

            return null;
        }

        $reference = ['airframe' => $icao, 'n' => count($list)];
        foreach (self::FIELDS as $field) {
            $values = array_column($list, $field);
            sort($values);
            $reference[$field] = $values[intdiv(count($values), 2)];
        }

        return $reference;
    }

    /**
     * Pesos actuales de la subflota, en kg (el modelo los expone ya convertidos).
     *
     * @return array<string, float>
     */
    private function currentWeights(Subfleet $subfleet): array
    {
        $aircraft = Aircraft::where('subfleet_id', $subfleet->id)->first(array_keys(self::FIELDS));

        $current = [];
        foreach (array_keys(self::FIELDS) as $field) {
            $current[$field] = $aircraft !== null ? (float) (string) $aircraft->{$field} : 0.0;
        }

        return $current;
    }

    /**
     * @param array<string, float> $current
     * @param array<string, float> $reference
     *
     * @return string[]
     */
    private function reportRow(array $current, array $reference): array
    {
        $row = [];
        foreach (self::FIELDS as $ours => $theirs) {
            $row[] = sprintf('%.0f -> %.0f', $current[$ours], $reference[$theirs]);
        }

        return $row;
    }

    /**
     * @param array<string, float> $reference
     *
     * @return int aviones actualizados
     */
    private function apply(Subfleet $subfleet, array $reference): int
    {
        $updated = 0;

        foreach (Aircraft::where('subfleet_id', $subfleet->id)->get() as $aircraft) {
            foreach (self::FIELDS as $ours => $theirs) {
                // Se le pasa un objeto Mass para que el cast lo guarde en la
                // unidad interna (libras); un numero plano se guardaria tal cual.
                $aircraft->{$ours} = Mass::make($reference[$theirs], 'kg');
            }

            $aircraft->save();
            $updated++;
        }

        return $updated;
    }
}
