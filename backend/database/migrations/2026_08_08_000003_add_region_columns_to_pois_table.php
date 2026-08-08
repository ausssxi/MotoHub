<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * pois に行政区域（都道府県・市区町村・市区町村コード）を持たせる。
 *
 * 座標 → municipality_polygons の ST_Contains 判定で解決した結果を書き戻すための列。
 * すべて nullable（未判定の行は NULL のまま）。既存カラムには一切手を触れない。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pois', function (Blueprint $table) {
            $table->string('prefecture', 10)->nullable()->after('address')->comment('座標から判定した都道府県');
            $table->string('city', 50)->nullable()->after('prefecture')->comment('座標から判定した市区町村');
            $table->char('municipality_code', 5)->nullable()->after('city')->comment('全国地方公共団体コード5桁');

            $table->index('municipality_code');
        });
    }

    public function down(): void
    {
        Schema::table('pois', function (Blueprint $table) {
            $table->dropIndex(['municipality_code']);
            $table->dropColumn(['prefecture', 'city', 'municipality_code']);
        });
    }
};
