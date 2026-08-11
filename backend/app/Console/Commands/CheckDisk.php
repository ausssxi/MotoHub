<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\DiskUsage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * ディスク使用率の事前検知（先日のディスク100%→全ページ500の再発防止）。
 *
 * 使用率の算出そのものは App\Support\DiskUsage に切り出した（ops:daily-report と共用。
 * 二重実装して数字が食い違うのを防ぐため）。取得方法・丸め方は切り出し前と同じ。
 *
 * しきい値以上（または --force）でメール通知。宛先は config('backup.notifications.mail.to')
 * を再利用（BACKUP_NOTIFICATION_EMAIL → CONTACT_ADMIN_EMAIL → info@motohub.jp のフォールバック
 * を config/backup.php:253 が実装済み）。本番に常駐 queue worker が無いため送信は必ず同期
 * （Mail::raw、queue は絶対に使わない）。
 */
final class CheckDisk extends Command
{
    protected $signature = 'system:check-disk {--threshold=85} {--force}';

    protected $description = 'ディスク使用率を算出し、しきい値以上ならメール通知する（ディスク枯渇の事前検知）';

    public function handle(): int
    {
        $threshold = (int) $this->option('threshold');
        $path = storage_path();

        $usage = DiskUsage::current($path);

        if ($usage === null) {
            $this->error("ディスク情報を取得できませんでした: {$path}");

            return self::FAILURE;
        }

        $total = $usage['total'];
        $free = $usage['free'];
        $used = $usage['used'];
        $usedPercent = $usage['used_percent'];

        // 常に人間可読でコンソール出力（手動実行で状態確認できるように）
        $this->info('ディスク使用状況');
        $this->line("  対象パス   : {$path}");
        $this->line('  使用率     : '.$usedPercent.'%');
        $this->line('  使用量     : '.$this->humanize($used));
        $this->line('  空き容量   : '.$this->humanize($free));
        $this->line('  総容量     : '.$this->humanize($total));
        $this->line("  しきい値   : {$threshold}%");

        $force = (bool) $this->option('force');

        // しきい値未満かつ --force 指定なしなら静かに終わる
        if ($usedPercent < $threshold && ! $force) {
            return self::SUCCESS;
        }

        $checkDir = base_path('storage/app/public');

        $subject = "【MotoHub】ディスク使用率 {$usedPercent}%";
        $body = implode("\n", [
            'MotoHub 本番ディスクの使用率がしきい値に達しました（または --force 実行）。',
            '',
            "使用率   : {$usedPercent}%",
            '使用量   : '.$this->humanize($used),
            '空き容量 : '.$this->humanize($free),
            '総容量   : '.$this->humanize($total),
            "しきい値 : {$threshold}%",
            '',
            '確認すべきディレクトリ:',
            "  {$checkDir}",
            '  （画像・追記系ログの肥大が主因になりやすい。du は避け、ls -la / find で当たりを付ける）',
        ]);

        // Log にも同内容を記録
        Log::warning('[system:check-disk] '.$subject, [
            'used_percent' => $usedPercent,
            'used' => $this->humanize($used),
            'free' => $this->humanize($free),
            'total' => $this->humanize($total),
            'threshold' => $threshold,
            'path' => $path,
        ]);

        $to = config('backup.notifications.mail.to');

        try {
            // 必ず同期送信。queue()/Mail::queue は使わない（本番に常駐 queue worker が無く永久未送信になる）。
            Mail::raw($body, function ($message) use ($to, $subject): void {
                $message->to($to)->subject($subject);
            });
            $this->warn("しきい値到達につきメール送信しました: {$to}");
        } catch (\Throwable $e) {
            // 送信失敗でもコマンド自体は状態表示に成功しているので、エラーは記録して継続。
            Log::error('[system:check-disk] メール送信に失敗しました', ['error' => $e->getMessage()]);
            $this->error('メール送信に失敗しました: '.$e->getMessage());
        }

        return self::SUCCESS;
    }

    /**
     * バイト数を人間可読形式（B/KB/MB/GB/TB）に整形する。
     * 実装は App\Support\DiskUsage と共用（ops:daily-report と表記を揃えるため）。
     */
    private function humanize(float $bytes): string
    {
        return DiskUsage::humanize($bytes);
    }
}
