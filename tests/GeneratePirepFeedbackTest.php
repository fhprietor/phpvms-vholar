<?php

namespace Tests;

use App\Events\PirepFiled;
use App\Jobs\GeneratePirepFeedback;
use App\Listeners\PirepFeedbackListener;
use App\Models\Acars;
use App\Models\Enums\AcarsType;
use App\Models\Pirep;
use App\Models\PirepAiFeedback;
use App\Models\Setting;
use App\Notifications\Messages\Broadcast\PirepFeedback as DiscordFeedback;
use App\Notifications\Messages\PirepFeedback as MailFeedback;
use App\Services\PirepFeedbackService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

/**
 * Flujo inmediato: PIREP presentado -> job en cola -> analisis -> Discord y
 * email, guardando el resultado.
 *
 * Lo critico que se protege aqui:
 *  - que la presentacion de un PIREP NUNCA dependa del analisis (si la API
 *    falla, el job se registra y se acaba, no propaga);
 *  - que con la funcion apagada no se gaste un solo token;
 *  - que Discord y email sean avisos independientes: si uno se desactiva, el
 *    otro sigue saliendo.
 */
final class GeneratePirepFeedbackTest extends TestCase
{
    private const TOUCHDOWN_LOG = [
        'DATOS DE ATERRIZAJE:',
        'Aterrizaje registrado: -73 fpm, 1,05 G, Rumbo: 340°, Cabeceo: 7,0°, Alabeo: 0,0°',
        'PISTA 35 | TD: 2331 ft desde umbral | CL: 2 ft de desviación',
    ];

    private function apiResponse(): array
    {
        return [
            'model'   => 'deepseek-flash',
            'choices' => [
                ['message' => ['content' => json_encode([
                    'veredicto'      => 'Aterrizaje suave pero largo',
                    'severidad'      => 1,
                    'puntos_fuertes' => ['VS de -73 fpm'],
                    'errores'        => ['Toma a 2331 ft del umbral'],
                    'accion'         => 'Tocar entre 1000 y 1500 ft',
                ], JSON_UNESCAPED_UNICODE)]],
            ],
            'usage' => ['prompt_tokens' => 500, 'completion_tokens' => 2000, 'total_tokens' => 2500],
        ];
    }

    /**
     * Encola respuestas de la API. Acepta un unico cuerpo (reconocible porque
     * trae la clave `choices`) o una lista de cuerpos para los reintentos.
     */
    private function mockApi(array|string $bodies): void
    {
        if (is_array($bodies) && array_key_exists('choices', $bodies)) {
            $bodies = [$bodies];
        }

        $responses = [];
        foreach ((array) $bodies as $body) {
            $responses[] = new Response(200, ['Content-Type' => 'application/json'],
                is_string($body) ? $body : json_encode($body));
        }

        app()->instance(Client::class, new Client(['handler' => HandlerStack::create(new MockHandler($responses))]));
    }

    private function service(): PirepFeedbackService
    {
        return app(PirepFeedbackService::class);
    }

    private function configureApiKey(string $key = 'test-key'): void
    {
        Setting::where('key', 'general.deepseek_api_key')->update(['value' => $key]);
    }

    private function setSetting(string $key, mixed $value): void
    {
        Setting::where('key', $key)->update(['value' => is_bool($value) ? (int) $value : $value]);
    }

    private function enableFeedback(bool $discord = true, bool $mail = true): void
    {
        $this->configureApiKey();
        $this->setSetting('general.deepseek_feedback_enabled', true);
        $this->setSetting('notifications.discord_pirep_feedback', $discord);
        $this->setSetting('notifications.mail_pirep_feedback', $mail);
    }

    private function makePirep(array $logLines = self::TOUCHDOWN_LOG): Pirep
    {
        $pirep = Pirep::factory()->create([
            'landing_rate' => -73,
            'source'       => 1,
        ]);

        foreach ($logLines as $i => $line) {
            Acars::factory()->create([
                'pirep_id'   => $pirep->id,
                'type'       => AcarsType::LOG,
                'log'        => $line,
                'created_at' => now()->addSeconds($i),
            ]);
        }

        return $pirep;
    }

