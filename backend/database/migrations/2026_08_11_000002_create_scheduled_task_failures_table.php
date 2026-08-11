<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * スケジュール実行の失敗記録。
 *
 * Illuminate\Console\Events\ScheduledTaskFailed を購読して1行ずつ積む。
 * laravel.log はホスト側 logrotate で7世代しか残らず、書式にも依存するため、
 * 「いつ・どのコマンドが・どの終了コードで落ちたか」は構造化してDBに持つ。
 * ops:daily-report がこのテーブルを日付で集計する。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_task_failures', function (Blueprint $table) {
            $table->id();
            $table->string('command', 255)->comment('artisanコマンド名（例: news:fetch）。クロージャは名前または Closure');
            $table->integer('exit_code')->nullable()->comment('終了コード。取得できない場合はNULL');
            $table->text('output')->nullable()->comment('例外メッセージ（先頭1000文字）');
            $table->timestamp('failed_at')->comment('失敗を検知した時刻');

            // 日次集計（対象日で絞ってコマンド別に数える）用
            $table->index('failed_at');
            $table->index(['command', 'failed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_task_failures');
    }
};
