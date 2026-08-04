<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * roadside_stations.latitude / longitude を nullable にする。
 *
 * 国交省公式一覧の新規道の駅は所在地が市町村止まりで、投入時点では座標が未確定なため
 * NULL を許容する（後日ジオコーディングで埋める）。doctrine/dbal 依存を避け生の
 * DB::statement で行う（2026_08_04_000000_make_pois_name_nullable.php と同方式）。
 *
 * インデックス非影響: latitude/longitude は index(['latitude','longitude']) に含まれるが
 * （create_roadside_stations_table.php:42）、カラムの NULL 許容化はインデックスを削除・再作成
 * しない。MySQL の B-Tree インデックスは NULL 値も格納できるため、複合インデックスは維持される。
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE `roadside_stations` MODIFY `latitude` DECIMAL(10,7) NULL, MODIFY `longitude` DECIMAL(10,7) NULL');
    }

    public function down(): void
    {
        // 座標 NULL の行は本マイグレーション適用後にしか存在しえない（適用前は NOT NULL だったため）。
        // よって NOT NULL へ戻す前に NULL 行を削除するのが正しい巻き戻し（元状態＝座標必須へ復元）。
        DB::statement('DELETE FROM `roadside_stations` WHERE `latitude` IS NULL OR `longitude` IS NULL');
        DB::statement('ALTER TABLE `roadside_stations` MODIFY `latitude` DECIMAL(10,7) NOT NULL, MODIFY `longitude` DECIMAL(10,7) NOT NULL');
    }
};
