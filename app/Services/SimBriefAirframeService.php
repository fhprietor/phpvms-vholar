<?php

namespace App\Services;

use App\Contracts\Service;
use App\Models\Aircraft;
use App\Models\User;

/**
 * Airframe de SimBrief del piloto.
 *
 * QUE RESUELVE
 * ------------
 * SimBrief selecciona el airframe por el parametro `type`: o el codigo ICAO
 * (`A320`) o el "Internal ID" de un airframe (`349674_1661382482154`, o sea
 * `<pilot_id_de_simbrief>_<airframe_id>`). Este servicio devuelve el Internal ID
 * que el piloto haya guardado para el tipo de avion del vuelo.
 *
 * POR QUE NO SE CONSULTA SU CUENTA
 * --------------------------------
 * Porque no se puede: SimBrief no publica un endpoint con los airframes
 * guardados de un usuario. `https://www.simbrief.com/api/inputs.airframes.json`
 * es un fichero estatico (mismos bytes con y sin `userid`, comprobado con un
 * usuario real), y las peticiones de un listado por usuario siguen sin respuesta
 * en su foro. El unico camino soportado es que el piloto copie el ID de su
 * editor de airframes y lo pegue en su perfil; de ahi el campo
 * `simbrief_airframe_ids`.
 *
 * SIN CAMPO NO PASA NADA: si el piloto no ha guardado nada (o el valor no
 * encaja), `resolve()` devuelve null y la cadena sigue con lo que ya usaba el
 * formulario de SimBrief.
 *
 * FORMATO (uno por tipo, separados por coma, punto y coma o salto de linea):
 *     B738=349674_1661382482154, A320=80_1709125568637
 */
class SimBriefAirframeService extends Service
{
    /** Slug del campo de perfil, derivado del nombre definido en la migracion. */
    public const FIELD_SLUG = 'simbrief_airframe_ids';

    /** El ID interno es `<digitos>_<digitos>`, pero se admite cualquier token corto. */
    private const ID_PATTERN = '/^[A-Za-z0-9_]{4,64}$/';

    /** Codigo ICAO de tipo de aeronave. */
    private const TYPE_PATTERN = '/^[A-Z0-9]{2,4}$/';

    /**
     * Tipo que hay que mandar a SimBrief como `type`, con su procedencia.
     *
     * PRIORIDAD: airframe del piloto -> `simbrief_type` del avion ->
     * `simbrief_type` de la subflota -> codigo ICAO. Es la cadena del formulario
     * de SimBrief con el airframe del piloto delante.
     *
     * Nunca falla: con cualquier combinacion de nulos devuelve el ICAO (o cadena
     * vacia) y `note = null`.
     *
     * @return array{type: string, airframe: string|null, source: string, note: array{level: string, text: string}|null}
     */
    public function resolveType(?Aircraft $aircraft, ?User $user, ?string $fallbackIcao = null): array
    {
        $subfleet = $aircraft?->subfleet;
        $icao = strtoupper(trim((string) ($subfleet?->type ?: ($aircraft?->icao ?: $fallbackIcao))));

        // 1. Airframe propio del piloto: null si no hay campo, esta vacio o no
        //    encaja. No debe romper nada ni avisar de nada.
        $airframe = $this->resolve($user, $icao);

        if ($airframe !== null) {
            return [
                'type'     => $airframe,
                'airframe' => $airframe,
                'source'   => 'pilot',
                'note'     => [
                    'level' => 'info',
                    'text'  => 'Se usara tu airframe de SimBrief ('.$airframe.') para '.$icao.'.',
                ],
            ];
        }

        // 2. Airframe fijado por la aerolinea, igual que en el formulario.
        if (filled($aircraft?->simbrief_type)) {
            return $this->fromAirline((string) $aircraft->simbrief_type, $icao, 'del avion', 'aircraft');
        }

        if (filled($subfleet?->simbrief_type)) {
            return $this->fromAirline((string) $subfleet->simbrief_type, $icao, 'de la subflota', 'subfleet');
        }

        return [
            'type'     => $icao,
            'airframe' => null,
            'source'   => 'icao',
            'note'     => null,
        ];
    }

    /**
     * Airframe fijado por la aerolinea. Solo avisa cuando aporta algo distinto
     * del ICAO: hoy `simbrief_type` suele repetir el ICAO y no merece un aviso.
     *
     * @return array{type: string, airframe: string|null, source: string, note: array{level: string, text: string}|null}
     */
    private function fromAirline(string $type, string $icao, string $origin, string $source): array
    {
        return [
            'type'     => $type,
            'airframe' => null,
            'source'   => $source,
            'note'     => $type === $icao ? null : [
                'level' => 'info',
                'text'  => 'Se usara el airframe de SimBrief '.$origin.' ('.$type.') para '.$icao.'.',
            ],
        ];
    }

    /**
     * Internal ID del piloto para ese tipo de avion, o null si no ha guardado
     * ninguno (o si el valor no es utilizable).
     */
    public function resolve(?User $user, ?string $icao): ?string
    {
        $type = strtoupper(trim((string) $icao));

        if ($user === null || $type === '') {
            return null;
        }

        return $this->parse($this->rawValue($user))[$type] ?? null;
    }

    /**
     * Convierte el texto del campo en un mapa ICAO => Internal ID.
     *
     * Es tolerante a proposito: espacios sobrantes, comas, punto y coma o saltos
     * de linea, y el tipo en minusculas. Lo que no encaje se ignora en silencio
     * en vez de romper el despacho.
     *
     * @return array<string, string>
     */
    public function parse(?string $raw): array
    {
        $map = [];

        foreach (preg_split('/[,;\r\n]+/', (string) $raw) ?: [] as $entry) {
            $entry = trim($entry);

            if ($entry === '' || !str_contains($entry, '=')) {
                continue;
            }

            [$type, $id] = explode('=', $entry, 2);
            $type = strtoupper(trim($type));
            $id = trim($id);

            if (!preg_match(self::TYPE_PATTERN, $type) || !preg_match(self::ID_PATTERN, $id)) {
                continue;
            }

            $map[$type] = $id;
        }

        return $map;
    }

    /**
     * Valor crudo del campo de perfil del piloto.
     */
    private function rawValue(User $user): ?string
    {
        $value = $user->fields->first(
            fn ($fieldValue) => optional($fieldValue->field)->slug === self::FIELD_SLUG
        );

        return $value?->value;
    }
}