    // ---------------------------------------------------------------- listener

    public function test_the_listener_does_not_queue_anything_when_disabled(): void
    {
        Queue::fake();
        $this->setSetting('general.deepseek_feedback_enabled', false);

        (new PirepFeedbackListener())->handle(new PirepFiled($this->makePirep()));

        Queue::assertNothingPushed();
    }

    public function test_the_listener_queues_the_analysis_when_enabled(): void
    {
        Queue::fake();
        $this->setSetting('general.deepseek_feedback_enabled', true);

        $pirep = $this->makePirep();
        (new PirepFeedbackListener())->handle(new PirepFiled($pirep));

        Queue::assertPushed(GeneratePirepFeedback::class);
    }

    /**
     * El listener corre dentro del request del cliente ACARS: si algo falla al
     * encolar, no puede tumbar la presentacion del PIREP.
     */
    public function test_the_listener_swallows_queueing_errors(): void
    {
        $this->setSetting('general.deepseek_feedback_enabled', true);
        Queue::shouldReceive('push')->andThrow(new \RuntimeException('cola caida'));

        (new PirepFeedbackListener())->handle(new PirepFiled($this->makePirep()));

        $this->assertTrue(true); // llego aqui sin lanzar
    }

    // --------------------------------------------------------------------- job

    public function test_the_job_does_nothing_when_disabled(): void
    {
        $this->configureApiKey();
        $this->setSetting('general.deepseek_feedback_enabled', false);
        Notification::fake();
        $this->mockApi($this->apiResponse());

        $pirep = $this->makePirep();
        (new GeneratePirepFeedback($pirep))->handle($this->service());

        $this->assertDatabaseMissing('pirep_ai_feedback', ['pirep_id' => $pirep->id]);
        Notification::assertNothingSent();
    }

    public function test_the_job_skips_pireps_without_telemetry(): void
    {
        $this->enableFeedback();
        Notification::fake();
        $this->mockApi($this->apiResponse());

        $pirep = $this->makePirep([]); // sin logs
        (new GeneratePirepFeedback($pirep))->handle($this->service());

        $this->assertDatabaseMissing('pirep_ai_feedback', ['pirep_id' => $pirep->id]);
        Notification::assertNothingSent();
    }

    public function test_the_job_stores_the_feedback_and_sends_both_notices(): void
    {
        $this->enableFeedback();
        Notification::fake();
        $this->mockApi($this->apiResponse());

        $pirep = $this->makePirep();
        (new GeneratePirepFeedback($pirep))->handle($this->service());

        $this->assertDatabaseHas('pirep_ai_feedback', [
            'pirep_id' => $pirep->id,
            'verdict'  => 'Aterrizaje suave pero largo',
            'severity' => 1,
        ]);

        Notification::assertSentTo($pirep, DiscordFeedback::class);
        Notification::assertSentTo($pirep->user, MailFeedback::class);
    }

    public function test_the_discord_notice_can_be_turned_off_without_affecting_the_email(): void
    {
        $this->enableFeedback(discord: false, mail: true);
        Notification::fake();
        $this->mockApi($this->apiResponse());

        $pirep = $this->makePirep();
        (new GeneratePirepFeedback($pirep))->handle($this->service());

        Notification::assertNotSentTo($pirep, DiscordFeedback::class);
        Notification::assertSentTo($pirep->user, MailFeedback::class);
    }

    public function test_the_email_can_be_turned_off_without_affecting_discord(): void
    {
        $this->enableFeedback(discord: true, mail: false);
        Notification::fake();
        $this->mockApi($this->apiResponse());

        $pirep = $this->makePirep();
        (new GeneratePirepFeedback($pirep))->handle($this->service());

        Notification::assertSentTo($pirep, DiscordFeedback::class);
        Notification::assertNotSentTo($pirep->user, MailFeedback::class);
    }

