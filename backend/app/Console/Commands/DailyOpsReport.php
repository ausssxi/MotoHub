<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ScheduledTaskFailure;
use App\Support\DiskUsage;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * 前日の異常サマリを1通にまとめて通知する。
 *
 * 「laravel.log には記録されているが誰も見ない」状態を解消するのが目的なので、
 * 異常が無い日も必ず送る（届かないこと自体を異常のサインにするため）。
 *
 * 通知手段は system:check-disk / backup:monitor と同じ管理者向けメール
 * （Mail::raw + config('backup.notifications.mail.to')）。本番に常駐 queue worker が
 * 無いため送信は必ず同期で行う（queue は使わない）。
 */
final class DailyOpsReport extends Command
{
    protected $signature = 'ops:daily-report
        {--date= : 対象日（Y-m-d。既定は前日）}
        {--dry-run : 通知を送らず内容を表示するだけ}';

    protected $description = '前日のスケジュール失敗・ERROR/WARNING・ディスク使用率をまとめて管理者へ通知する';

    /** 「異常あり」と判定するディスク使用率（system:check-disk の既定しきい値に合わせる） */
    private const DISK_THRESHOLD = 85;

    /** 種別の上位いくつを本文に載せるか */
    private const TOP_KINDS = 5;

    /** 種別としてまとめる際のメッセージ最大長 */
    private const KIND_LENGTH = 120;

    public function handle(): int
    {
        try {
            $dateOption = (string) ($this->option('date') ?? '');
            $date = $dateOption !== ''
                ? Carbon::parse($dateOption)->startOfDay()
                : Carbon::yesterday()->startOfDay();
        } catch (\Throwable $e) {
            $this->error('--date の書式が不正です（Y-m-d で指定してください）: '.$e->getMessage());

            return self::FAILURE;
        }

        $failures = $this->collectScheduleFailures($date);
        $logs = $this->scanLogs($date);
        $disk = DiskUsage::current();

        $abnormalReasons = $this->abnormalReasons($failures, $logs, $disk);
        $subject = sprintf(
            '【MotoHub】日次サマリ %s %s',
            $date->toDateString(),
            $abnormalReasons === [] ? '異常なし' : '異常あり（'.implode(' / ', $abnormalReasons).'）'
        );
        $body = $this->buildBody($date, $failures, $logs, $disk);

        if ($this->option('dry-run')) {
            $this->line($subject);
            $this->newLine();
            $this->line($body);
            $this->newLine();
            $this->info('[DRY RUN] 通知は送信していません。');

            return self::SUCCESS;
        }

        $to = config('backup.notifications.mail.to');

        try {
            // 必ず同期送信。queue()/Mail::queue は使わない（常駐 queue worker が無く永久未送信になる）。
            Mail::raw($body, function ($message) use ($to, $subject): void {
                $message->to($to)->subject($subject);
            });
            $this->info("日次サマリを送信しました: {$to}");
            $this->line($subject);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            Log::error('[ops:daily-report] メール送信に失敗しました', ['error' => $e->getMessage()]);
            $this->error('メール送信に失敗しました: '.$e->getMessage());

            // 通知が飛ばないこと自体が異常なので異常終了する。
            // これ自体もスケジュール失敗として記録され、翌日のサマリに載る。
            return self::FAILURE;
        }
    }

    /**
     * 対象日のスケジュール失敗をコマンド別に集計する。
     *
     * @return list<array{command: string, count: int, last_failed_at: string}>
     */
    private function collectScheduleFailures(Carbon $date): array
    {
        $rows = ScheduledTaskFailure::query()
            ->whereBetween('failed_at', [$date->copy()->startOfDay(), $date->copy()->endOfDay()])
            ->selectRaw('command, COUNT(*) as failure_count, MAX(failed_at) as last_failed_at')
            ->groupBy('command')
            ->orderByDesc('failure_count')
            ->get();

        return $rows->map(fn ($row): array => [
            'command' => (string) $row->command,
            'count' => (int) $row->failure_count,
            'last_failed_at' => (string) $row->last_failed_at,
        ])->all();
    }

