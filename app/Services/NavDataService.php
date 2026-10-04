<?php

namespace App\Services;

use App\Contracts\Service;
use App\Models\User;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Entrega al cliente ACARS autenticado las credenciales del servicio NavData
 * (URL + API key) que el staff mantiene en Admin > Settings.
 *
 * phpVMS NO hace de proxy: entrega las credenciales una vez y a partir de ahi
 * el cliente habla directamente con NavData. Para que la clave no viaje (ni
 * quede) en claro, se envia dentro de un sobre cifrado cuya clave se deriva
 * con HKDF-SHA256 de la api_key con la que el piloto se ha autenticado: el
 * unico secreto compartido es el que el cliente ya usa en X-API-KEY.
 *
 * Hay dos sobres, porque el cliente ACARS es .NET Framework 4.8.1 y ahi no
 * existen AesGcm ni HKDF:
 *
 *   - aes-256-gcm (defecto): autenticado, nonce de 12 B.
 *         base64( nonce[12] || tag[16] || ciphertext )
 *   - aes-256-cbc-hmac-sha256: solo BCL (AesCryptoServiceProvider +
 *     HMACSHA256), encrypt-then-MAC, IV de 16 B.
 *         mac = HMAC-SHA256(mac_key, AAD || iv || ciphertext)
 *         base64( iv[16] || ciphertext || mac[32] )
 *
 * En ambos, el JSON plano es el mismo:
 *
 *     {"url":..., "key":..., "key_id":..., "issued_at":..., "expires_at":...}
 *
 * Contrato completo y ejemplos en docs/vmsopenacars/ENTREGA-CLAVE-NAVDATA.md.
 */
class NavDataService extends Service
{
    public const CIPHER_GCM = 'aes-256-gcm';

    public const CIPHER_CBC = 'aes-256-cbc-hmac-sha256';

    public const DEFAULT_CIPHER = self::CIPHER_GCM;

    /** Longitud del nonce de AES-GCM, en bytes. */
    public const NONCE_BYTES = 12;

    /** Longitud del tag de autenticacion de AES-GCM, en bytes. */
    public const TAG_BYTES = 16;

    /** Longitud del IV de AES-CBC, en bytes. */
    public const IV_BYTES = 16;

    /** Longitud del HMAC-SHA256 del sobre CBC, en bytes. */
    public const MAC_BYTES = 32;

    private const KEY_BYTES = 32;

    /**
     * Sobres que el endpoint sabe emitir.
     *
     * @return string[]
     */
    public static function supportedCiphers(): array
    {
        return [self::CIPHER_GCM, self::CIPHER_CBC];
    }

    /**
     * Clave de NavData configurada (Admin > Settings), o null si esta vacia.
     */
    public function getApiKey(): ?string
    {
        return $this->readSetting('general.navdata_api_key');
    }

    /**
     * URL base del servicio NavData configurada (Admin > Settings), o null.
     *
     * Se normaliza sin barra final: el cliente concatena rutas tal cual sobre
     * la base ("https://host/api/v1" + "/runways"), asi que una barra de mas
     * produce un `//` que el servicio puede no reconocer. El `.config` que se
     * publicaba llevaba barra final, de ahi la guarda.
     */
    public function getApiUrl(): ?string
    {
        $url = $this->readSetting('general.navdata_api_url');
        if ($url === null) {
            return null;
        }

        $url = rtrim($url, '/');

        return $url === '' ? null : $url;
    }

    /**
     * Sin URL y clave no hay nada que entregar: el endpoint responde 503.
     */
    public function isConfigured(): bool
    {
        return $this->getApiKey() !== null && $this->getApiUrl() !== null;
    }

    public function supportsCipher(string $cipher): bool
    {
        return \in_array($cipher, self::supportedCiphers(), true);
    }

    /**
     * Huella estable y no reversible de la clave configurada. Permite al
     * cliente detectar una rotacion sin descifrar nada y sin exponer la clave.
     */
    public function getKeyFingerprint(): string
    {
        return substr(hash_hmac('sha256', $this->getApiKey() ?? '', $this->getHmacSecret()), 0, 16);
    }

    /**
     * Validez del sobre, en segundos. Al expirar, el cliente debe volver a
     * pedirlo: asi recoge una clave rotada sin reinstalar nada.
     */
    public function getEnvelopeTtl(): int
    {
        return max(60, (int) config('vholar.navdata.envelope_ttl', 21600));
    }

    /**
     * Construye el sobre que se le devuelve al cliente. La parte de metadatos
     * (algoritmo, huella, caducidad) va en claro porque no es secreta; la URL
     * y la clave van solo dentro de `payload`.
     */
    public function sealForUser(User $user, string $cipher = self::DEFAULT_CIPHER): array
    {
        $issuedAt = Carbon::now();
        $expiresAt = $issuedAt->copy()->addSeconds($this->getEnvelopeTtl());
        $keyId = $this->getKeyFingerprint();

        $payload = [
            'url'        => $this->getApiUrl(),
            'key'        => $this->getApiKey(),
            'key_id'     => $keyId,
            'issued_at'  => $issuedAt->toIso8601String(),
            'expires_at' => $expiresAt->toIso8601String(),
        ];

        return [
            'service'    => 'navdata',
            'cipher'     => $cipher,
            'kdf'        => 'hkdf-sha256',
            'key_id'     => $keyId,
            'issued_at'  => $payload['issued_at'],
            'expires_at' => $payload['expires_at'],
            'payload'    => $this->seal($payload, $user->api_key, $cipher),
        ];
    }

