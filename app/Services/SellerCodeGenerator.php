<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class SellerCodeGenerator
{
    public function generate(): string
    {
        $now = now();
        $minuteKey = $now->format('ymdHi');

        $sequence = DB::transaction(function () use ($minuteKey) {
            $row = DB::table('seller_code_sequences')
                ->where('minute_key', $minuteKey)
                ->lockForUpdate()
                ->first();

            if ($row) {
                $next = $row->sequence + 1;
                DB::table('seller_code_sequences')
                    ->where('minute_key', $minuteKey)
                    ->update(['sequence' => $next, 'updated_at' => now()]);

                return $next;
            }

            DB::table('seller_code_sequences')->insert([
                'minute_key' => $minuteKey,
                'sequence' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return 1;
        });

        return $minuteKey.'S'.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
    }
}
