<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * driving_schools に住所・座標（地図掲載の土台）を追加する。
 *
 * 既存の city（市区町村）だけでは番地精度のジオコーディングができないため、
 * 番地まで含む address と、緯度経度・ジオコーディングの実行記録を持たせる。
 * すべて nullable で追加し、既存カラム・既存データには一切触れない。
 *
 *  - address        … 番地まで含む住所（city の後段）。ジオコーディング入力。
 *  - latitude       … 緯度（decimal(10,7)）。GSI/Nominatim の結果を格納。
 *  - longitude      … 経度（decimal(10,7)）。
 *  - geocoded_at    … 最後にジオコーディングを実行した時刻。
 *  - geocode_status … ジオコーディング結果の状態（例: ok / not_found / failed 等の文字列）。
 *
 * 地図のビューポート(bbox)絞り込み用に (latitude, longitude) の複合インデックスを張る。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('driving_schools', function (Blueprint $table) {
            $table->string('address', 191)->nullable()->after('city');
            $table->decimal('latitude', 10, 7)->nullable()->after('address');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->timestamp('geocoded_at')->nullable()->after('longitude');
            $table->string('geocode_status', 20)->nullable()->after('geocoded_at');
            $table->index(['latitude', 'longitude'], 'driving_schools_lat_lng_idx');
        });
    }

    public function down(): void
    {
        Schema::table('driving_schools', function (Blueprint $table) {
            $table->dropIndex('driving_schools_lat_lng_idx');
            $table->dropColumn(['address', 'latitude', 'longitude', 'geocoded_at', 'geocode_status']);
        });
    }
};
