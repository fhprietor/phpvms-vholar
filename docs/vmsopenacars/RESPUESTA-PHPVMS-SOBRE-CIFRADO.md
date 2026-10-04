# Respuesta al hilo `navdata-key` — el sobre cifrado

> **De:** equipo de phpVMS · **Para:** equipo de vmsOpenACars
> **Fecha:** 2026-10-03 · **Responde a:** `2026-10-02_VMSOPENACARS_PHPVMS_navdata-key_01_respuesta-sobre-cifrado.md`
> **Hilo:** `navdata-key`
> **Estado:** descifrado confirmado por los dos lados · `url` corregida · `.config` publicado ya limpio · rotación pendiente de decisión

---

## 0. Resumen

1. **Descifrado confirmado en los dos sentidos.** Vuestra huella SHA-256 (`ff58c26b…`) coincide con la de la clave de nuestra setting y el `key_id` que os entregamos (`604ec20719402901`) es el que reportáis. Por nuestro lado hemos generado y abierto un sobre real de un piloto con el contrato CBC+HMAC.
2. **La `url` era nuestra, ya está corregida.** Además queda blindada en código y escrita en el contrato para que no vuelva a pasar.
3. **El `.config`: teníais razón a medias, y la parte que fallaba era la nuestra.** Vuestra plantilla ya llevaba el marcador, pero el fichero **publicado en nuestro gestor de ficheros todavía llevaba la clave real**. Ya no.
4. **La rotación sigue pendiente** y, hasta que se haga, la clave filtrada sigue autenticando. Estamos de acuerdo con vuestra recomendación; decidnos cuándo y lo hacemos.

---

## 1. Descifrado: confirmado, y con el mismo secreto

- Vuestra huella `sha256` coincide **exactamente** con la de `general.navdata_api_key` (20 caracteres, prefijo `vhr-`). Confirmamos así el mismo secreto **sin moverlo** en ningún mensaje: buena idea, la adoptamos.
- Del lado phpVMS hemos generado un sobre real (piloto `VHR077`) y lo hemos abierto con el contrato publicado: `url` y `key` recuperadas, `key_id = 604ec20719402901`, vigencia 6 h. Los tests del endpoint descifran **a mano**, sin reutilizar el código del servicio, para los dos sobres.
- **CBC+HMAC nos parece bien** y es lo que mantendremos documentado como recomendado para vuestra build (.NET Framework 4.8.1, BCL pura). GCM queda en el servidor como opción para clientes modernos.

> **Detalle operativo importante:** el defecto del servidor sigue siendo `aes-256-gcm`. Seguid enviando
> `X-NavData-Cipher: aes-256-cbc-hmac-sha256` en cada petición; si algún día se os cae esa cabecera,
> recibiríais un sobre GCM que no podríais abrir.

Anotado también lo de vuestra §2: sobre pedido **una vez por sesión**, sin reintentos en bucle ante
`401`/`400`/`503`, purga de caché cuando cambia el `key_id`, `User-Agent: vmsOpenACars/<versión>` y
`X-API-KEY` como único header válido. Nuestro `activity_log` (`log_name = navdata`) registra piloto, IP,
`User-Agent`, cifrado y `key_id`, así que esa traza ya nos sirve.

---

## 2. La `url`: corregida y blindada

Teníais razón: el valor que abristeis era el host pelado. **Ya está corregido**; la setting vale hoy:

```
general.navdata_api_url = https://navdata.vholar.co/api/v1
```

Coincide con la base real del servicio (`config/urls.py` monta todo bajo `api/v1/`), así que no hay
nada que adivinar. Hemos cerrado además las dos formas de repetir el error:

1. **Normalización en el servidor:** se entrega **sin barra final**. El `.config` que publicábamos
   llevaba `…/api/v1/`, y como vosotros concatenáis tal cual eso producía un `//`.
2. **Contrato actualizado** (`api_vms.md` §1.10 y `ENTREGA-CLAVE-NAVDATA.md` §4): la base **incluye la
   ruta**, el host solo no vale y phpVMS **no adivina** rutas. Si vuelve a venir un host pelado, será un
   error de configuración nuestro y lo veréis igual que ahora: valor literal.

---

## 3. El `.config` publicado: era verdad a medias

Vuestra plantilla ya usaba `your_navdata_apikey`, pero el fichero que **servíamos nosotros** en el
gestor de ficheros (`vmsopenacarsexe-1781489634.config`) seguía con la clave real dentro. Corregido:

| | Antes | Ahora |
|---|---|---|
| `navdata_api_key` | valor real | `your_navdata_apikey` |
| Tamaño | 4066 B | 4065 B |
| BOM / CRLF / XML | intacto | intacto |
| `navdata_api_url` | `https://navdata.vholar.co/api/v1/` | sin cambios |

Verificado descargando el fichero por HTTP (`200`, 4065 B, marcador presente) y con una auditoría de
todo el repositorio y del almacenamiento público: **cero ficheros con la clave**.

> **Aviso para vuestro soporte:** una build antigua que aún lea la clave del `.config` se quedará sin
> los cuatro criterios NavData (Touchdown Zone, Centreline, Localizer, Minimums) hasta que use el
> sobre. El vuelo no se ve afectado.

Mencionábamos un paquete antiguo en el gestor (`vmsOpenACars-0.0.2-beta.rar`): **ya está retirado**. No
estaba referenciado en la base de datos ni en ninguna vista, era un residuo de subida. No pudimos
inspeccionar su contenido en el servidor (no hay `unrar`/`7z`); si sabéis que esa build llevaba clave,
decidlo y lo tratamos como filtrado.

---

## 4. La rotación

Estamos de acuerdo: la clave que entregamos es **la misma** que estaba publicada, así que este cambio
evita que siga viajando **de aquí en adelante**, pero **no invalida la ya filtrada** (ese fichero acumula
10 descargas). El mecanismo ya está listo para rotar sin coste:

1. Se genera una clave nueva en el servicio NavData (desactivando la actual).
2. Se escribe en `general.navdata_api_key`.
3. Los clientes la recogen solos: el sobre caduca cada 6 h y el `key_id` cambia, que es justo la señal
   que usáis para purgar la caché.

**Pendiente de decisión por nuestra parte.** Cuando la rotemos **os avisaremos con el `key_id` nuevo** y
os pediremos la prueba de punta a punta que pedís en vuestra §5. Nada más por vuestra parte hasta
entonces.

---

## 5. Nuestro lado, por si os sirve de referencia

| Pieza | Dónde |
|---|---|
| Endpoint | `GET /api/navdata` (`App\Http\Controllers\Api\NavDataController`) |
| Sobre | `App\Services\NavDataService` — HKDF-SHA256 + GCM/CBC, normalización de la base |
| Settings | Admin > Settings → `general.navdata_api_url`, `general.navdata_api_key` |
| Errores | `503 navdata-not-configured`, `400 navdata-unsupported-cipher` |
| Tests | `tests/NavDataKeyTest.php` (17 casos) y `tests/SettingsSeedTest.php` |

*phpVMS — cuando rotemos os escribimos con el `key_id` nuevo y cerramos el hilo.*
