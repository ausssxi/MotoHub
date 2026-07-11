<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 車種Q&A の「回答が付いたら通知」購読（購読 × 質問）。
 * 既存 push_subscriptions（購読 × 車種）と同じ設計思想だが、意味を汚さないため別テーブル。
 * 匿名識別は endpoint_hash（= sha256(endpoint)）を流用。新規PIIは保存しない。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_question_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('model_question_id')->constrained('model_questions')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('endpoint');
            $table->string('endpoint_hash', 64);
            $table->string('p256dh');
            $table->string('auth');
            $table->timestamps();

            // 同一デバイス × 同一質問は1件（重複購読防止・updateOrCreateの正本）
            // 明示名: 自動生成名はMySQLの64文字上限を超えるため短縮
            $table->unique(['endpoint_hash', 'model_question_id'], 'pqs_endpoint_question_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_question_subscriptions');
    }
};
