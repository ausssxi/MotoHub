<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $correct = [
            'engine_type' => '水冷4ストローク・4気筒DOHC4バルブ',
            'max_power'   => '41(56PS)／11000',
            'max_torque'  => '39(4.0kg･m)／9500',
        ];

        DB::table('bike_models')
            ->whereIn('id', [61, 64])
            ->update($correct);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 文字化けデータに戻す必要はないため空
    }
};
