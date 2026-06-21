<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\GarageLaunchAnnouncement;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * 愛車ガレージ ローンチ告知メールの配信。
 * 段取り: --test でテスト送信 → 内田が確認 → 本送信（--force もしくは対話確認で明示GO）。
 * ★安全装置: 引数なしでは本送信しない（必ず確認を要求）。
 */
class SendGarageLaunchAnnouncement extends Command
{
    protected $signature = 'mail:garage-launch
        {--test= : 指定メールアドレスへテスト送信（1通のみ・本送信しない）}
        {--dry-run : 対象ユーザー数を表示するだけ（送信しない）}
        {--force : 本送信の対話確認をスキップ（自動化用・要注意）}';

    protected $description = '愛車ガレージ ローンチ告知メールを全ユーザーへ配信（テスト/ドライラン/本送信）';

    public function handle(): int
    {
        // 1) テスト送信（1通のみ）
        if ($address = $this->option('test')) {
            $user = User::where('email', $address)->first()
                ?? new User(['name' => 'テストライダー', 'email' => $address]);
            $user->email = $address; // transient でも宛先を確実に

            Mail::to($address)->send(new GarageLaunchAnnouncement($user));
            $this->info("テスト送信しました → {$address}（宛名: ".($user->name ?: 'ライダー').'）');

            return self::SUCCESS;
        }

        $recipients = User::whereNotNull('email')->where('email', '!=', '');
        $total = $recipients->count();

        // 2) ドライラン（送信しない）
        if ($this->option('dry-run')) {
            $this->info("【ドライラン】対象: {$total} 名（送信しません）");
            $recipients->take(5)->get()->each(fn (User $u) => $this->line("  - {$u->email}（宛名: ".($u->name ?: 'ライダー').'）'));
            if ($total > 5) {
                $this->line('  … ほか'.($total - 5).'名');
            }

            return self::SUCCESS;
        }

        // 3) 本送信（★明示確認なしには絶対送らない）
        if (! $this->option('force') && ! $this->confirm("本送信します。全 {$total} 名へ告知メールを送りますか？")) {
            $this->warn('中止しました（本送信していません）。テストは --test=you@example.com、確認は --dry-run。');

            return self::SUCCESS;
        }

        $sent = 0;
        $failed = 0;
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $recipients->chunkById(100, function ($users) use (&$sent, &$failed, $bar) {
            foreach ($users as $user) {
                try {
                    Mail::to($user->email)->send(new GarageLaunchAnnouncement($user));
                    $sent++;
                } catch (\Throwable $e) {
                    $failed++;
                    $this->newLine();
                    $this->error("送信失敗: {$user->email} — {$e->getMessage()}");
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->info("本送信完了: 成功 {$sent} 件 / 失敗 {$failed} 件 / 対象 {$total} 名");

        return self::SUCCESS;
    }
}
