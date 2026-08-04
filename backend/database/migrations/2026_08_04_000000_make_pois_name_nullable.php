<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * pois.name を nullable にする。
 *
 * OSM に name タグが無い POI（洗車場・一部GS等）でセンチネル '名称不明' を保存するのをやめ、
 * name = null を許容して表示側フォールバックに委ねるため。
 * doctrine/dbal 依存を避け、生の ALTER TABLE ... MODIFY で行う（既存の enum 変更と同方式）。
 *
 * インデックス非影響: name は unique(['osm_id','type']) にも index(['type','latitude','longitude']) にも
 * 含まれないため、MODIFY で null 許容にしても既存インデックスは変更されない。
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE `pois` MODIFY `name` VARCHAR(255) NULL');
    }

    public function down(): void
    {
        // NULL が残ったまま NOT NULL に戻すと失敗するため、先に空文字へ移す（順序必須）。
        DB::statement("UPDATE `pois` SET `name` = '' WHERE `name` IS NULL");
        DB::statement('ALTER TABLE `pois` MODIFY `name` VARCHAR(255) NOT NULL');
    }
};
