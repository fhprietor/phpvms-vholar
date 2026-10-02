# Entrega de la clave de NavData — phpVMS → vmsOpenAcars

> **De:** equipo de phpVMS · **Para:** equipo de vmsOpenAcars
> **Fecha:** 2026-10-02 · **Estado:** endpoint implementado y probado en phpVMS. Pendiente de que
> confirméis el descifrado en vuestra build.
> **Refs:** `api_vms.md` §1.10 · `app/Services/NavDataService.php` · `tests/NavDataKeyTest.php`

---

## 1. Qué cambia y por qué

Hoy `navdata_api_key` viaja dentro del `.config` que se publica en el gestor de ficheros
(`vmsopenacarsexe.config`, bloque `<!-- NavData API -->`). Ese fichero lo descarga cualquiera que
tenga acceso al gestor: **la clave estaba publicada**.

A partir de ahora:

- El `.config` **ya no lleva la clave** (`navdata_api_key` se deja vacío; `navdata_api_url` puede
  quedar como respaldo, pero se ignora si el servidor entrega una).
- Al autenticarse, el cliente pide las credenciales a phpVMS con **su propia `vms_api_key`**.
- phpVMS **no hace de proxy**: entrega URL + clave una vez, selladas, y a partir de ahí vuestro
  cliente habla directamente con NavData (scoring de pista, procedimientos, anuncios…).

La clave **nunca sale en claro** del servidor: viaja cifrada con una clave derivada de la `vms_api_key`
del piloto, que es el único secreto que ya compartimos. Un piloto no puede descifrar el sobre de otro
(y no digamos alguien que solo tenga el `.config`).

---

## 2. Cómo se pide

```
GET /api/navdata
X-API-KEY: <vms_api_key del piloto>
X-NavData-Cipher: aes-256-cbc-hmac-sha256      (opcional; por defecto aes-256-gcm)
```

Ejemplo:

```bash
curl -sS https://va.example.com/api/navdata \
  -H "X-API-KEY: $VMS_API_KEY" \
  -H "X-NavData-Cipher: aes-256-cbc-hmac-sha256"
```

> **Importante:** el header de autenticación es `X-API-KEY`. `Authorization: Bearer <key>` **no**
> funciona (ya os lo avisamos en `RESPUESTA-PHPVMS-PRUEBAS.md` §3).

El endpoint está limitado a **30 peticiones por minuto y piloto**: pedidlo **una vez por sesión**
(ver §6), no en cada frame.

---

## 3. Respuesta

```json
{
  "data": {
    "service": "navdata",
    "cipher": "aes-256-cbc-hmac-sha256",
    "kdf": "hkdf-sha256",
    "key_id": "3f9a1c2b7d4e5061",
    "issued_at": "2026-10-02T23:21:51+00:00",
    "expires_at": "2026-10-03T05:21:51+00:00",
    "payload": "0dK3…=="
  }
}
```

| Campo | Uso en el cliente |
|---|---|
| `cipher` | Cómo abrir `payload`. Debe coincidir con lo que pedisteis |
| `key_id` | Huella de la clave de NavData. Si cambia respecto a la anterior, el staff la rotó: **refrescad** |
| `issued_at` / `expires_at` | Vigencia del sobre. Pasado `expires_at`, volved a pedirlo |
| `payload` | Sobre cifrado en base64. Dentro va `{url, key, key_id, issued_at, expires_at}` |

Errores:

| HTTP | `type` | Qué hacer |
|---|---|---|
| `400` | `navdata-unsupported-cipher` | Habéis pedido un cifrado que no existe. El cuerpo trae `supported` |
| `401` | — | `vms_api_key` ausente/inválida o piloto no ACTIVE. No reintentar en bucle |
| `503` | `navdata-not-configured` | El staff no ha rellenado URL/clave en Admin > Settings (`settings` dice qué falta). El vuelo sigue funcionando sin scoring NavData |

**Nada de esto viaja en el cuerpo de otras respuestas**: el sobre solo se entrega aquí.

---

## 4. Contrato de descifrado

