<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 車種ページの統合スレッド型クチコミ（質問/雑談/カスタム/整備を1つの器で受ける）。
 * 既存 model_questions を種火として移行する（type=question, is_seed=true）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discussion_threads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bike_model_id')->constrained('bike_models')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 20)->default('question'); // question / chat / custom / maintenance
            $table->string('nickname', 50)->nullable();       // ゲスト表示名（本名は保存しない）
            $table->string('title', 120);
            $table->text('body')->nullable();
            $table->string('submitter_ip_hash', 64)->nullable(); // sha256(ip|app.key)・生IP非保存
            $table->string('status', 20)->default('published');  // published / pending / hidden（キルスイッチ）
            $table->boolean('is_seed')->default(false);          // Q&A移行の種火
            $table->timestamps();

            $table->index(['bike_model_id', 'status', 'created_at']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discussion_threads');
    }
};
