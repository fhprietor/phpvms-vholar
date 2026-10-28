<?php

namespace App\Services;

use App\Contracts\Service;
use App\Models\Pirep;

/**
 * Revision obligatoria por espacio restringido.
 *
 * El cliente ACARS marca la incidencia en los CAMPOS PERSONALIZADOS del PIREP (`fields` de la
 * API, que acaban en `pirep_field_values`); nunca en `state`, que lo fija el servidor. Si
 * aparece alguno de los campos de abajo, el PIREP no se auto-aprueba aunque el rango del
 * piloto tenga `auto_approve_acars`: queda en PENDING para que el staff lo revise.
 *
 *   restricted_airspace  (recomendado: el valor es la zona, p.ej. "SKEZ" o "SKCG exclusion")
 *   restricted_zone      (alternativo)
 *   airspace_violation   (alternativo; "1" o texto)
 *
 * El campo es la señal para la maquina; el texto para el revisor va en `notes`.
 */
class AirspaceReviewService extends Service
{
    /** Nombres de campo que disparan la revision (en minusculas). */
    public const FIELDS = ['restricted_airspace', 'restricted_zone', 'airspace_violation'];

    /**
     * Devuelve la zona/motivo si el PIREP pide revision por espacio restringido, o null.
     *
     * `$pending` son los campos que llegan en la MISMA peticion: en PirepService::file() la
     * decision del estado se toma antes de guardarlos, asi que hay que mirarlos tambien.
     *
     * @param array<int, mixed> $pending [{name, value, source}, ...] o PirepFieldValue[]
     */
    public function violationFor(Pirep $pirep, array $pending = []): ?string
    {
        foreach ($pending as $field) {
            $violation = $this->match($this->nameOf($field), $this->valueOf($field));

            if ($violation !== null) {
                return $violation;
            }
        }

        // Se consulta fresco sobre `field_values` (la relacion): `$pirep->fields` es un accessor
        // que mezcla definiciones y valores con posible cache.
        foreach ($pirep->field_values()->get() as $value) {
            $violation = $this->match((string) $value->name, (string) $value->value);

            if ($violation !== null) {
                return $violation;
            }
        }

        return null;
    }

    /**
     * @param mixed $field
     */
    private function nameOf($field): string
    {
        return is_array($field) ? (string) ($field['name'] ?? '') : (string) ($field->name ?? '');
    }

    /**
     * @param mixed $field
     */
    private function valueOf($field): string
    {
        return is_array($field) ? (string) ($field['value'] ?? '') : (string) ($field->value ?? '');
    }

    private function match(string $name, string $value): ?string
    {
        if (!in_array(strtolower($name), self::FIELDS, true)) {
            return null;
        }

        $text = trim($value);

        if ($text === '' || $text === '0') {
            return null;
        }

        return $text === '1' ? 'espacio restringido' : $text;
    }

    /**
     * El PIREP debe quedar en revision.
     */
    public function requiresReview(Pirep $pirep): bool
    {
        return $this->violationFor($pirep) !== null;
    }

    /**
     * Linea que se anade a las notas del PIREP para que el revisor sepa por que esta ahi.
     */
    public function noteLine(string $violation): string
    {
        return '[Revision obligatoria] Espacio restringido: '.$violation;
    }
}
