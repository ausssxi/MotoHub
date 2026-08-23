<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * rental_garages.geocode_status の enum に 'source' を追加。
 * スクレイパー（JSON-LD 等）が権威ある座標を提供したレコードを表し、
 * 以降のジオコーディング（GSI/Nominatim）や代表点降格の対象外にするための状態。
 *
 * doctrine/dbal 依存を避け生の ALTER TABLE ... MODIFY で行う（既存マイグレーションは編集しない）。
 */
return new class extends Migration
{
    public function up(): void
    {
        // SQLite には ENUM 制約が無く geocode_status は自由な文字列を許すため MODIFY 不要（no-op）。
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }
        DB::statement("ALTER TABLE `rental_garages` MODIFY `geocode_status` ENUM('pending', 'ok', 'failed', 'out_of_range', 'approximate', 'source') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }
        // 'source' の行が残っていると失敗するため、必要なら先に 'ok' 等へ移すこと。
        DB::statement("ALTER TABLE `rental_garages` MODIFY `geocode_status` ENUM('pending', 'ok', 'failed', 'out_of_range', 'approximate') NOT NULL DEFAULT 'pending'");
    }
};