    /**
     * laravel.log（ローテート済み・gz圧縮済みを含む）から対象日の ERROR / WARNING を集計する。
     *
     * @return array{ERROR: array{count: int, kinds: array<string, int>}, WARNING: array{count: int, kinds: array<string, int>}, files: list<string>}
     */
    private function scanLogs(Carbon $date): array
    {
        $prefix = '['.$date->toDateString();
        $counts = ['ERROR' => 0, 'WARNING' => 0];
        $kinds = ['ERROR' => [], 'WARNING' => []];
        $scanned = [];

        foreach ($this->logFiles($date) as $file) {
            $scanned[] = basename($file);

            foreach ($this->readLines($file) as $line) {
                // 対象日の「行頭がタイムスタンプ」の行だけを見る（スタックトレース等は自動的に除外される）
                if (! str_starts_with($line, $prefix)) {
                    continue;
                }

                if (preg_match('/^\[[^\]]+\]\s+[\w:-]+\.(ERROR|WARNING):\s*(.*)$/u', $line, $m) !== 1) {
                    continue;
                }

                $level = $m[1];
                $counts[$level]++;

                $kind = $this->normalizeMessage($m[2]);
                $kinds[$level][$kind] = ($kinds[$level][$kind] ?? 0) + 1;
            }
        }

        foreach ($kinds as $level => $byKind) {
            arsort($byKind);
            $kinds[$level] = array_slice($byKind, 0, self::TOP_KINDS, true);
        }

        return [
            'ERROR' => ['count' => $counts['ERROR'], 'kinds' => $kinds['ERROR']],
            'WARNING' => ['count' => $counts['WARNING'], 'kinds' => $kinds['WARNING']],
            'files' => $scanned,
        ];
    }

    /**
     * 対象日の行を含み得るログファイル一覧。
     *
     * ホスト側 logrotate は laravel.log / laravel.log.1 / laravel.log.2.gz … の形式で
     * 7世代保持する。daily チャンネルへ切り替えた場合の laravel-YYYY-MM-DD.log も拾う。
     * 対象日の行を含むファイルは必ず対象日の開始以降に書き込まれているので、
     * 更新時刻でそれ以前のファイルを落として無駄読みを避ける。
     *
     * @return list<string>
     */
    private function logFiles(Carbon $date): array
    {
        $dir = storage_path('logs');
        $files = array_merge(
            glob($dir.'/laravel.log*') ?: [],
            glob($dir.'/laravel-*.log*') ?: [],
        );

        $since = $date->copy()->startOfDay()->getTimestamp();

        $files = array_filter(array_unique($files), static function (string $file) use ($since): bool {
            if (! is_file($file) || ! is_readable($file)) {
                return false;
            }

            $mtime = @filemtime($file);

            return $mtime !== false && $mtime >= $since;
        });

        sort($files);

        return array_values($files);
    }

    /**
     * ログを1行ずつ読む。.gz はそのまま展開して読む（zlib はPHP同梱。追加ライブラリではない）。
     *
     * @return \Generator<int, string>
     */
    private function readLines(string $file): \Generator
    {
        $isGz = str_ends_with($file, '.gz');

        if ($isGz && ! function_exists('gzopen')) {
            $this->warn('zlib が無いため圧縮ログを読み飛ばします: '.basename($file));

            return;
        }

        $handle = $isGz ? @gzopen($file, 'rb') : @fopen($file, 'rb');

        if ($handle === false) {
            $this->warn('ログを開けませんでした: '.basename($file));

            return;
        }

        try {
            while (true) {
                $line = $isGz ? gzgets($handle) : fgets($handle);
                if ($line === false) {
                    break;
                }

                yield rtrim($line, "\r\n");
            }
        } finally {
            $isGz ? gzclose($handle) : fclose($handle);
        }
    }

    /**
     * メッセージを「種別」に丸める。
     * 末尾のコンテキストJSONを外し、可変部（数字）をまとめて同種のログを1つに集約する。
     */
    private function normalizeMessage(string $message): string
    {
        // Laravel のフォーマッタは context を ' {"key":...}' の形で末尾に付ける。
        // 行頭が '[system:check-disk]' のようなメッセージを壊さないよう、'{"' に限定して落とす。
        $message = preg_replace('/\s\{".*$/s', '', $message) ?? $message;

        // 引用符で囲まれた可変値（URL・キー名など）をまとめる。
        // これが無いと 1062 の "Duplicate entry '<記事URL>'" が毎回別種別に散り、
        // 上位5件が同じ事象で埋まってしまう。
        $message = preg_replace("/'[^']*'/u", "'…'", $message) ?? $message;
        $message = preg_replace('/"[^"]*"/u', '"…"', $message) ?? $message;

        // 件数・ID・秒数などの可変部を N にまとめる（同じ事象を1種別として数えるため）
        $message = preg_replace('/\d+/', 'N', $message) ?? $message;
        $message = preg_replace('/\s+/', ' ', $message) ?? $message;

        return Str::limit(trim($message), self::KIND_LENGTH, '…');
    }

