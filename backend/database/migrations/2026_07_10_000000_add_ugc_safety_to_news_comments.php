<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ニュースコメントの UGC 安全弁＋ログイン不要化の土台（4面目）。
 * - user_id を nullable 化（ゲスト投稿を許可）。既存の紐付けは維持。
 * - nickname: ゲストの表示名（本名は絶対に使わない）。
 * - submitter_ip_hash: sha256(ip|app.key)・生IP非保存（連投/濫用の単位）。
 * - is_approved: 公開状態（即反映＝true・通報対応のキルスイッチ）。既存行は default true で公開維持。
 * 加算的・後方互換（is_approved 以外の列は nullable）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news_comments', function (Blueprint $table) {
            $table->string('nickname', 50)->nullable()->after('user_id');
            $table->string('submitter_ip_hash', 64)->nullable()->after('nickname');
            $table->boolean('is_approved')->default(true)->after('body');
            $table->index(['news_id', 'is_approved']);
        });

        // user_id をゲスト投稿のため nullable へ（FK/cascade は維持）。
        Schema::table('news_comments', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('news_comments', function (Blueprint $table) {
            $table->dropIndex(['news_id', 'is_approved']);
            $table->dropColumn(['nickname', 'submitter_ip_hash', 'is_approved']);
        });
    }
};
