<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class QuoteNumberGenerator
{
    public function generate(): string
    {
        $now = now();
        $minuteKey = $now->format('ymdHi');

        $sequence = DB::transaction(function () use ($minuteKey) {
            $row = DB::table('quote_number_sequences')
                ->where('minute_key', $minuteKey)
                ->lockForUpdate()
                ->first();

            if ($row) {
                $next = $row->sequence + 1;
                DB::table('quote_number_sequences')
                    ->where('minute_key', $minuteKey)
                    ->update(['sequence' => $next, 'updated_at' => now()]);

                return $next;
            }

            DB::table('quote_number_sequences')->insert([
                'minute_key' => $minuteKey,
                'sequence' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return 1;
        });

        return $minuteKey.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
