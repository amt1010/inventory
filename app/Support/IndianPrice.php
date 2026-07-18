<?php

namespace App\Support;

class IndianPrice
{
    /**
     * Normalise a free-text price string: prefix every number group with the
     * Indian Rupee symbol and apply Indian digit grouping (e.g. 1,00,000),
     * while leaving ranges, words, and separators untouched.
     *
     * "1200 - 1800 per reel"  → "₹1,200 - ₹1,800 per reel"
     * "₹100000"               → "₹1,00,000"
     */
    public static function format(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return $value;
        }

        // Match each number group (optionally already carrying a ₹ and commas)
        // so re-formatting is idempotent and never doubles the symbol.
        return preg_replace_callback('/(?:₹\s*)?\d[\d,]*/u', function (array $match): string {
            $digits = preg_replace('/\D/', '', $match[0]);

            if ($digits === '') {
                return $match[0];
            }

            return '₹'.self::group($digits);
        }, $value);
    }

    /**
     * Group a run of digits in the Indian system: the last three digits, then
     * two at a time (1,00,000 rather than 100,000).
     */
    private static function group(string $digits): string
    {
        if (strlen($digits) <= 3) {
            return $digits;
        }

        $last3 = substr($digits, -3);
        $rest = substr($digits, 0, -3);
        $rest = preg_replace('/\B(?=(\d\d)+(?!\d))/', ',', $rest);

        return $rest.','.$last3;
    }
}
