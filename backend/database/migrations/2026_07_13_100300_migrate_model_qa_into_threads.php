<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 既存の車種Q&A（model_questions / model_answers）を統合スレッドへロスなく種火移行。
 * 質問→thread(type=question, is_seed=true)、回答→reply(source=human)。承認状態は status へ写像。
 * 旧テーブルは残す（ダウンタイム回避・ロールバック余地）。二重移行を避けるため既に移行済みなら何もしない。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('model_questions') || ! Schema::hasTable('discussion_threads')) {
            return;
        }
        // 既に種火が入っていれば冪等スキップ
        if (DB::table('discussion_threads')->where('is_seed', true)->exists()) {
            return;
        }

        $status = fn ($approved) => $approved ? 'published' : 'hidden';

        DB::table('model_questions')->orderBy('id')->chunkById(200, function ($questions) use ($status) {
            foreach ($questions as $q) {
                $threadId = DB::table('discussion_threads')->insertGetId([
                    'bike_model_id' => $q->bike_model_id,
                    'user_id' => $q->user_id,
                    'type' => 'question',
                    'nickname' => $q->nickname,
                    'title' => $q->title,
                    'body' => $q->body,
                    'submitter_ip_hash' => $q->submitter_ip_hash,
                    'status' => $status($q->is_approved),
                    'is_seed' => true,
                    'created_at' => $q->created_at,
                    'updated_at' => $q->updated_at,
                ]);

                $answers = DB::table('model_answers')->where('model_question_id', $q->id)->orderBy('id')->get();
                foreach ($answers as $a) {
                    DB::table('discussion_replies')->insert([
                        'discussion_thread_id' => $threadId,
                        'user_id' => $a->user_id,
                        'nickname' => $a->nickname,
                        'body' => $a->body,
                        'submitter_ip_hash' => $a->submitter_ip_hash,
                        'is_official' => false,
                        'source' => 'human',
                        'status' => $status($a->is_approved),
                        'answer_generated_at' => null,
                        'helpful_count' => $a->helpful_count ?? 0,
                        'created_at' => $a->created_at,
                        'updated_at' => $a->updated_at,
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        // 種火として入れた分のみ撤去（返信はFKカスケードで消える）
        if (Schema::hasTable('discussion_threads')) {
            DB::table('discussion_threads')->where('is_seed', true)->delete();
        }
    }
};
