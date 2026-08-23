<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 行政区域ポリゴン本体（1市区町村が複数ポリゴンを持つため municipalities と 1:N で分離）。
 *
 * 【SRID は 0 で固定】本番MySQLで実測済み:
 *   - ST_GeomFromGeoJSON(json, 1, 0) は座標を「経度・緯度」のまま保持する
 *   - SRID 4326 を指定すると MySQL が「緯度・経度」の順に入れ替え、問い合わせ側と食い違う
 *   - SRID 0 かつ ST_Contains(geom, POINT(経度, 緯度)) で命中を確認済み
 * → geom 列は必ず SRID 0。4326 は使わない。
 *
 * SRID 0 の列指定と SPATIAL INDEX（対象列は NOT NULL 必須）はスキーマビルダで表現しづらいため、
 * 生SQLで作成する。down() は DROP TABLE で確実に落とす。
 */
return new class extends Migration
{
    public function up(): void
    {
        // SQLite は GEOMETRY 型・SPATIAL INDEX・SRID 指定に非対応。テスト等でテーブルが
        // 存在するよう geom を binary で持つ最小同等テーブルを作る（空間クエリは MySQL 専用）。
        if (DB::connection()->getDriverName() !== 'mysql') {
            Schema::create('municipality_polygons', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->char('code', 5)->index();
                $table->binary('geom');
            });

            return;
        }

        DB::statement(<<<'SQL'
            CREATE TABLE `municipality_polygons` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `code` CHAR(5) NOT NULL,
                `geom` GEOMETRY NOT NULL SRID 0,
                PRIMARY KEY (`id`),
                INDEX `municipality_polygons_code_index` (`code`),
                SPATIAL INDEX `municipality_polygons_geom_spatialindex` (`geom`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('municipality_polygons');
    }
};
