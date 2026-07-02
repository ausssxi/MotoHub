<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * shops.source: 'scraper'（Webike等の自動収集）| 'user'（ユーザー投稿→承認）。
 * スクレイパーの再分類・一括更新は source='scraper' のみを対象にし、user店を保護する。
 * 既存行は default 'scraper' で自動的に埋まる（バックフィル不要）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->string('source', 20)->default('scraper')->after('shop_type')->index();
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropIndex(['source']);
            $table->dropColumn('source');
        });
    }
};
