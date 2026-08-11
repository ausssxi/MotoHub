<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\ScheduledTaskFailure;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * スケジュール失敗を scheduled_task_failures へ記録する処理の共通実装。
 *
 * 呼び出し元は2つあり、どちらも同じ形の行を作る:
 *   1. App\Listeners\RecordScheduledTaskFailure
 *      … ScheduledTaskFailed イベント経由（通常のスケジュール）
 *   2. routes/console.php の ->onFailure()
 *      … ->runInBackground() を付けた3件。Laravel は ScheduleRunCommand で
 *        `if ($event->exitCode != 0 && ! $event->runInBackground)` と条件を付けており、
 *        バックグラウンド実行では ScheduledTaskFailed が飛ばないため、
 *        schedule:finish 経由で呼ばれる onFailure から記録する。
 *
 * コマンド名の正規化（describe）もここに置き、2箇所で同じキーになるようにする。
 */
final class ScheduledTaskFailureLog
{
    /** output 列へ入れるメッセージの最大長 */
    private const MESSAGE_LIMIT = 1000;

    /**
     * スケジュールイベントから記録する。コマンド名・終了コードはイベントから取る。
     *
     * $message は例外メッセージ。onFailure 経由では例外が存在しない（単に終了コードが
     * 非0なだけ）ため null を渡す。その場合 output は NULL のまま記録する。
     */
    public static function recordEvent(ScheduledEvent $task, ?string $message = null): void
    {
        self::record(self::describe($task), $task->exitCode, $message);
    }

    /**
     * 記録本体。DBが落ちている等で書けない場合でもスケジューラは止めず、ログへ退避する。
     */
    public static function record(string $command, ?int $exitCode, ?string $message = null): void
    {
        try {
            ScheduledTaskFailure::create([
                'command' => $command,
                'exit_code' => $exitCode,
                'output' => $message === null ? null : Str::limit($message, self::MESSAGE_LIMIT, '…'),
                'failed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('スケジュール失敗の記録に失敗しました', [
                'command' => $command,
                'exit_code' => $exitCode,
                'original_error' => $message,
                'record_error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 集計しやすいコマンド名に整える。
     *
     * $task->command は実際には
     *   '/usr/bin/php8.3' 'artisan' news:fetch >> '/path/to/logs/poi.log' 2>&1
     * のような完全なシェル文字列なので、リダイレクトと php/artisan の前置きを落とす。
     * Schedule::call() のクロージャは command を持たないため名前（->name()）を使う。
     */
    public static function describe(ScheduledEvent $task): string
    {
        $command = (string) ($task->command ?? '');

        if ($command === '') {
            return $task->description !== null && $task->description !== ''
                ? $task->description
                : 'Closure';
        }

        // appendOutputTo/sendOutputTo 由来のリダイレクト以降を落とす
        $command = preg_replace('/\s*\d?>>?\s.*$/', '', $command) ?? $command;

        // "'/usr/bin/php8.3' 'artisan' news:fetch --foo" → "news:fetch --foo"
        if (preg_match("/artisan'?\s+(.*)$/", $command, $m) === 1) {
            $command = $m[1];
        }

        $command = trim($command);

        return $command !== '' ? Str::limit($command, 255, '') : 'Unknown';
    }
}
