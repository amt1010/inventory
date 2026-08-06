<?php

use App\Services\SellerCodeGenerator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sellers', function (Blueprint $table) {
            $table->string('seller_code', 16)->nullable()->after('id');
            $table->string('manufacturing_activity')->nullable()->after('business_address');
            $table->string('availability_hours')->nullable()->after('manufacturing_activity');
            $table->timestamp('password_set_at')->nullable()->after('approved_by');
            $table->dropUnique(['email']);
        });

        $generator = new SellerCodeGenerator();

        foreach (DB::table('sellers')->orderBy('id')->get(['id']) as $seller) {
            DB::table('sellers')->where('id', $seller->id)->update([
                'seller_code' => $generator->generate(),
            ]);
        }

        Schema::table('sellers', function (Blueprint $table) {
            $table->unique('seller_code');
        });
    }

    public function down(): void
    {
        Schema::table('sellers', function (Blueprint $table) {
            $table->dropUnique(['seller_code']);
            $table->dropColumn(['seller_code', 'manufacturing_activity', 'availability_hours', 'password_set_at']);
            $table->unique('email');
        });
    }
};
