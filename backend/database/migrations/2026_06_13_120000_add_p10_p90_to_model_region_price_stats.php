<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 全国行に p10/p90（total_price 円）を追加。heterogeneity guard 用。
 * p90/p10 が大きすぎる（＝現行とヴィンテージ等が混在するバケット）モデルは
 * 地域価格を一切主張しない（getForModel が空を返す）。'全国'以外の行は null。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('model_region_price_stats', function (Blueprint $table) {
            $table->decimal('p10', 12, 0)->nullable()->after('median_price')->comment('total_price の下位10%（全国行のみ）');
            $table->decimal('p90', 12, 0)->nullable()->after('p10')->comment('total_price の上位10%（全国行のみ）');
        });
    }

    public function down(): void
    {
        Schema::table('model_region_price_stats', function (Blueprint $table) {
            $table->dropColumn(['p10', 'p90']);
        });
    }
};
