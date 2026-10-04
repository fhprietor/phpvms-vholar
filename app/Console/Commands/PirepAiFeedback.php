<?php

namespace App\Console\Commands;

use App\Contracts\Command;
use App\Exceptions\PirepFeedbackException;
use App\Models\Pirep;
use App\Models\PirepAiFeedback as AiFeedback;
use App\Services\PirepFeedbackService;
use Illuminate\Support\Facades\DB;

/**
 * Genera retroalimentacion automatica (a modo de instructor de vuelo) para los
 * PIREPs que traen telemetria ACARS.
 *
 * Pensado para ejecutarse desde el cron, no en el flujo del PIREP: la llamada
 * al modelo tarda unos segundos y no debe bloquear ni condicionar la entrega
 * de un PIREP por el cliente ACARS.
 */
class PirepAiFeedback extends Command
{
    protected $signature = 'phpvms:pireps-ai-feedback
        {--pirep= : Analizar solo este ID de PIREP (ignora --limit)}
        {--limit=10 : Numero maximo de PIREPs pendientes a procesar}
        {--force : Reanalizar aunque ya exista retroalimentacion (tambien en lote)}
        {--source= : Acotar el lote a un cliente ACARS, por prefijo de source_name (ej. vmsOpenAcars)}
        {--dry-run : Mostrar la telemetria que se enviaria, sin llamar a la API}
        {--recent=0 : Informe para staff: mostrar los N ultimos analisis por gravedad}
        {--stats : Informe para staff: resumen agregado}';

    protected $description = 'Genera retroalimentacion automatica de IA para los PIREPs con telemetria ACARS';

    public function handle(): int
    {
        /** @var PirepFeedbackService $service */
        $service = app(PirepFeedbackService::class);

        if ($this->option('stats')) {
            return $this->showStats();
        }

        if ((int) $this->option('recent') > 0) {
            return $this->showRecent((int) $this->option('recent'));
        }

        // Analisis de un PIREP concreto
        if ($pirepId = $this->option('pirep')) {
            return $this->analyseOne($service, (string) $pirepId);
        }

        if (!$service->isConfigured()) {
            $this->error('No hay clave de DeepSeek configurada.');
            $this->line('Admin > Settings > general.deepseek_api_key');

            return self::FAILURE;
        }

        $limit = max(1, (int) $this->option('limit'));
        $source = $this->option('source') ?: null;

        // `--force` en lote: reanaliza tambien los que ya tienen feedback. Sin
        // el, solo se procesan los que nunca se analizaron.
        $pireps = $service->pendingPireps($limit, (bool) $this->option('force'), $source);

        if ($pireps->isEmpty()) {
            $this->info('No hay PIREPs pendientes de analisis.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Modelo: %s | Cliente: %s | PIREPs a procesar: %d%s',
            $service->modelName(),
            $source ?? 'todos',
            $pireps->count(),
            $this->option('force') ? ' (reanalizando)' : ''
        ));

        $ok = 0;
        $failed = 0;
        $tokens = 0;

        foreach ($pireps as $pirep) {
            try {
                $feedback = $service->analyseAndStore($pirep);
                $tokens += (int) $feedback->total_tokens;

                $this->line(sprintf(
                    '  <info>OK</info>   %s %s-%s | %s [%s]',
                    $pirep->flight_number,
                    $pirep->dpt_airport_id,
                    $pirep->arr_airport_id,
                    $feedback->verdict ?? '-',
                    $feedback->severityLabel()
                ));
                $ok++;
            } catch (PirepFeedbackException $e) {
                $this->line(sprintf(
                    '  <error>FALLO</error> %s %s-%s | %s',
                    $pirep->flight_number,
                    $pirep->dpt_airport_id,
                    $pirep->arr_airport_id,
                    $e->getMessage()
                ));
                $failed++;
            }
        }

        $this->newLine();
        $this->info(sprintf('Procesados: %d correctos, %d fallidos. Tokens consumidos: %d', $ok, $failed, $tokens));

        return $failed > 0 && $ok === 0 ? self::FAILURE : self::SUCCESS;
    }

