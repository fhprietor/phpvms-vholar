<?php

namespace Tests;

use App\Models\User;
use App\Services\NavDataService;

/**
 * Entrega de la clave de NavData a los clientes ACARS (GET /api/navdata).
 *
 * El descifrado de los tests NO reutiliza App\Services\NavDataService::open():
 * replica a mano el contrato publicado en
 * docs/vmsopenacars/ENTREGA-CLAVE-NAVDATA.md (HKDF-SHA256 + AES-256-GCM, con
 * salt/info/AAD constantes). Si alguien cambia el formato sin actualizar el
 * documento, estos tests fallan.
 */
final class NavDataKeyTest extends TestCase
{
    private const API_URL = 'https://navdata.test/api';

    private const API_KEY = 'navdata-test-key-0123456789abcdef';

    /** Salt HKDF publico (contractual). */
    private const KDF_SALT = 'vmsopenacars/navdata/v1';

    /** Info HKDF publica (contractual). */
    private const KDF_INFO = 'navdata-api-key';

    /** AAD del AES-GCM (contractual). */
    private const AAD = 'vmsopenacars/navdata/v1';

    private User $pilot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pilot = User::factory()->create();
    }

    public function test_the_endpoint_requires_authentication(): void
    {
        $this->get('/api/navdata')->assertStatus(401);
        $this->withHeaders(['x-api-key' => 'not-a-key'])->get('/api/navdata')->assertStatus(401);
    }

    public function test_it_reports_an_unconfigured_service(): void
    {
        $this->withHeaders($this->headers($this->pilot))
            ->getJson('/api/navdata')
            ->assertStatus(503)
            ->assertJsonPath('type', config('phpvms.error_root').'/navdata-not-configured')
            ->assertJsonPath('settings', [
                'general.navdata_api_url',
                'general.navdata_api_key',
            ]);
    }

    public function test_a_half_configured_service_is_still_unconfigured(): void
    {
        setting_save('general.navdata_api_url', self::API_URL);

        $this->withHeaders($this->headers($this->pilot))
            ->getJson('/api/navdata')
            ->assertStatus(503);
    }

    public function test_it_delivers_the_url_and_key_sealed(): void
    {
        $this->configure();

        $response = $this->withHeaders($this->headers($this->pilot))->getJson('/api/navdata');
        $response->assertStatus(200)->assertHeader('Cache-Control', 'no-store, private');

        $data = $response->json('data');
        $this->assertSame('navdata', $data['service']);
        $this->assertSame('aes-256-gcm', $data['cipher']);
        $this->assertSame('hkdf-sha256', $data['kdf']);
        $this->assertNotEmpty($data['key_id']);
        $this->assertNotEmpty($data['expires_at']);

        // La clave no puede aparecer en claro en la respuesta.
        $this->assertStringNotContainsString(self::API_KEY, $response->getContent());

        $payload = $this->openEnvelope($data['payload'], $this->pilot->api_key);
        $this->assertIsArray($payload);
        $this->assertSame(self::API_URL, $payload['url']);
        $this->assertSame(self::API_KEY, $payload['key']);
        $this->assertSame($data['key_id'], $payload['key_id']);
        $this->assertSame($data['expires_at'], $payload['expires_at']);

        // Caducidad coherente con el TTL configurado y posterior a la emision.
        $this->assertTrue(
            strtotime($payload['expires_at']) > strtotime($payload['issued_at']),
            'expires_at debe ser posterior a issued_at'
        );
        $this->assertSame(
            app(NavDataService::class)->getEnvelopeTtl(),
            strtotime($payload['expires_at']) - strtotime($payload['issued_at'])
        );
    }

    public function test_every_delivery_uses_a_fresh_nonce(): void
    {
        $this->configure();

        $first = $this->withHeaders($this->headers($this->pilot))->getJson('/api/navdata')->json('data.payload');
        $second = $this->withHeaders($this->headers($this->pilot))->getJson('/api/navdata')->json('data.payload');

        $this->assertNotSame($first, $second);
    }

    public function test_another_pilot_cannot_open_the_envelope(): void
    {
        $this->configure();

        $intruder = User::factory()->create();
        $sealed = $this->withHeaders($this->headers($this->pilot))->getJson('/api/navdata')->json('data.payload');

        $this->assertNull($this->openEnvelope($sealed, $intruder->api_key));
        $this->assertNull(app(NavDataService::class)->open($sealed, $intruder->api_key));
    }

    public function test_a_tampered_envelope_is_rejected(): void
    {
        $this->configure();

        $sealed = $this->withHeaders($this->headers($this->pilot))->getJson('/api/navdata')->json('data.payload');
        $blob = base64_decode($sealed, true);

        // Se altera el ultimo byte del criptograma: el tag GCM debe fallar.
        $blob[\strlen($blob) - 1] = $blob[\strlen($blob) - 1] === "\x00" ? "\x01" : "\x00";

        $this->assertNull(app(NavDataService::class)->open(base64_encode($blob), $this->pilot->api_key));
    }

    public function test_it_delivers_a_cbc_envelope_when_the_client_asks_for_one(): void
    {
        $this->configure();

        $response = $this->withHeaders(array_merge($this->headers($this->pilot), [
            'X-NavData-Cipher' => 'aes-256-cbc-hmac-sha256',
        ]))->getJson('/api/navdata')->assertStatus(200);

        $data = $response->json('data');
        $this->assertSame('aes-256-cbc-hmac-sha256', $data['cipher']);
        $this->assertSame('hkdf-sha256', $data['kdf']);
        $this->assertStringNotContainsString(self::API_KEY, $response->getContent());

        $payload = $this->openCbcEnvelope($data['payload'], $this->pilot->api_key);
        $this->assertIsArray($payload);
        $this->assertSame(self::API_URL, $payload['url']);
        $this->assertSame(self::API_KEY, $payload['key']);
        $this->assertSame($data['key_id'], $payload['key_id']);

        // El servicio abre lo mismo que emite.
        $this->assertSame(
            $payload,
            app(NavDataService::class)->open($data['payload'], $this->pilot->api_key, 'aes-256-cbc-hmac-sha256')
        );
    }

    public function test_the_cbc_mac_rejects_a_tampered_envelope(): void
    {
        $this->configure();

        $sealed = $this->withHeaders(array_merge($this->headers($this->pilot), [
            'X-NavData-Cipher' => 'aes-256-cbc-hmac-sha256',
        ]))->getJson('/api/navdata')->json('data.payload');

        $blob = base64_decode($sealed, true);

        // Alterar el criptograma (no el MAC) debe invalidar el HMAC.
        $blob[20] = $blob[20] === "\x00" ? "\x01" : "\x00";
        $this->assertNull($this->openCbcEnvelope(base64_encode($blob), $this->pilot->api_key));

        // Y alterar el MAC tampoco cuela.
        $blob = base64_decode($sealed, true);
        $blob[\strlen($blob) - 1] = $blob[\strlen($blob) - 1] === "\x00" ? "\x01" : "\x00";
        $this->assertNull($this->openCbcEnvelope(base64_encode($blob), $this->pilot->api_key));
    }

    public function test_the_two_envelopes_are_not_interchangeable(): void
    {
        $this->configure();

        $gcm = $this->withHeaders($this->headers($this->pilot))->getJson('/api/navdata')->json('data.payload');
        $cbc = $this->withHeaders(array_merge($this->headers($this->pilot), [
            'X-NavData-Cipher' => 'aes-256-cbc-hmac-sha256',
        ]))->getJson('/api/navdata')->json('data.payload');

        // Salt/info distintos: una api_key no abre el sobre del otro cifrado.
        $this->assertNull($this->openCbcEnvelope($gcm, $this->pilot->api_key));
        $this->assertNull($this->openEnvelope($cbc, $this->pilot->api_key));
    }

    public function test_an_unknown_cipher_is_rejected(): void
    {
        $this->configure();

        $this->withHeaders(array_merge($this->headers($this->pilot), [
            'X-NavData-Cipher' => 'aes-128-rot13',
        ]))->getJson('/api/navdata')
            ->assertStatus(400)
            ->assertJsonPath('type', config('phpvms.error_root').'/navdata-unsupported-cipher')
            ->assertJsonPath('cipher', 'aes-128-rot13')
            ->assertJsonPath('supported', ['aes-256-gcm', 'aes-256-cbc-hmac-sha256']);
    }

    public function test_the_key_id_changes_when_the_key_is_rotated(): void
    {
        $this->configure();
        $svc = app(NavDataService::class);

        $before = $svc->getKeyFingerprint();
        setting_save('general.navdata_api_key', 'rotated-key');

        $this->assertNotSame($before, $svc->getKeyFingerprint());
        $this->assertStringNotContainsString('rotated-key', $before);
    }

    public function test_the_delivery_is_audited(): void
    {
        $this->configure();

        $this->withHeaders(array_merge($this->headers($this->pilot), [
            'User-Agent' => 'vmsOpenAcars/0.9.16',
        ]))->getJson('/api/navdata')->assertStatus(200);

        $this->assertDatabaseHas('activity_log', [
            'log_name'    => 'navdata',
            'causer_type' => User::class,
            'causer_id'   => $this->pilot->id,
            'description' => 'NavData API credentials delivered',
        ]);
    }

    public function test_the_delivery_is_not_audited_when_nothing_is_sent(): void
    {
        $this->withHeaders($this->headers($this->pilot))->getJson('/api/navdata')->assertStatus(503);

        $this->assertDatabaseMissing('activity_log', ['log_name' => 'navdata']);
    }

    /**
     * La derivacion usa HKDF-SHA256 estandar (RFC 5869, Test Case 1). Es lo que
     * permite que el cliente derive la misma clave desde .NET/Java/C++.
     */
    public function test_the_derivation_is_standard_hkdf_sha256(): void
    {
        $okm = hash_hkdf(
            'sha256',
            str_repeat("\x0b", 22),
            42,
            hex2bin('f0f1f2f3f4f5f6f7f8f9'),
            hex2bin('000102030405060708090a0b0c')
        );

        $this->assertSame(
            '3cb25f25faacd57a90434f64d0362f2a2d2d0a90cf1a5a4c5db02d56ecc4c5bf34007208d5b887185865',
            bin2hex($okm)
        );
    }

    private function configure(string $url = self::API_URL, string $key = self::API_KEY): void
    {
        setting_save('general.navdata_api_url', $url);
        setting_save('general.navdata_api_key', $key);
    }

    /**
     * Replica independiente del contrato: base64(nonce[12] || tag[16] || ct).
     */
    private function openEnvelope(string $sealed, string $apiKey): ?array
    {
        $blob = base64_decode($sealed, true);
        if ($blob === false || \strlen($blob) <= 28) {
            return null;
        }

        $key = hash_hkdf('sha256', $apiKey, 32, self::KDF_INFO, self::KDF_SALT);

        $plaintext = openssl_decrypt(
            substr($blob, 28),
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            substr($blob, 0, 12),
            substr($blob, 12, 16),
            self::AAD
        );

        return $plaintext === false ? null : json_decode($plaintext, true);
    }

    /**
     * Replica independiente del contrato CBC: base64(iv[16] || ct || mac[32]),
     * con mac = HMAC-SHA256(mac_key, AAD || iv || ct) y las claves derivadas de
     * 64 bytes de HKDF (32 enc + 32 mac).
     */
    private function openCbcEnvelope(string $sealed, string $apiKey): ?array
    {
        $blob = base64_decode($sealed, true);
        if ($blob === false || \strlen($blob) <= 48) {
            return null;
        }

        $material = hash_hkdf('sha256', $apiKey, 64, self::KDF_INFO.'-cbc', self::KDF_SALT);

        $iv = substr($blob, 0, 16);
        $mac = substr($blob, -32);
        $ciphertext = substr($blob, 16, -32);

        $expected = hash_hmac('sha256', self::AAD.$iv.$ciphertext, substr($material, 32), true);
        if (!hash_equals($expected, $mac)) {
            return null;
        }

        $plaintext = openssl_decrypt(
            $ciphertext,
            'aes-256-cbc',
            substr($material, 0, 32),
            OPENSSL_RAW_DATA,
            $iv
        );

        return $plaintext === false ? null : json_decode($plaintext, true);
    }
}
