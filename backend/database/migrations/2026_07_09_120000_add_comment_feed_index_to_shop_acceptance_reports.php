<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 全店横断「新着口コミ」フィード用の複合index。
 * comment_approved=true を created_at 降順で引くクエリの filesort を回避する。
 * 加算的・後方互換（既存挙動に影響なし）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_acceptance_reports', function (Blueprint $table) {
            $table->index(['comment_approved', 'created_at'], 'idx_comment_approved_created');
        });
    }

    public function down(): void
    {
        Schema::table('shop_acceptance_reports', function (Blueprint $table) {
            $table->dropIndex('idx_comment_approved_created');
        });
    }
};