    private function analyseOne(PirepFeedbackService $service, string $pirepId): int
    {
        $pirep = Pirep::find($pirepId);
        if ($pirep === null) {
            $this->error('No existe el PIREP '.$pirepId);

            return self::FAILURE;
        }

        $this->info(sprintf(
            'PIREP %s | %s %s-%s | landing_rate %s',
            $pirep->id,
            $pirep->flight_number,
            $pirep->dpt_airport_id,
            $pirep->arr_airport_id,
            $pirep->landing_rate ?? 'n/d'
        ));

        if ($this->option('dry-run')) {
            $payload = $service->buildPayload($pirep);
            if ($payload === null) {
                $this->error('Sin telemetria analizable.');

                return self::FAILURE;
            }

            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        if (!$service->isConfigured()) {
            $this->error('No hay clave de DeepSeek configurada.');

            return self::FAILURE;
        }

        if (!$this->option('force') && AiFeedback::where('pirep_id', $pirep->id)->exists()) {
            $this->warn('Ya tiene retroalimentacion. Usa --force para reanalizar.');

            return self::SUCCESS;
        }

        try {
            $feedback = $service->analyseAndStore($pirep);
        } catch (PirepFeedbackException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->renderFeedback($feedback);
        $this->newLine();
        $this->line(sprintf(
            'Tokens: %s (prompt %s + respuesta %s)',
            $feedback->total_tokens ?? 'n/d',
            $feedback->prompt_tokens ?? 'n/d',
            $feedback->completion_tokens ?? 'n/d'
        ));

        return self::SUCCESS;
    }

    /**
     * Informe para staff: los ultimos analisis ordenados por gravedad, para
     * localizar rapido los pilotos que necesitan seguimiento.
     */
    private function showRecent(int $limit): int
    {
        $rows = AiFeedback::query()
            ->with('pirep')
            ->orderByDesc('severity')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            $this->info('Todavia no hay retroalimentacion generada.');

            return self::SUCCESS;
        }

        $this->table(
            ['Fecha', 'Vuelo', 'Ruta', 'LR', 'Gravedad', 'Veredicto'],
            $rows->map(fn (AiFeedback $f) => [
                optional($f->created_at)->format('Y-m-d H:i'),
                $f->pirep?->flight_number ?? '-',
                ($f->pirep?->dpt_airport_id ?? '?').'-'.($f->pirep?->arr_airport_id ?? '?'),
                $f->pirep?->landing_rate ?? '-',
                $f->severityLabel(),
                $f->verdict ?? '-',
            ])->all()
        );

        return self::SUCCESS;
    }

    private function showStats(): int
    {
        $total = AiFeedback::count();
        if ($total === 0) {
            $this->info('Todavia no hay retroalimentacion generada.');

            return self::SUCCESS;
        }

        $bySeverity = AiFeedback::query()
            ->select('severity', DB::raw('COUNT(*) AS n'))
            ->groupBy('severity')
            ->pluck('n', 'severity');

        $this->info('Analisis generados: '.$total);
        foreach (AiFeedback::$severity_labels as $level => $label) {
            $this->line(sprintf('  %-12s %d', $label.':', (int) ($bySeverity[$level] ?? 0)));
        }

        $tokens = (int) AiFeedback::sum('total_tokens');
        $this->newLine();
        $this->line('Tokens consumidos en total: '.$tokens);
        if ($total > 0) {
            $this->line('Media por analisis: '.round($tokens / $total));
        }

        // Coste estimado con las tarifas publicas de deepseek-flash.
        $service = app(PirepFeedbackService::class);
        $prompt = (int) AiFeedback::sum('prompt_tokens');
        $completion = (int) AiFeedback::sum('completion_tokens');

        $this->newLine();
        $this->line(sprintf('Entrada: %s tokens | Salida: %s tokens (incluye razonamiento)', $prompt, $completion));
        $this->line(sprintf('Coste real estimado  pico: US$ %.4f | valle: US$ %.4f', $service->estimatedCost($prompt, $completion, true), $service->estimatedCost($prompt, $completion, false)));
        if ($total > 0) {
            $perPirep = $service->estimatedCost((int) round($prompt / $total), (int) round($completion / $total), true);
            $this->line(sprintf('Coste medio por PIREP   pico: US$ %.5f | valle: US$ %.5f', $perPirep, $service->estimatedCost((int) round($prompt / $total), (int) round($completion / $total), false)));
        }

        $pending = $service->pendingPireps(null)->count();
        $this->line('Pendientes en cola: '.$pending);

        return self::SUCCESS;
    }

    private function renderFeedback(AiFeedback $feedback): void
    {
        $this->line('<options=bold>'.$feedback->verdict.'</> ('.$feedback->severityLabel().')');

        if (!empty($feedback->good_points)) {
            $this->newLine();
            $this->line('<info>Puntos fuertes</info>');
            foreach ($feedback->good_points as $point) {
                $this->line('  + '.$point);
            }
        }

        if (!empty($feedback->errors)) {
            $this->newLine();
            $this->line('<error>Errores</error>');
            foreach ($feedback->errors as $error) {
                $this->line('  - '.$error);
            }
        }

        if (!empty($feedback->action)) {
            $this->newLine();
            $this->line('<comment>Accion para el proximo vuelo</comment>');
            $this->line('  '.$feedback->action);
        }
    }
}
