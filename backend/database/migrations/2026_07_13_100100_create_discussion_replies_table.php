<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * スレッドへの返信。is_official=MotoHub公式（AI必答含む）、source=ai/human で出所を明示。
 * answer_generated_at: MotoHub必答の生成完了時刻（null=「回答準備中」プレースホルダ／冪等キー）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discussion_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('discussion_thread_id')->constrained('discussion_threads')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('nickname', 50)->nullable();
            $table->text('body');
            $table->string('submitter_ip_hash', 64)->nullable();
            $table->boolean('is_official')->default(false); // MotoHub公式（人を装わない・明示ラベル）
            $table->string('source', 20)->nullable();        // ai / human
            $table->string('status', 20)->default('published');
            $table->timestamp('answer_generated_at')->nullable(); // 必答生成完了（null=準備中）
            $table->unsignedInteger('helpful_count')->default(0);
            $table->timestamps();

            $table->index(['discussion_thread_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discussion_replies');
    }
};