Dos sobres, misma clave derivada. Constantes **exactas** (si os baila un byte, no abre):

```
IKM   = vms_api_key del piloto            (UTF-8, tal cual, sin trim ni prefijos)
SALT  = "vmsopenacars/navdata/v1"         (UTF-8)
AAD   = "vmsopenacars/navdata/v1"         (UTF-8)
```

### 4.1 `aes-256-cbc-hmac-sha256` — **recomendado para .NET Framework 4.8.1**

Todo con la BCL, sin paquetes nuevos:

```
INFO = "navdata-api-key-cbc"
material = HKDF-SHA256(ikm=IKM, salt=SALT, info=INFO, L=64)
    enc_key = material[0..32]
    mac_key = material[32..64]

blob       = base64_decode(payload)
iv         = blob[0..16]
mac        = blob[len-32..len]
ciphertext = blob[16..len-32]

mac_esperado = HMAC-SHA256(mac_key, AAD || iv || ciphertext)
if (!fixed_time_equals(mac, mac_esperado)) -> abortar     # verificar ANTES de descifrar

plaintext = AES-256-CBC(enc_key, iv, PKCS7, ciphertext)
```

### 4.2 `aes-256-gcm` — por defecto del servidor

Necesita .NET 5+ (`AesGcm`, `HKDF`) o **BouncyCastle** en .NET Framework:

```
INFO = "navdata-api-key"
key  = HKDF-SHA256(ikm=IKM, salt=SALT, info=INFO, L=32)

blob       = base64_decode(payload)
nonce      = blob[0..12]
tag        = blob[12..28]
ciphertext = blob[28..]

plaintext = AES-256-GCM(key, nonce, ciphertext, tag, AAD)
```

El JSON plano, en ambos casos:

```json
{
  "url": "https://navdata.example.com/api",
  "key": "la-clave-de-navdata",
  "key_id": "3f9a1c2b7d4e5061",
  "issued_at": "2026-10-02T23:21:51+00:00",
  "expires_at": "2026-10-03T05:21:51+00:00"
}
```

`url` es la base del servicio NavData y `key` la clave: exactamente lo que hoy sacáis de
`navdata_api_url` y `navdata_api_key`.

---

## 5. Código .NET Framework 4.8.1

`HKDF.DeriveKey` y `AesGcm` **no existen** en .NET Framework 4.8.1, así que:

- HKDF se implementa a mano: son ~15 líneas de `HMACSHA256` (RFC 5869).
- El sobre **CBC+HMAC** se abre con `AesCryptoServiceProvider` (BCL).
- Si preferís GCM en Framework, hace falta BouncyCastle (§5.3) — decidnos y lo tenemos en cuenta.

### 5.1 HKDF-SHA256 (RFC 5869) e igualdad en tiempo constante

```csharp
using System;
using System.Security.Cryptography;
using System.Text;

public static class NavDataCrypto
{
    // HKDF-Extract + HKDF-Expand para SHA-256 (RFC 5869).
    public static byte[] HkdfSha256(byte[] ikm, byte[] salt, byte[] info, int length)
    {
        using var hmac = new HMACSHA256(salt ?? new byte[32]);
        byte[] prk = hmac.ComputeHash(ikm);

        byte[] okm = new byte[length];
        byte[] previous = Array.Empty<byte>();
        int pos = 0;
        byte counter = 1;

        while (pos < length)
        {
            byte[] input = new byte[previous.Length + info.Length + 1];
            Buffer.BlockCopy(previous, 0, input, 0, previous.Length);
            Buffer.BlockCopy(info, 0, input, previous.Length, info.Length);
            input[input.Length - 1] = counter++;

            using var expand = new HMACSHA256(prk);
            previous = expand.ComputeHash(input);

            int take = Math.Min(previous.Length, length - pos);
            Buffer.BlockCopy(previous, 0, okm, pos, take);
            pos += take;
        }

        return okm;
    }

    // Comparacion en tiempo constante (CryptographicOperations es de .NET Core).
    public static bool FixedTimeEquals(byte[] a, byte[] b)
    {
        if (a == null || b == null || a.Length != b.Length) return false;

        int diff = 0;
        for (int i = 0; i < a.Length; i++) diff |= a[i] ^ b[i];

        return diff == 0;
    }
}
```

