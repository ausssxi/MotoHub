<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * shops.name_normalized: 店名検索・重複判定用の正規化済み店名（ShopNameNormalizer 出力）。
 * Eloquent保存時に自動セット。スクレイパー（SQLAlchemy）経由の行は NULL で入るため
 * shops:normalize-names コマンド（日次スケジュール）でバックフィルする。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->string('name_normalized', 255)->nullable()->after('name')->index();
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropIndex(['name_normalized']);
            $table->dropColumn('name_normalized');
        });
    }
};
