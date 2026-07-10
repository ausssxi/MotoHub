<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 車種Q&A の回答（質問にぶら下がる2階層目）。ログイン不要・即反映・キルスイッチ。
 * helpful_count = 「参考になった」（回答者のモチベーション装置・投稿の代替ではない）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('model_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('model_question_id')->constrained('model_questions')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('nickname', 50)->nullable();
            $table->text('body');                            // 回答本文（必須）
            $table->string('submitter_ip_hash', 64)->nullable();
            $table->boolean('is_approved')->default(true);
            $table->unsignedInteger('helpful_count')->default(0);
            $table->timestamps();

            $table->index(['model_question_id', 'is_approved', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('model_answers');
    }
};
