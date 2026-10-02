# Operación · Permisos de ficheros

Este documento cubre **dos incidentes** del 2 de octubre de 2026, ambos por la misma causa de fondo —un fichero creado por un usuario distinto de `www-data` con permisos demasiado restrictivos— pero en dos direcciones opuestas:

| | Incidente 1 | Incidente 2 |
|---|---|---|
| Hora | 01:46 UTC | 02:09 → 02:22 UTC |
| Qué fallaba | **escritura** | **lectura** |
| Síntoma | 6 respuestas **500** | tema visualmente roto (dropdowns transparentes) |
| Fichero | `storage/framework/views/*.php` | `public/assets/themes/vholar/css/tokens.css` |
| Modo | `664` con grupo `frank` | `600` (sin lectura para nadie más) |

**Regla:** un fichero que crea un humano o una herramienta debe quedar en **`664` con grupo `www-data`**. Ni `600` (nadie más lo lee) ni con grupo `frank` (el servidor no escribe).

---

# Incidente 1 · Escritura · `storage/framework/views`

**2 de octubre de 2026, 01:46 UTC** — la web devolvió **6 respuestas 500** en un minuto.

```
ErrorException
file_put_contents(/var/www/phpvms/storage/framework/views/c0c8383729ff55486171044558d47496.php):
Failed to open stream: Permission denied
```

---

## 1. Esquema de permisos correcto

El proyecto tiene un script canónico, `/usr/local/bin/phpvms-fix-permissions.sh`:

```bash
chmod -R 775 storage/framework/
chmod -R 777 storage/framework/cache/
chmod -R 775 storage/logs/
chmod -R 775 bootstrap/cache/

chown -R frank:www-data storage/ bootstrap/cache/
```

**Lo importante es `frank:www-data`**: el propietario es `frank` y **el grupo es `www-data`**, con modo 664/775. El servidor web corre como `www-data` (php-fpm8.3 y nginx), y escribe **por el bit de grupo**.

## 2. Causa raíz

### La cadena que produjo el 500

1. **01:35** — se ejecutó `php artisan view:cache` **como el usuario `frank`**. Compiló las 597 vistas y las dejó como **`frank:frank 664`**.
2. El grupo era `frank`, no `www-data`, así que para `www-data` esos archivos caían en la clase "otros" = **solo lectura**.
3. **01:44** — se siguieron editando `.blade.php`. Laravel detecta la fuente más nueva que el compilado y **recompila**: intenta sobrescribir el archivo y falla.
4. **01:46** — las primeras peticiones que tocan esas vistas devuelven 500.

Laravel **puede crear** archivos nuevos (el directorio es 777), pero **no puede sobrescribir** los existentes si no tiene permiso de escritura sobre el archivo. Por eso solo fallaban las vistas ya compiladas.

### Por qué el grupo salió mal

`storage/logs/` tiene el bit **setgid** (`drwxrwsr-x`): cualquier archivo creado ahí hereda automáticamente el grupo `www-data`, aunque lo cree `frank`.

`storage/framework/views/` **no lo tiene** (`drwxrwxrwx`). Sin setgid, un archivo creado por `frank` se queda con el grupo primario de `frank`. Ese es el defecto de fondo.

### Por qué no se autocorrigió

El script de permisos se ejecutaba periódicamente (cada 6 h, según `storage/logs/permissions.log`). **Su última entrada es del 31 de marzo de 2026** — dejó de correr hace ~6 meses. Ya no está en `/etc/cron.d`, ni en `/etc/cron.hourly`/`daily`, ni hay un timer de systemd. Sin él, las propiedades de grupo se fueron degradando hasta romper.

## 3. Lo que se hizo (2 de octubre, 01:47)

Como no hay `sudo` disponible (`no new privileges`), no se pudo usar `chown`. Pero la cuenta pertenece al grupo `www-data`, así que basta con cambiar el **grupo**:

```bash
find storage bootstrap/cache -user frank -exec chgrp www-data {} +
chmod 775 storage/framework/testing
```

Esto reproduce exactamente el `chown -R frank:www-data` del script canónico, sin necesidad de root. **Sin borrar archivos y sin cerrar ninguna sesión.**

Resultado verificado:

| Comprobación | Resultado |
|---|---|
| Archivos no escribibles por `www-data` bajo `storage/` y `bootstrap/cache/` | **0** |
| Directorios no escribibles por `www-data` | **0** |
| Archivos con grupo `frank` (la causa del fallo) | **0** |
| Peticiones 500 tras el arreglo | **0** (11× 200 a las 01:47, 4× a las 01:48, 7× a las 01:49) |

