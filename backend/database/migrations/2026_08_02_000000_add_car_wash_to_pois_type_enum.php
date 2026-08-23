<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * pois.type の enum に 'car_wash'（洗車場）を追加する。
 *
 * enum の変更は doctrine/dbal 依存を避けるため change() を使わず、
 * 生の ALTER TABLE ... MODIFY COLUMN で行う。既存データ（3種）はそのまま。
 */
return new class extends Migration
{
    public function up(): void
    {
        // SQLite には ENUM 制約が無く type 列は自由な文字列を許すため MODIFY 自体が不要（no-op）。
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }
        DB::statement("ALTER TABLE `pois` MODIFY COLUMN `type` ENUM('gas_station', 'convenience_store', 'michi_no_eki', 'car_wash') NOT NULL");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }
        // 既存3種へ戻す（car_wash 行が残っていると失敗するため、先に該当行を削除/移行しておくこと）。
        DB::statement("ALTER TABLE `pois` MODIFY COLUMN `type` ENUM('gas_station', 'convenience_store', 'michi_no_eki') NOT NULL");
    }
};