    /**
     * Si la API falla, el job termina sin excepcion y sin notificar: el PIREP
     * ya esta registrado y la retroalimentacion es un extra.
     */
    public function test_an_api_failure_does_not_propagate_and_notifies_nothing(): void
    {
        $this->enableFeedback();
        Notification::fake();

        // Dos respuestas vacias: el servicio reintenta una vez y se rinde.
        $this->mockApi([
            ['choices' => [['message' => ['content' => '']]]],
            ['choices' => [['message' => ['content' => '']]]],
        ]);

        $pirep = $this->makePirep();

        (new GeneratePirepFeedback($pirep))->handle($this->service());

        $this->assertDatabaseMissing('pirep_ai_feedback', ['pirep_id' => $pirep->id]);
        Notification::assertNothingSent();
    }

    public function test_the_job_does_nothing_without_an_api_key(): void
    {
        $this->setSetting('general.deepseek_feedback_enabled', true);
        $this->configureApiKey('');
        Notification::fake();
        $this->mockApi($this->apiResponse());

        $pirep = $this->makePirep();
        (new GeneratePirepFeedback($pirep))->handle($this->service());

        $this->assertDatabaseMissing('pirep_ai_feedback', ['pirep_id' => $pirep->id]);
        Notification::assertNothingSent();
    }

    /**
     * El job espera a que confirme la transaccion: evita leer un PIREP a medio
     * escribir.
     */
    public function test_the_job_waits_for_the_commit(): void
    {
        $job = new GeneratePirepFeedback($this->makePirep());

        $this->assertTrue($job->afterCommit);
        $this->assertSame(1, $job->tries);
        // Debe superar el timeout HTTP de 180 s del servicio.
        $this->assertGreaterThan(180, $job->timeout);
    }

    /**
     * El aviso de Discord lleva los numeros concretos del aterrizaje, que es
     * lo que lo distingue del mensaje de PIREP presentado.
     */
    public function test_the_discord_embed_carries_the_landing_metrics(): void
    {
        $this->enableFeedback();

        $pirep = $this->makePirep();
        $feedback = PirepAiFeedback::create([
            'pirep_id' => $pirep->id,
            'model'    => 'deepseek-flash',
            'verdict'  => 'Aterrizaje suave pero largo',
            'severity' => 1,
            'action'   => 'Tocar entre 1000 y 1500 ft',
        ]);

        $message = (new DiscordFeedback($feedback, $pirep))->toDiscordChannel($pirep);
        $embed = $message->toArray()['embeds'][0];

        $fields = collect($embed['fields'])->pluck('value', 'name')->all();

        $this->assertArrayHasKey('**Tasa**', $fields);
        $this->assertSame('-73 fpm', $fields['**Tasa**']);
        $this->assertSame('2331 ft', $fields['**Zona TD**']);
        $this->assertStringContainsString('Tocar entre 1000 y 1500 ft', $embed['description']);
    }

    // ------------------------------------------------------------------- vista

    /**
     * La tarjeta se prueba renderizando su parcial, no la pagina entera.
     *
     * Renderizar `pireps.show` completo en la suite no es viable por dos
     * motivos ajenos a esta funcion: la vista del tema referencia la ruta
     * `DBasic.aircraft` de un modulo inactivo (route not defined), y el tema
     * activo de la base de datos de test es `seven`, no `vholar`.
     */
    private function renderCard(?PirepAiFeedback $feedback): string
    {
        return view('components.pirep-ai-feedback', ['aiFeedback' => $feedback])->render();
    }

