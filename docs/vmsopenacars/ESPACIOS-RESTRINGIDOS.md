# Espacios restringidos: contrato para el cliente ACARS

Fecha: 2026-10-27. Estado: implementado en el servidor (Vholar). El cliente solo tiene que
enviar la marca; **no** debe enviar `state`.

## Que tiene que enviar el cliente

Al presentar el PIREP (`POST /api/pireps/{id}`), en el objeto `fields`:

```json
{
  "fields": {
    "restricted_airspace": "SKEZ"
  }
}
```

- El nombre del campo puede ser `restricted_airspace` (recomendado), `restricted_zone` o
  `airspace_violation`.
- El valor es libre: la zona, el identificador del espacio o una descripcion corta
  (`"SKEZ"`, `"SKCG zona de exclusion"`, `"1"` si no hay mas detalle).
- Un valor vacio o `0` **no** cuenta como violacion (permite enviar el campo siempre y
  marcarlo solo cuando ocurra).
- El servidor guarda el campo como valor de PIREP con origen `ACARS` y lo pinta en el detalle.

Opcional, para el revisor humano: mandar tambien el texto en `notes`
(`"Violacion de espacio restringido: SKEZ a FL340"`). El servidor anade ademas su propia
linea a `notes`.

## Que NO tiene que enviar

- **`state`**: se ignora. El servidor pone `IN_PROGRESS` al prefilar y `PENDING` al
  presentar. No existe un estado "en revision": `PENDING` ya significa "esperando
  aprobacion del admin".
- No hace falta crear el campo antes en el admin: cualquier nombre que llegue en `fields` se
  guarda como valor del PIREP.

## Que hace el servidor

1. Detecta la marca al presentar (lee los campos que llegan en la misma peticion).
2. **Anula la auto-aprobacion del rango**: el PIREP queda en `state = 1` (PENDING) aunque el
   rango del piloto tenga `auto_approve_acars`, que hoy es el caso de todos los rangos.
3. Anade a `notes`: `[Revision obligatoria] Espacio restringido: <valor>`.
4. El detalle del PIREP muestra un aviso amarillo "Revision obligatoria".
5. El staff lo revisa y acepta o rechaza con comentario; el piloto ve el comentario en el
   detalle.

## Como comprobarlo

1. Prefilar un vuelo y presentarlo con `fields.restricted_airspace = "SKEZ"`.
2. El PIREP debe quedar **pendiente** (no aceptado) y con la linea en las notas.
3. Repetir sin el campo: debe auto-aprobarse como siempre.

Pruebas automaticas en el servidor: `tests/AirspaceReviewTest.php` (marca -> pendiente;
limpio -> sin aviso; `0`/vacio -> sin aviso).