    /**
     * Cifra el payload con la api_key indicada y devuelve el sobre en base64.
     */
    public function seal(array $payload, string $apiKey, string $cipher = self::DEFAULT_CIPHER): string
    {
        if (!$this->supportsCipher($cipher)) {
            throw new \InvalidArgumentException('Unsupported NavData envelope cipher: '.$cipher);
        }

        $plaintext = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($plaintext === false) {
            throw new RuntimeException('Unable to encode the NavData payload');
        }

        return $cipher === self::CIPHER_CBC
            ? $this->sealCbc($plaintext, $apiKey)
            : $this->sealGcm($plaintext, $apiKey);
    }

    /**
     * Abre un sobre. Devuelve null si el base64, el tag/mac o la api_key no son
     * validos. Se usa en los tests y para diagnosticar desde consola; el
     * cliente ACARS implementa lo mismo en su lenguaje.
     */
    public function open(string $sealed, string $apiKey, string $cipher = self::DEFAULT_CIPHER): ?array
    {
        if (!$this->supportsCipher($cipher)) {
            throw new \InvalidArgumentException('Unsupported NavData envelope cipher: '.$cipher);
        }

        return $cipher === self::CIPHER_CBC
            ? $this->openCbc($sealed, $apiKey)
            : $this->openGcm($sealed, $apiKey);
    }

    /**
     * HKDF-SHA256 (RFC 5869) con la api_key del piloto como IKM. El salt es una
     * constante publica; el info separa el uso de cada sobre.
     */
    public function deriveKey(string $apiKey, int $length = self::KEY_BYTES, string $info = ''): string
    {
        return hash_hkdf(
            'sha256',
            $apiKey,
            $length,
            $info !== '' ? $info : $this->getKdfInfo(),
            (string) config('vholar.navdata.kdf_salt', 'vmsopenacars/navdata/v1')
        );
    }

    private function sealGcm(string $plaintext, string $apiKey): string
    {
        $nonce = random_bytes(self::NONCE_BYTES);

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER_GCM,
            $this->deriveKey($apiKey),
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            $this->getAad()
        );

        if ($ciphertext === false || !isset($tag)) {
            throw new RuntimeException('Unable to seal the NavData envelope (aes-256-gcm)');
        }

        return base64_encode($nonce.$tag.$ciphertext);
    }

    private function openGcm(string $sealed, string $apiKey): ?array
    {
        $blob = base64_decode($sealed, true);
        if ($blob === false || \strlen($blob) <= self::NONCE_BYTES + self::TAG_BYTES) {
            return null;
        }

        $plaintext = openssl_decrypt(
            substr($blob, self::NONCE_BYTES + self::TAG_BYTES),
            self::CIPHER_GCM,
            $this->deriveKey($apiKey),
            OPENSSL_RAW_DATA,
            substr($blob, 0, self::NONCE_BYTES),
            substr($blob, self::NONCE_BYTES, self::TAG_BYTES),
            $this->getAad()
        );

        return $plaintext === false ? null : $this->decodePayload($plaintext);
    }

    private function sealCbc(string $plaintext, string $apiKey): string
    {
        $keys = $this->deriveCbcKeys($apiKey);
        $iv = random_bytes(self::IV_BYTES);

        $ciphertext = openssl_encrypt(
            $plaintext,
            'aes-256-cbc',
            $keys['enc'],
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($ciphertext === false) {
            throw new RuntimeException('Unable to seal the NavData envelope (aes-256-cbc)');
        }

        $mac = hash_hmac('sha256', $this->getAad().$iv.$ciphertext, $keys['mac'], true);

        return base64_encode($iv.$ciphertext.$mac);
    }

    private function openCbc(string $sealed, string $apiKey): ?array
    {
        $blob = base64_decode($sealed, true);
        if ($blob === false || \strlen($blob) <= self::IV_BYTES + self::MAC_BYTES) {
            return null;
        }

        $iv = substr($blob, 0, self::IV_BYTES);
        $mac = substr($blob, -self::MAC_BYTES);
        $ciphertext = substr($blob, self::IV_BYTES, -self::MAC_BYTES);

        $keys = $this->deriveCbcKeys($apiKey);
        $expected = hash_hmac('sha256', $this->getAad().$iv.$ciphertext, $keys['mac'], true);

        // Encrypt-then-MAC: sin un mac valido no se toca el criptograma.
        if (!hash_equals($expected, $mac)) {
            return null;
        }

        $plaintext = openssl_decrypt(
            $ciphertext,
            'aes-256-cbc',
            $keys['enc'],
            OPENSSL_RAW_DATA,
            $iv
        );

        return $plaintext === false ? null : $this->decodePayload($plaintext);
    }

    /**
     * 64 bytes de HKDF: 32 para AES-256-CBC y 32 para el HMAC-SHA256.
     *
     * @return array{enc: string, mac: string}
     */
    private function deriveCbcKeys(string $apiKey): array
    {
        $material = $this->deriveKey($apiKey, self::KEY_BYTES * 2, $this->getKdfInfo().'-cbc');

        return [
            'enc' => substr($material, 0, self::KEY_BYTES),
            'mac' => substr($material, self::KEY_BYTES),
        ];
    }

    private function decodePayload(string $plaintext): ?array
    {
        $payload = json_decode($plaintext, true);

        return \is_array($payload) ? $payload : null;
    }

    private function getAad(): string
    {
        return (string) config('vholar.navdata.aad', 'vmsopenacars/navdata/v1');
    }

    private function getKdfInfo(): string
    {
        return (string) config('vholar.navdata.kdf_info', 'navdata-api-key');
    }

    private function getHmacSecret(): string
    {
        return (string) config('app.key');
    }

    /**
     * Lee una setting y la normaliza: las cadenas vacias son "no configurado".
     */
    private function readSetting(string $key): ?string
    {
        $value = setting($key, null);
        if (!\is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
