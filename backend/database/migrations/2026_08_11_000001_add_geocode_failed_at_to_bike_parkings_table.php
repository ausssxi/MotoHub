<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * bike_parkings に geocode_failed_at（ジオコーディング失敗時刻）を持たせる。
 *
 * parking:geocode を Nominatim から国土地理院（GsiGeocodingService）へ統一するのに合わせ、
 * poi:geocode と同じ失敗記録方式を導入する。失敗行に時刻を刻み、既定ではその行を再試行しない
 * （--retry-failed 指定時のみ対象）。nullable（未失敗の行は NULL）。既存カラムには触れない。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bike_parkings', function (Blueprint $table) {
            $table->timestamp('geocode_failed_at')->nullable()->after('longitude')->comment('ジオコーディング失敗時刻（NULL=未失敗）');
        });
    }

    public function down(): void
    {
        Schema::table('bike_parkings', function (Blueprint $table) {
            $table->dropColumn('geocode_failed_at');
        });
    }
};
