<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * rental_garages.geocode_status の enum に 'approximate'（市区町村どまりの代表点＝地図非表示）を追加。
 *
 * doctrine/dbal 依存を避けるため生の ALTER TABLE ... MODIFY で行う（pois の car_wash 追加と同手法）。
 * 既存マイグレーションは編集せず本ファイルを追加する（本番適用済みのため）。
 */
return new class extends Migration
{
    public function up(): void
    {
        // SQLite には ENUM 制約が無く geocode_status は自由な文字列を許すため MODIFY 不要（no-op）。
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }
        DB::statement("ALTER TABLE `rental_garages` MODIFY `geocode_status` ENUM('pending', 'ok', 'failed', 'out_of_range', 'approximate') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }
        // 'approximate' の行が残っていると失敗するため、必要なら先に 'failed' 等へ移すこと。
        DB::statement("ALTER TABLE `rental_garages` MODIFY `geocode_status` ENUM('pending', 'ok', 'failed', 'out_of_range') NOT NULL DEFAULT 'pending'");
    }
};
