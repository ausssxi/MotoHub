<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * pois に洗車場（car_wash）の設備フラグ2列を追加する（「セルフ」「洗車機」バッジ用）。
 *
 * OSM の生の値（yes / no / only / fixme 等）をそのまま保持するため boolean にせず varchar。
 * 洗車場以外（gas_station / convenience_store）では通常 NULL。すべて nullable。
 * pois.type の enum は変更しない。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pois', function (Blueprint $table) {
            $table->string('self_service', 10)->nullable()->after('opening_hours')->comment('OSM self_service の生値（yes/no/only/fixme 等）');
            $table->string('automated', 10)->nullable()->after('self_service')->comment('OSM automated の生値（yes/no 等）');
        });
    }

    public function down(): void
    {
        Schema::table('pois', function (Blueprint $table) {
            $table->dropColumn(['self_service', 'automated']);
        });
    }
};
