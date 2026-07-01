<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ユーザー投稿の「受け入れ情報」（他店購入車OK 等のポジティブ情報）。
 *
 * スクレイパー(shops.service_tags)とは完全に別テーブル。scraper 側には本テーブルの
 * モデルが存在しないため、Webike再クロールが触れることは構造的に無い。
 * 評価・星・ネガティブ項目は一切持たない（名誉毀損・業務妨害リスク回避）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_acceptance_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();

            // ポジティブな受け入れフラグ（固定4種）
            $table->boolean('accepts_other_store')->default(false); // 他店購入車OK
            $table->boolean('accepts_bring_in')->default(false);    // 持ち込みOK
            $table->boolean('pickup_service')->default(false);      // 引き取り対応
            $table->boolean('walk_in_ok')->default(false);          // 飛び込みOK

            $table->string('comment', 120)->nullable();             // 任意・ポジティブ前提

            // 承認フロー（既定は保留＝掲載前）。Review.is_approved を踏襲するが default は false
            $table->boolean('is_approved')->default(false);

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); // 匿名可
            $table->string('submitter_ip_hash', 64)->nullable();    // sha256（生IPは保存しない）

            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['shop_id', 'is_approved']); // 表示・集計用
            $table->index('is_approved');              // 承認キュー用
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_acceptance_reports');
    }
};
