<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * rental_garages.prefecture を nullable にする。
 *
 * 政令市・23区などで住所が市区名から始まり都道府県が解決できない物件を、
 * 座標埋め（rental_garage:geocode）まで含めて取りこぼさず保存できるようにするため。
 * doctrine/dbal 依存を避け、生の ALTER TABLE ... MODIFY で変更する。
 * 既存マイグレーション（作成時）は本番適用済みのため編集せず、本ファイルを追加する。
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE `rental_garages` MODIFY `prefecture` VARCHAR(10) NULL');
    }

    public function down(): void
    {
        // 元は NOT NULL。戻す前に null 行が残っていると失敗するため、必要なら先に補完すること。
        DB::statement('ALTER TABLE `rental_garages` MODIFY `prefecture` VARCHAR(10) NOT NULL');
    }
};
