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
 * quede) en claro, se envia dentro de un sobre AES-256-GCM cuya clave se
 * deriva con HKDF-SHA256 de la api_key con la que el piloto se ha autenticado:
 * el unico secreto compartido es el que el cliente ya usa en X-API-KEY.
 *
 * Formato del sobre (ver docs/vmsopenacars/ENTREGA-CLAVE-NAVDATA.md):
 *
 *     base64( nonce[12] || tag[16] || ciphertext )
 *
 * con AAD = config('vholar.navdata.aad') y el JSON plano:
 *
 *     {"url":..., "key":..., "key_id":..., "issued_at":..., "expires_at":...}
 */
class NavDataService extends Service
{
    public const CIPHER = 'aes-256-gcm';

    public const KDF = 'hkdf-sha256';

    /** Longitud del nonce de AES-GCM, en bytes. */
    public const NONCE_BYTES = 12;

    /** Longitud del tag de autenticacion de AES-GCM, en bytes. */
    public const TAG_BYTES = 16;

    private const KEY_BYTES = 32;

    /**
     * Clave de NavData configurada (Admin > Settings), o null si esta vacia.
     */
    public function getApiKey(): ?string
    {
        return $this->readSetting('general.navdata_api_key');
    }

    /**
     * URL base del servicio NavData configurada (Admin > Settings), o null.
     */
    public function getApiUrl(): ?string
    {
        return $this->readSetting('general.navdata_api_url');
    }

    /**
     * Sin URL y clave no hay nada que entregar: el endpoint responde 503.
     */
    public function isConfigured(): bool
    {
        return $this->getApiKey() !== null && $this->getApiUrl() !== null;
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
    public function sealForUser(User $user): array
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
            'cipher'     => self::CIPHER,
            'kdf'        => self::KDF,
            'key_id'     => $keyId,
            'issued_at'  => $payload['issued_at'],
            'expires_at' => $payload['expires_at'],
            'payload'    => $this->seal($payload, $user->api_key),
        ];
    }

    /**
     * Cifra el payload con la api_key indicada y devuelve el sobre en base64.
     */
    public function seal(array $payload, string $apiKey): string
    {
        $nonce = random_bytes(self::NONCE_BYTES);

        $ciphertext = openssl_encrypt(
            json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            self::CIPHER,
            $this->deriveKey($apiKey),
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            $this->getAad()
        );

        if ($ciphertext === false || !isset($tag)) {
            throw new RuntimeException('Unable to seal the NavData envelope');
        }

        return base64_encode($nonce.$tag.$ciphertext);
    }

    /**
     * Abre un sobre. Devuelve null si el base64, el tag o la api_key no son
     * validos. Se usa en los tests y para diagnosticar desde consola; el
     * cliente ACARS implementa lo mismo en su lenguaje.
     */
    public function open(string $sealed, string $apiKey): ?array
    {
        $blob = base64_decode($sealed, true);
        if ($blob === false || \strlen($blob) <= self::NONCE_BYTES + self::TAG_BYTES) {
            return null;
        }

        $nonce = substr($blob, 0, self::NONCE_BYTES);
        $tag = substr($blob, self::NONCE_BYTES, self::TAG_BYTES);
        $ciphertext = substr($blob, self::NONCE_BYTES + self::TAG_BYTES);

        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $this->deriveKey($apiKey),
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            $this->getAad()
        );

        if ($plaintext === false) {
            return null;
        }

        $payload = json_decode($plaintext, true);

        return \is_array($payload) ? $payload : null;
    }

    /**
     * HKDF-SHA256 (RFC 5869) con la api_key del piloto como IKM. El salt y el
     * info son constantes publicas.
     */
    public function deriveKey(string $apiKey): string
    {
        return hash_hkdf(
            'sha256',
            $apiKey,
            self::KEY_BYTES,
            (string) config('vholar.navdata.kdf_info', 'navdata-api-key'),
            (string) config('vholar.navdata.kdf_salt', 'vmsopenacars/navdata/v1')
        );
    }

    private function getAad(): string
    {
        return (string) config('vholar.navdata.aad', 'vmsopenacars/navdata/v1');
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