### 5.2 Abrir el sobre CBC+HMAC y leer URL + clave

```csharp
using System;
using System.Security.Cryptography;
using System.Text;
using Newtonsoft.Json.Linq;   // o JavaScriptSerializer / System.Text.Json si lo preferís

public static class NavDataEnvelope
{
    private static readonly byte[] Salt = Encoding.UTF8.GetBytes("vmsopenacars/navdata/v1");
    private static readonly byte[] Aad  = Encoding.UTF8.GetBytes("vmsopenacars/navdata/v1");

    /// <summary>Abre el payload de GET /api/navdata y devuelve (url, key).</summary>
    public static (string Url, string Key, string KeyId, DateTimeOffset ExpiresAt) OpenCbc(
        string base64Payload, string vmsApiKey)
    {
        byte[] blob = Convert.FromBase64String(base64Payload);
        if (blob.Length <= 48) throw new CryptographicException("Sobre NavData demasiado corto");

        byte[] iv         = new byte[16];
        byte[] mac        = new byte[32];
        byte[] ciphertext = new byte[blob.Length - 48];
        Buffer.BlockCopy(blob, 0,  iv,         0, 16);
        Buffer.BlockCopy(blob, 16, ciphertext, 0, ciphertext.Length);
        Buffer.BlockCopy(blob, blob.Length - 32, mac, 0, 32);

        byte[] material = NavDataCrypto.HkdfSha256(
            Encoding.UTF8.GetBytes(vmsApiKey),
            Salt,
            Encoding.UTF8.GetBytes("navdata-api-key-cbc"),
            64);

        byte[] encKey = new byte[32];
        byte[] macKey = new byte[32];
        Buffer.BlockCopy(material, 0,  encKey, 0, 32);
        Buffer.BlockCopy(material, 32, macKey, 0, 32);

        // Encrypt-then-MAC: primero el MAC, con AAD || iv || ciphertext.
        byte[] macInput = new byte[Aad.Length + iv.Length + ciphertext.Length];
        Buffer.BlockCopy(Aad,        0, macInput, 0,                        Aad.Length);
        Buffer.BlockCopy(iv,         0, macInput, Aad.Length,               iv.Length);
        Buffer.BlockCopy(ciphertext, 0, macInput, Aad.Length + iv.Length,   ciphertext.Length);

        using (var hmac = new HMACSHA256(macKey))
        {
            if (!NavDataCrypto.FixedTimeEquals(hmac.ComputeHash(macInput), mac))
                throw new CryptographicException("MAC del sobre NavData invalido");
        }

        string json;
        using (var aes = new AesCryptoServiceProvider())
        {
            aes.KeySize = 256;
            aes.Mode    = CipherMode.CBC;
            aes.Padding = PaddingMode.PKCS7;
            aes.Key     = encKey;
            aes.IV      = iv;

            using var dec = aes.CreateDecryptor();
            byte[] plain = dec.TransformFinalBlock(ciphertext, 0, ciphertext.Length);
            json = Encoding.UTF8.GetString(plain);
        }

        var o = JObject.Parse(json);

        return (o.Value<string>("url"),
                o.Value<string>("key"),
                o.Value<string>("key_id"),
                o.Value<DateTimeOffset>("expires_at"));
    }
}
```

**No hace falta** que construyáis el sobre (eso es solo del servidor): aquí no hay
`TransformFinalBlock` de cifrado ni generación de IV/MAC.

### 5.3 Si preferís GCM en .NET Framework (BouncyCastle)