## 4. Arreglos duraderos (requieren root)

**a. Poner el bit setgid** — es la corrección de fondo, evita que el grupo vuelva a degradarse:

```bash
sudo chmod g+s storage/framework storage/framework/views \
                  storage/framework/sessions storage/framework/cache \
                  storage/framework/testing bootstrap/cache
```

A partir de ahí, cualquier archivo que se cree en esas carpetas —lo cree quien lo cree— hereda el grupo `www-data`.

**b. Restaurar el cron de permisos** (raíz del problema actual, lleva 6 meses caído):

```bash
sudo crontab -e
# añadir:
0 */6 * * * /usr/local/bin/phpvms-fix-permissions.sh
```

**c. No ejecutar `artisan` como `frank`.** Para cualquier comando de Laravel:

```bash
sudo -u www-data php artisan <comando>
```

Si en algún momento se ejecuta como `frank`, volver a aplicar el `chgrp` del punto 3.

## 5. Diagnóstico rápido

```bash
# ¿Hay archivos con el grupo equivocado?
find storage bootstrap/cache -group frank | wc -l

# ¿Cuáles NO puede escribir www-data?
find storage bootstrap/cache -type f ! -name '.gitignore' \
  ! \( -perm -u+w -user www-data -o -perm -g+w -group www-data -o -perm -o+w \)

# ¿Sigue vivo el cron de permisos?
tail -3 storage/logs/permissions.log

# ¿Hubo 500? (el sitio es vholar.co y registra en el log general de nginx)
awk '{split($4,a,":"); if(a[2]":"a[3]>="01:44") print a[2]":"a[3], $9}' \
  /var/log/nginx/access.log | sort | uniq -c
```

Cuidado al verificar permisos: un archivo **sí** es escribible por `www-data` si es su propietario (caso `www-data:www-data 644`), aunque el bit de grupo esté apagado. Comprobar solo el bit de grupo da falsos positivos.

## 6. Nota

`storage/framework/testing/` figuraba como `frank:frank 700`. No afecta a la web (solo lo usan las pruebas de PHPUnit), pero se alineó a `775 frank:www-data` por consistencia.

---

# Incidente 2 · Lectura · `tokens.css`

**2 de octubre de 2026, 02:09 → 02:22 UTC.** La home mostró los **menús desplegables del header transparentes**, invisibles sobre el carrusel de imágenes.

## 1. Causa

El fichero `public/assets/themes/vholar/css/tokens.css` —creado en esa misma sesión— quedó con modo **`600`**:

```
-rw------- frank:frank   tokens.css     ← nginx (www-data) no puede leerlo → HTTP 403
-rw-rw-r-- frank:frank   theme.css      ← el bit de "otros" lo salva → HTTP 200
```

Como `tokens.css` no se servía, **ninguna custom property estaba definida**. Y un `var(--token)` sin definir y sin fallback **invalida la declaración completa**: para `background-color`, el valor calculado pasa a ser su valor inicial, que es **`transparent`**.

De ahí el síntoma exacto:

```css
.dropdown-menu { background-color: var(--vh-surface-2) !important; }   /* → transparent */
```

Un menú desplegable sobre un fondo de página oscuro se disimula; sobre las **imágenes del carrusel**, se ve claramente roto.

## 2. Por qué no se detectó antes

Las verificaciones de la fase 6 comprobaron que el fichero **existía**, que los **tokens estaban declarados** y que las **referencias resolvían** — todo a nivel de contenido. Ninguna comprobación pidió el fichero **por HTTP**. El 403 solo aparece al servirlo, y el servidor corre como otro usuario.

**Un fichero nuevo bajo `public/` no está verificado hasta que nginx lo devuelve con un 200.**

## 3. Arreglo

```bash
chmod 664 public/assets/themes/vholar/css/tokens.css
chgrp www-data public/assets/themes/vholar/css/tokens.css
```

Y se auditó **todo el proyecto** con la comprobación de lectura del punto 5. Aparecieron **21 ficheros más** con el mismo defecto, creados en sesiones anteriores:

| Fichero | Gravedad |
|---|---|
| `modules/VmsOpenOps/Support/OpsPricing.php` | **Alta.** PHP que se carga en tiempo de ejecución; lo usan 7 sitios (cálculo de coste de jumpseat y ferry). Si opcache no lo tuviera cacheado, esas páginas darían error fatal. |
| `modules/VmsOpenOps/Database/Migrations/*.php` | Media (solo al migrar) |
| `app/Database/migrations/2026_09_*.php` ×2 | Media (solo al migrar) |
| `deploy/versions.yml`, `tests/DeployManifestTest.php` | Baja |
| 14 `README.md` y documentos en `docs/` | Ninguna (no se sirven) |

Los 21 se alinearon a `664 frank:www-data`.

## 4. Cómo se sirve realmente este sitio

```
/etc/nginx/sites-available/phpvms  →  server_name vholar.co 192.168.20.233
                                      root /var/www/phpvms/public
```

`public/assets/themes/vholar/css/` es un **symlink** a `resources/views/layouts/vholar/css/`, así que editar el fichero en `resources/` lo cambia en `public/`. No son dos copias.

Para comprobar cualquier página o recurso **como lo hace el navegador** (nginx responde a `vholar.co` en localhost):

```bash
curl -s -H "Host: vholar.co" -o /dev/null -w "%{http_code}\n" \
  http://127.0.0.1/assets/themes/vholar/css/tokens.css
```

## 5. Diagnóstico rápido

```bash
# ¿Qué ficheros del proyecto NO puede LEER www-data?  (excluye storage, ya cubierto arriba)
find . -type f ! -path './storage/*' ! -path './node_modules/*' ! -path './.git/*' \
  ! \( -perm -u+r -user www-data -o -perm -g+r -group www-data -o -perm -o+r \)

# ¿Todos los recursos de una página responden 200?
curl -s -H "Host: vholar.co" http://127.0.0.1/ -o /tmp/home.html
grep -oE '(href|src)="[^"]*"' /tmp/home.html | sed 's/.*="//;s/"$//' \
  | grep -E '^/|vholar\.co/' | sed 's|^https\?://vholar\.co||' | sort -u \
  | while read u; do printf "%s  %s\n" \
      "$(curl -s -H 'Host: vholar.co' -o /dev/null -w '%{http_code}' "http://127.0.0.1$u")" "$u"; done

# ¿Hubo 403? nginx los registra en el mismo access.log
grep ' 403 ' /var/log/nginx/access.log | tail
```

**Cuidado con la simetría de los dos casos:** para **escribir**, un fichero `www-data:www-data 644` sirve (es el propietario), pero `frank:www-data 664` también. Para **leer**, basta con que se cumpla *cualquiera* de los tres bits (`u+r` si es suyo, `g+r` si comparte grupo, `o+r`). Comprobar solo un bit da falsos positivos en ambos sentidos.

---

# Anexo · El problema sigue teniendo una fuente activa

Durante la sesión del 2 de octubre, **mientras se arreglaban los dos incidentes**, aparecieron ficheros nuevos con el grupo equivocado:

```
02:28  frank:frank       tests/TestCase.php
02:28  frank:frank       tests/TestCaseIsolationTest.php
02:28  frank:frank       storage/framework/sessions/zwQHdLnjpOFXz9OIFgMBYKz0BI2HYpfKsS5PNus2
02:30  frank:frank       .phpunit.cache/test-results
02:31  frank:frank       CHANGELOG.md
02:31  frank:frank       deploy/README.md
```

Ninguno lo creó esta sesión. Lo que indican en conjunto —un `TestCase` nuevo, un test de aislamiento, caché de PHPUnit y el fichero de sesión de esa ejecución— es que **hay otro proceso corriendo PHPUnit (y editando ficheros) como el usuario `frank` en este mismo directorio**.

Cada ejecución de pruebas que arranque Laravel **crea un fichero de sesión como `frank:frank`**. Si el identificador de sesión de un piloto real coincide con uno de esos ficheros, el servidor no podrá escribirlo y esa petición fallará — es exactamente el incidente 1.

**Por eso el arreglo duradero no es cosmético.** El bit setgid de la sección 4 no solo evita que se degrade la propiedad: hace que *cualquier* proceso que escriba en esas carpetas —`www-data`, `frank`, PHPUnit o el que sea— cree ficheros con el grupo `www-data`, y por tanto utilizables por el servidor web.

Mientras eso no se aplique, conviene saber que el problema reaparecerá por sí solo. La comprobación de la sección 5 sirve para detectarlo en segundos:

```bash
find storage bootstrap/cache -type f ! -name '.gitignore' \
  ! \( -perm -u+w -user www-data -o -perm -g+w -group www-data -o -perm -o+w \)
```


