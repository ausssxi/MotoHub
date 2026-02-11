<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bike_model_market_stats', function (Blueprint $table) {
            // 計算済みの分布（ヒストグラム）データをJSONで保存するカラムを追加
            $table->json('distribution_data')->nullable()->after('listing_count')->comment('計算済みの分布データ');
        });
    }

    public function down(): void
    {
        Schema::table('bike_model_market_stats', function (Blueprint $table) {
            $table->dropColumn('distribution_data');
        });
    }
};