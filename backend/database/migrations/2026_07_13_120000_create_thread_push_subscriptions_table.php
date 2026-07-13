<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 統合スレッドの「返信が付いたら通知」購読（旧Q&Aの push を threads へ繋ぎ替え）。
 * 匿名識別は endpoint_hash 流用。既存の push_question_subscriptions（旧question紐付）を、
 * ③のQ&A→スレ移行と同じ対応(bike_model_id+title+created_at)で該当スレへ写して既存購読者を迷子にしない。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('thread_push_subscriptions')) {
            Schema::create('thread_push_subscriptions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('discussion_thread_id')->constrained('discussion_threads')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('endpoint');
                $table->string('endpoint_hash', 64);
                $table->string('p256dh');
                $table->string('auth');
                $table->timestamps();

                $table->unique(['endpoint_hash', 'discussion_thread_id'], 'tps_endpoint_thread_unique');
            });
        }

        // 既存Q&A購読の移行マッピング（旧question→移行済み seed thread）。
        if (! Schema::hasTable('push_question_subscriptions') || ! Schema::hasTable('model_questions')) {
            return;
        }
        DB::table('push_question_subscriptions')->whereNotNull('model_question_id')->orderBy('id')
            ->chunkById(200, function ($subs) {
                foreach ($subs as $s) {
                    $mq = DB::table('model_questions')->where('id', $s->model_question_id)->first();
                    if (! $mq) {
                        continue;
                    }
                    $threadId = DB::table('discussion_threads')->where('is_seed', true)
                        ->where('bike_model_id', $mq->bike_model_id)
                        ->where('title', $mq->title)
                        ->where('created_at', $mq->created_at)
                        ->value('id');
                    if (! $threadId) {
                        continue;
                    }
                    DB::table('thread_push_subscriptions')->updateOrInsert(
                        ['endpoint_hash' => $s->endpoint_hash, 'discussion_thread_id' => $threadId],
                        ['endpoint' => $s->endpoint, 'p256dh' => $s->p256dh, 'auth' => $s->auth, 'user_id' => $s->user_id, 'created_at' => now(), 'updated_at' => now()],
                    );
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('thread_push_subscriptions');
    }
};
