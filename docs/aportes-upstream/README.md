# Aportes a los proyectos originales

Propuestas **preparadas y verificadas** para devolver a upstream lo que hemos
arreglado o necesitamos aquí. **No se ha enviado nada todavía**: son parches
aplicables (`git am`) más el texto del PR.

## Contenido

| Carpeta | Qué hay |
|---|---|
| `phpvms/` | 4 parches para `phpvms/phpvms` (base `7.0.10`) + `PR-DESCRIPTIONS.md` |
| `disposable/` | 2 parches de Bootstrap 5 para los módulos de FatihKoz + `PR-DESCRIPTIONS.md` |
| `referencia/` | Informe de los 8 defectos de `VmsOpenOps` (módulo **propio**, ya arreglados; no tiene upstream) |

## Cómo enviarlos

### phpVMS — `releases/7.0` para 7.x (`main` ya es 8.0.0-dev)

```bash
git clone https://github.com/<tu-usuario>/phpvms.git /tmp/pr && cd /tmp/pr
git remote add upstream https://github.com/phpvms/phpvms.git
git fetch upstream --tags
git checkout -b fix/acars-logs-endpoint 7.0.10      # una rama por parche
git am <ruta>/phpvms/0001-*.patch
git push -u origin fix/acars-logs-endpoint          # y abrir el PR
```

Los cuatro son independientes: repite el `checkout`/`am` por parche y pega el
texto correspondiente de `phpvms/PR-DESCRIPTIONS.md`.

### Disposable (FatihKoz)

Los PRs van contra `main` de cada módulo y **sólo tocan vistas** (`data-bs-*`):

```bash
git clone https://github.com/<tu-usuario>/DisposableBasic.git /tmp/dbasic && cd /tmp/dbasic
git checkout -b fix/bootstrap5-data-attributes f0b03db5bef7a5b7ea08e93ef07e3e86d9f3cbfd
git am <ruta>/disposable/0001-*.patch
```

Lo mismo con `DisposableSpecial` y `0002-*.patch` sobre su base
`d1d776cea9d4e6453dad99e59b18f3f3a9ddd153` (también en la cabecera del parche).

> **Licencia**: las licencias de Disposable y CHJumpSeat prohíben que *nosotros*
> redistribuyamos el código. Enviar un PR **al repositorio del autor** no es
> redistribución nuestra y es la vía limpia; además deja de obligarnos a mantener
> parches locales (`patches/`).

## Verificación hecha aquí

- Los 4 parches de phpVMS se generaron en un **clon limpio** de `7.0.10`: aplican
  con `git apply --check`, sólo tocan los ficheros listados y no llevan cambios de
  modo. Con los cuatro aplicados la suite pasa: `OK (211 tests, 1184 assertions)`.
- Los 2 parches de Disposable aplican sobre su base. El de `DisposableBasic`,
  aplicado a `f0b03db`, reproduce **exactamente** nuestro módulo vendorizado.

## Descartado tras comprobarlo (para no perder el tiempo)

- **`$position['type']` en ACARS**: upstream ya lo fuerza desde 7.0.10.
- **Inferencia de diversión por `alt_airport_id`**: upstream exige el campo
  `diversion-airport`; nuestro añadido es política de la VA.
- **Comentario del admin antes de `changeState()`**: upstream separa `status()` de
  `comments()`, así que el flujo combinado es específico de Vholar.
- **CARTO con API key**: upstream no usa `cartocdn`; es elección de nuestro tema.
- **`0 = deshabilitado` en el cron de PIREPs**: el default de upstream es 12 y el
  caso 0 es discutible (como mucho, un issue de UX).

## Estado

| Aporte | Estado |
|---|---|
| 4 PRs a phpVMS | preparados y verificados, **sin enviar** |
| 2 PRs a Disposable (Bootstrap 5) | preparados y verificados, **sin enviar** |
| Informe de VmsOpenOps | documento interno (ese módulo no tiene upstream) |
