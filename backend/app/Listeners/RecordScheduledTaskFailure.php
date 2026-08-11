<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Support\ScheduledTaskFailureLog;
use Illuminate\Console\Events\ScheduledTaskFailed;

/**
 * スケジュール実行の失敗を scheduled_task_failures へ記録する。
 *
 * ログを grep するのではなくフレームワークのイベントを購読するので、ログ書式に依存せず、
 * 登録済みのスケジュールをこの1クラスで捕捉できる。
 *
 * ただし ->runInBackground() を付けたスケジュールにはこのイベントが飛ばない。
 * Laravel は ScheduleRunCommand で
 *   if ($event->exitCode != 0 && ! $event->runInBackground) { throw ... }
 * としており、バックグラウンド実行の異常終了は例外にならないため。
 * 該当の3件（youtube:refresh / parts:refresh / news:refresh）は routes/console.php で
 * ->onFailure() を付け、同じ ScheduledTaskFailureLog を呼んで同じ形の行を作っている。
 *
 * 記録処理そのものは App\Support\ScheduledTaskFailureLog に置き、
 * イベント経由と onFailure 経由で同じ知識を2箇所に書かないようにしている。
 */
final class RecordScheduledTaskFailure
{
    public function handle(ScheduledTaskFailed $event): void
    {
        ScheduledTaskFailureLog::recordEvent($event->task, $event->exception->getMessage());
    }
}
