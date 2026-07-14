<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * casual投稿（「ひとこと」）を本文だけで投げられるよう title を任意化する。
 * 既存レコードは全て title 有り（従来必須）のため後方互換に問題なし。
 * 質問(type=question)は FAQPage schema/SEO のため Request 側で required_if を維持する。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('discussion_threads', function (Blueprint $table) {
            $table->string('title', 120)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('discussion_threads', function (Blueprint $table) {
            $table->string('title', 120)->nullable(false)->change();
        });
    }
};