    public function test_the_card_shows_the_full_feedback(): void
    {
        $pirep = $this->makePirep();
        $feedback = PirepAiFeedback::create([
            'pirep_id'    => $pirep->id,
            'model'       => 'deepseek-flash',
            'verdict'     => 'Aterrizaje suave pero largo',
            'severity'    => 2,
            'good_points' => ['VS de -73 fpm'],
            'errors'      => ['Toma a 2331 ft del umbral'],
            'action'      => 'Tocar entre 1000 y 1500 ft',
        ]);

        $html = $this->renderCard($feedback);

        $this->assertStringContainsString('Análisis del Instructor', $html);
        $this->assertStringContainsString('Aterrizaje suave pero largo', $html);
        $this->assertStringContainsString('VS de -73 fpm', $html);
        $this->assertStringContainsString('Toma a 2331 ft del umbral', $html);
        $this->assertStringContainsString('Tocar entre 1000 y 1500 ft', $html);
        // Gravedad 2 -> acento de peligro del tema y etiqueta "Critico".
        $this->assertStringContainsString('var(--vh-danger)', $html);
        $this->assertStringContainsString('Crítico', $html);
    }

    /**
     * Regresion: el <html> lleva data-bs-theme="dark", y en modo oscuro
     * Bootstrap pinta alert-warning con fondo #332701 (mostaza oscuro). La
     * clase `text-dark` que llevaba encima dejaba el texto en #212529, es decir
     * 1.05:1 de contraste: las recomendaciones se volvian invisibles.
     *
     * Por eso la tarjeta no puede volver a usar las clases semanticas de
     * Bootstrap para el color; usa los tokens --vh-* de tokens.css, que si
     * cumplen las reglas de contraste documentadas alli.
     */
    public function test_the_card_avoids_bootstrap_semantic_colours(): void
    {
        $pirep = $this->makePirep();
        $feedback = PirepAiFeedback::create([
            'pirep_id' => $pirep->id,
            'model'    => 'deepseek-flash',
            'verdict'  => 'Veredicto de prueba',
            'severity' => 1,
            'action'   => 'Accion de prueba',
        ]);

        $html = $this->renderCard($feedback);

        $this->assertStringNotContainsString('text-dark', $html);
        $this->assertStringNotContainsString('alert-warning', $html);
        $this->assertStringNotContainsString('bg-warning', $html);
        $this->assertStringNotContainsString('border-warning', $html);
        $this->assertStringNotContainsString('text-warning', $html);

        // Los tokens del tema si estan, y el -soft acompana al acento.
        $this->assertStringContainsString('var(--vh-warning)', $html);
        $this->assertStringContainsString('var(--vh-warning-soft)', $html);
        $this->assertStringContainsString('var(--vh-text)', $html);
    }

    public function test_each_severity_maps_to_its_own_theme_accent(): void
    {
        $expected = [
            PirepAiFeedback::SEVERITY_OK         => '--vh-success',
            PirepAiFeedback::SEVERITY_IMPROVABLE => '--vh-warning',
            PirepAiFeedback::SEVERITY_CRITICAL   => '--vh-danger',
        ];

        foreach ($expected as $severity => $token) {
            $pirep = $this->makePirep();
            $feedback = PirepAiFeedback::create([
                'pirep_id' => $pirep->id,
                'model'    => 'deepseek-flash',
                'severity' => $severity,
                'action'   => 'Accion',
            ]);

            $html = $this->renderCard($feedback);

            $this->assertStringContainsString('var('.$token.')', $html);
            $this->assertStringContainsString('var('.$token.'-soft)', $html);
        }
    }

    public function test_the_card_renders_nothing_without_feedback(): void
    {
        $this->assertSame('', trim($this->renderCard(null)));
    }

    /**
     * El contenido lo escribe el modelo: si devolviera HTML, Blade lo escaparia
     * igualmente, pero conviene fijarlo para que nadie lo cambie a {!! !!}.
     */
    public function test_the_card_escapes_model_output(): void
    {
        $pirep = $this->makePirep();
        $feedback = PirepAiFeedback::create([
            'pirep_id' => $pirep->id,
            'model'    => 'deepseek-flash',
            'verdict'  => '<script>alert(1)</script>',
            'severity' => 1,
        ]);

        $html = $this->renderCard($feedback);

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }
}
