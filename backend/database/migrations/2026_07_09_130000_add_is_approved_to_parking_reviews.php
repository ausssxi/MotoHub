<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 駐車場レビューの公開状態（キルスイッチ）。既定 true＝即反映。
 * - 通報対応で Filament から個別非公開にできる（is_approved=false）。
 * - 新規ユーザー投稿駐車場（自作スポット＋自演レビュー対策）は投稿時に false で保存。
 * 既存行は default true で公開のまま（今表示中のレビューが消えない）。加算的・後方互換。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parking_reviews', function (Blueprint $table) {
            $table->boolean('is_approved')->default(true)->after('user_id');
            $table->index(['bike_parking_id', 'is_approved']);
        });
    }

    public function down(): void
    {
        Schema::table('parking_reviews', function (Blueprint $table) {
            $table->dropIndex(['bike_parking_id', 'is_approved']);
            $table->dropColumn('is_approved');
        });
    }
};
