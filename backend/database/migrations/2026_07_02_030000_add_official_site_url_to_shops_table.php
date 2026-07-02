<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * shops.official_site_url: 店の「公式サイトURL」の正。
 * ⚠️ スクレイパーが書き込む website_url（Webike店舗ページ等）とは別物。
 *    このカラムはユーザー投稿由来のみ。スクレイパーは書き込まない
 *    （scraper/common/database.py の Shop ORM に敢えて含めていない）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->string('official_site_url', 200)->nullable()->after('website_url');
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn('official_site_url');
        });
    }
};
