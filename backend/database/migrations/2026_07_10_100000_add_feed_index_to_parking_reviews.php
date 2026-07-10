<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 全駐車場横断「新着口コミ」フィード（/parking/reviews）用の複合index。
 * 公開(is_approved=true)を created_at 降順で引く際の filesort を回避。
 * ショップ側の idx_comment_approved_created に相当。加算的・後方互換。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parking_reviews', function (Blueprint $table) {
            $table->index(['is_approved', 'created_at'], 'idx_parking_review_approved_created');
        });
    }

    public function down(): void
    {
        Schema::table('parking_reviews', function (Blueprint $table) {
            $table->dropIndex('idx_parking_review_approved_created');
        });
    }
};