```csharp
using Org.BouncyCastle.Crypto.Engines;
using Org.BouncyCastle.Crypto.Modes;
using Org.BouncyCastle.Crypto.Parameters;

byte[] key = NavDataCrypto.HkdfSha256(
    Encoding.UTF8.GetBytes(vmsApiKey),
    Salt,
    Encoding.UTF8.GetBytes("navdata-api-key"),
    32);

byte[] blob = Convert.FromBase64String(base64Payload);
byte[] nonce = new byte[12];
byte[] tag   = new byte[16];
byte[] ct    = new byte[blob.Length - 28];
Buffer.BlockCopy(blob, 0,  nonce, 0, 12);
Buffer.BlockCopy(blob, 12, tag,   0, 16);
Buffer.BlockCopy(blob, 28, ct,    0, ct.Length);

// BouncyCastle espera el tag al final de la entrada al descifrar.
byte[] input = new byte[ct.Length + tag.Length];
Buffer.BlockCopy(ct,  0, input, 0,         ct.Length);
Buffer.BlockCopy(tag, 0, input, ct.Length, tag.Length);

var gcm = new GcmBlockCipher(new AesEngine());
gcm.Init(false, new AeadParameters(new KeyParameter(key), 128, nonce, Aad));

byte[] output = new byte[gcm.GetOutputSize(input.Length)];
int len = gcm.ProcessBytes(input, 0, input.Length, output, 0);
len += gcm.DoFinal(output, len);
string json = Encoding.UTF8.GetString(output, 0, len);
```

Si `DoFinal` lanza `InvalidCipherTextException`, el tag no cuadra: sobre manipulado o `vms_api_key`
equivocada. Por eso **recomendamos CBC+HMAC** (§5.2) para vuestra build: es BCL pura, sin paquetes
nuevos ni matices de API.

---

## 6. Caché, rotación y ciclo de vida

1. Al arrancar la sesión (o al autenticar), pedid el sobre **una vez**.
2. Guardadlo en memoria y usad `url` + `key` hasta `expires_at` (6 h por defecto).
3. Antes de cada vuelo: si `expires_at` ya pasó, pedid otro. Si `key_id` cambió, descartad la caché
   de procedimientos (`REFRESH NAVDATA`).
4. Si la respuesta es `503`, el scoring NavData queda desactivado pero **el vuelo sigue con
   normalidad** (igual que hoy sin API key).
5. Nunca escribáis la clave descifrada en `vmsopenacarsexe.config`, logs ni telemetría.

phpVMS registra cada entrega en `activity_log` (`log_name = navdata`) con piloto, IP, `User-Agent`,
cifrado y `key_id`: si alguna vez hay que investigar una filtración, sabemos quién la pidió y cuándo.

Header `User-Agent` sugerido (nos ayuda en la auditoría):
`vmsOpenAcars/<version>`.

---

## 7. Lo que necesitamos de vosotros

1. **Confirmad que abrís el sobre** con las constantes de §4 (nos vale con que nos enviéis la URL y la
   clave recuperadas de vuestro lado para una entrega de prueba).
2. Decidnos si os quedáis en **CBC+HMAC** o preferís GCM con BouncyCastle.
3. Confirmad que podéis dejar `navdata_api_key` **vacío** en el `.config` publicado.
4. Si vuestra build no puede hacer ninguna de las dos cosas, decídmelo y buscamos otro formato: el
   servidor es nuestro, el contrato lo cerramos juntos.

---

## 8. Del lado phpVMS

| Pieza | Dónde |
|---|---|
| Endpoint | `GET /api/navdata` → `App\Http\Controllers\Api\NavDataController` |
| Sobre | `App\Services\NavDataService` (`seal`/`open`, HKDF + GCM/CBC) |
| Config del sobre | `config/vholar.php` → `navdata` (`envelope_ttl`, `kdf_salt`, `kdf_info`, `aad`) |
| Settings | Admin > Settings → `general.navdata_api_url`, `general.navdata_api_key` |
| Errores | `App\Exceptions\NavDataNotConfigured` (503), `NavDataUnsupportedCipher` (400) |
| Tests | `tests/NavDataKeyTest.php` (15 casos: round-trip de ambos sobres, clave ajena, manipulación, 400/401/503, auditoría, vector RFC 5869) |

Los tests descifran **a mano** (sin reutilizar el código del servicio), así que el formato está
congelado: si alguien lo cambia sin actualizar este documento, la suite falla.
