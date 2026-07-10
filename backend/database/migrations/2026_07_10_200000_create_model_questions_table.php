<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 車種Q&A（価格コム型）の質問。UGC 5面目。既存の型（即反映＋通報＋NGワード＋honeypot＋
 * IPハッシュ＋throttle＋キルスイッチ）を適用。ログイン不要（user_id nullable）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('model_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bike_model_id')->constrained('bike_models')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('nickname', 50)->nullable();     // ゲスト表示名（本名は使わない）
            $table->string('title', 120);                   // 質問の要旨（必須）
            $table->text('body')->nullable();               // 詳細（任意）
            $table->string('submitter_ip_hash', 64)->nullable(); // sha256(ip|app.key)・生IP非保存
            $table->boolean('is_approved')->default(true);  // 即反映・キルスイッチ
            $table->timestamps();

            $table->index(['bike_model_id', 'is_approved', 'created_at']); // 車種ページの新着順
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('model_questions');
    }
};
