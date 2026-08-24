<?php

namespace App\Support;

class MaterialType
{
    /**
     * Maps a free-text "Raw Material" / "Finished Good(s)" cell (case-
     * insensitive, tolerant of a trailing "s") to Product::material_type's
     * two real column values. Returns null for anything unrecognized --
     * callers decide whether that's a hard failure or a default.
     */
    public static function normalize(?string $value): ?string
    {
        $normalized = mb_strtolower(trim((string) $value));

        return match (true) {
            $normalized === 'raw material' => 'raw_material',
            in_array($normalized, ['finished good', 'finished goods'], true) => 'finished_good',
            default => null,
        };
    }
}
