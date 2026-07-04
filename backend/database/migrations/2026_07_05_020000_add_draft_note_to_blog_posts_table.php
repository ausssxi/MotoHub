<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * blog_posts.draft_note: 機械生成の下書きに付ける管理用フラグ注記（管理画面限定表示）。
 * 本文(body)をマーカーで汚さず、消し忘れ公開でも一般表示に痕跡が残らないようにする。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->string('draft_note', 255)->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->dropColumn('draft_note');
        });
    }
};