    /**
     * 「異常あり」と判定した理由（件名に載せる短い文字列）。空配列なら異常なし。
     *
     * WARNING は常に一定量出るため単独では異常と扱わない（件名の意味が薄れるため）。
     * 本文には必ず件数と上位種別を載せる。
     *
     * @param  list<array{command: string, count: int, last_failed_at: string}>  $failures
     * @param  array{ERROR: array{count: int, kinds: array<string, int>}, WARNING: array{count: int, kinds: array<string, int>}, files: list<string>}  $logs
     * @param  array{path: string, total: float, free: float, used: float, used_percent: int}|null  $disk
     * @return list<string>
     */
    private function abnormalReasons(array $failures, array $logs, ?array $disk): array
    {
        $reasons = [];

        $failureTotal = array_sum(array_column($failures, 'count'));
        if ($failureTotal > 0) {
            $reasons[] = "スケジュール失敗{$failureTotal}件";
        }

        if ($logs['ERROR']['count'] > 0) {
            $reasons[] = "ERROR {$logs['ERROR']['count']}件";
        }

        if ($disk === null) {
            $reasons[] = 'ディスク情報取得不可';
        } elseif ($disk['used_percent'] >= self::DISK_THRESHOLD) {
            $reasons[] = "ディスク{$disk['used_percent']}%";
        }

        return $reasons;
    }

    /**
     * 通知本文。短く保つ（詳細はログを見る前提の要約）。
     *
     * @param  list<array{command: string, count: int, last_failed_at: string}>  $failures
     * @param  array{ERROR: array{count: int, kinds: array<string, int>}, WARNING: array{count: int, kinds: array<string, int>}, files: list<string>}  $logs
     * @param  array{path: string, total: float, free: float, used: float, used_percent: int}|null  $disk
     */
    private function buildBody(Carbon $date, array $failures, array $logs, ?array $disk): string
    {
        $lines = ['対象日: '.$date->toDateString(), ''];

        $failureTotal = array_sum(array_column($failures, 'count'));
        $lines[] = '■ スケジュール失敗: '.$failureTotal.'件'
            .($failures === [] ? '' : '（'.count($failures).'コマンド）');
        if ($failures === []) {
            $lines[] = '  なし';
        } else {
            foreach ($failures as $f) {
                $last = Carbon::parse($f['last_failed_at'])->format('H:i:s');
                $lines[] = "  - {$f['command']}  {$f['count']}回  最終 {$last}";
            }
        }
        $lines[] = '';

        $lines[] = '■ laravel.log';
        foreach (['ERROR', 'WARNING'] as $level) {
            $lines[] = sprintf('  %-7s: %d件', $level, $logs[$level]['count']);
            foreach ($logs[$level]['kinds'] as $kind => $count) {
                $lines[] = "    - {$count}件 {$kind}";
            }
        }
        $lines[] = '  （'.($logs['files'] === [] ? '対象ログなし' : '対象: '.implode(', ', $logs['files'])).'）';
        $lines[] = '';

        if ($disk === null) {
            $lines[] = '■ ディスク使用率: 取得できませんでした';
        } else {
            $lines[] = sprintf(
                '■ ディスク使用率: %d%%（使用 %s / 空き %s / 総 %s）※しきい値%d%%',
                $disk['used_percent'],
                DiskUsage::humanize($disk['used']),
                DiskUsage::humanize($disk['free']),
                DiskUsage::humanize($disk['total']),
                self::DISK_THRESHOLD
            );
        }

        $lines[] = '';
        $lines[] = '詳細の再表示: php artisan ops:daily-report --date='.$date->toDateString().' --dry-run';

        return implode("\n", $lines);
    }
}
