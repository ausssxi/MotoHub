<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * pois に geocode_failed_at（ジオコーディング失敗時刻）を持たせる。
 *
 * poi:geocode は毎日 orderBy 無しで先頭5000件を対象にしており、住所が取れない同じ行を
 * 毎日叩き続けていた（成果は1日10件程度・残り3万件に未到達）。失敗を記録して既定では
 * 再試行しないようにするための列。nullable（未失敗の行は NULL のまま）。既存カラムには触れない。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pois', function (Blueprint $table) {
            $table->timestamp('geocode_failed_at')->nullable()->after('municipality_code')->comment('ジオコーディング失敗時刻（NULL=未失敗）');
        });
    }

    public function down(): void
    {
        Schema::table('pois', function (Blueprint $table) {
            $table->dropColumn('geocode_failed_at');
        });
    }
};
