<?php

namespace App\Validation;

use App\Models\District;
use App\Models\Province;
use App\Support\Text;

/**
 * Normalises workplace input before validation.
 *
 * İl / ilçe may arrive as ids (form dropdowns) or as text (manual entry, Excel). Text that
 * matches a known province / district is converted to its id; otherwise it is kept as the
 * manually entered name, as the spec allows.
 */
final class WorkplaceInput
{
    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function normalize(array $input): array
    {
        $input = array_map(fn ($value) => is_string($value) ? (trim($value) === '' ? null : trim($value)) : $value, $input);

        if (empty($input['province_id']) && ! empty($input['province_name']) && is_string($input['province_name'])) {
            $province = self::findProvince($input['province_name']);

            if ($province !== null) {
                $input['province_id'] = $province->id;
            }
        }

        if (! empty($input['province_id'])) {
            $input['province_name'] = null;

            if (empty($input['district_id']) && ! empty($input['district_name']) && is_string($input['district_name'])) {
                $district = self::findDistrict((int) $input['province_id'], $input['district_name']);

                if ($district !== null) {
                    $input['district_id'] = $district->id;
                }
            }
        }

        if (! empty($input['district_id'])) {
            $input['district_name'] = null;
        }

        if (array_key_exists('has_union', $input) && $input['has_union'] === null) {
            $input['has_union'] = false;
        }

        return $input;
    }

    private static function findProvince(string $name): ?Province
    {
        if (ctype_digit($name)) {
            return Province::find((int) $name);
        }

        $key = Text::key($name);

        return Province::all()->first(fn (Province $province) => Text::key($province->name) === $key);
    }

    private static function findDistrict(int $provinceId, string $name): ?District
    {
        $key = Text::key($name);

        return District::query()
            ->where('province_id', $provinceId)
            ->get()
            ->first(fn (District $district) => Text::key($district->name) === $key);
    }
}
