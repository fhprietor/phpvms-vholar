<?php

namespace App\Http\Resources;

use App\Contracts\Resource;
use App\Contracts\Unit;

/**
 * ACARS table but only include the fields for the routes
 * Class AcarsRoute
 */
class AcarsRoute extends Resource
{
    /**
     * Attributes cast to a Unit object (App\Contracts\Unit). The numeric value is
     * held in a protected property, so a plain json_encode of them only sends the
     * unit names. They get expanded into unit => value pairs.
     */
    private const UNIT_FIELDS = ['distance', 'fuel'];

    public function toArray($request)
    {
        return $this->expandUnitFields(parent::toArray($request));
    }

    /**
     * Expand the unit fields of a single row, or of every row of a collection
     */
    private function expandUnitFields(array $data): array
    {
        if (array_is_list($data)) {
            return array_map(fn (array $row) => $this->expandUnitFields($row), $data);
        }

        foreach (self::UNIT_FIELDS as $field) {
            if (($data[$field] ?? null) instanceof Unit) {
                $data[$field] = $data[$field]->getResponseUnits();
            }
        }

        return $data;
    }
}
