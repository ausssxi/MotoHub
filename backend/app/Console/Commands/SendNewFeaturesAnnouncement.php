<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\NewFeaturesAnnouncement;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * 新機能告知メールの配信（[[SendGarageLaunchAnnouncement]] を踏襲）。
 * 段取り: --dry-run で件数確認 → --test で自分に1通 → 文面確認 → 本送信（対話確認 or --force）。
 * ★プライバシー: 1人ずつ個別送信（To は本人のみ・複数アドレスを1通の To/CC にまとめない）。
 * ★安全装置: 引数なしでは本送信しない（必ず確認を要求）。
 */
class SendNewFeaturesAnnouncement extends Command
{
    protected $signature = 'mail:new-features
        {--test= : 指定メールアドレスへテスト送信（1通のみ・本送信しない）}
        {--dry-run : 対象ユーザー数と先頭数件（マスク表示）のみ（送信しない）}
        {--force : 本送信の対話確認をスキップ（自動化用・要注意）}';

    protected $description = '新機能告知メール（アイコン/ガレージコメント/マイコンテンツ）を全ユーザーへ個別配信';

    public function handle(): int
    {
        // 1) テスト送信（1通のみ・自分宛の確認用）
        if ($address = $this->option('test')) {
            $user = User::where('email', $address)->first()
                ?? new User(['name' => 'テストライダー', 'email' => $address]);
            $user->email = $address; // transient でも宛先を確実に

            Mail::to($address)->send(new NewFeaturesAnnouncement($user));
            $this->info("テスト送信しました → {$address}（宛名: ".($user->name ?: 'ライダー').'）');

            return self::SUCCESS;
        }

        // 対象＝メール有りの全会員（配信停止列は無し＝返信ベースopt-out）
        $recipients = User::whereNotNull('email')->where('email', '!=', '');
        $total = $recipients->count();

        // 2) ドライラン（送信しない・アドレスはマスク表示）
        if ($this->option('dry-run')) {
            $this->info("【ドライラン】対象: {$total} 名（送信しません）");
            $recipients->take(5)->get()->each(fn (User $u) => $this->line('  - '.$this->maskEmail($u->email).'（宛名: '.($u->name ?: 'ライダー').'）'));
            if ($total > 5) {
                $this->line('  … ほか'.($total - 5).'名');
            }

            return self::SUCCESS;
        }

        // 3) 本送信（★明示確認なしには絶対送らない）
        if (! $this->option('force') && ! $this->confirm("本送信します。全 {$total} 名へ告知メールを『1人ずつ個別に』送りますか？")) {
            $this->warn('中止しました（本送信していません）。テストは --test=you@example.com、確認は --dry-run。');

            return self::SUCCESS;
        }

        $sent = 0;
        $failed = 0;
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        // chunkById で分割しつつ、必ず「1アドレス＝1通」で送る（To に他人を混ぜない）。
        $recipients->chunkById(100, function ($users) use (&$sent, &$failed, $bar) {
            foreach ($users as $user) {
                try {
                    Mail::to($user->email)->send(new NewFeaturesAnnouncement($user));
                    $sent++;
                    usleep(200_000); // 0.2s: 送信レート配慮（過剰にしない）
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

    /**
     * ドライラン表示用にメールをマスク（先頭2文字＋ドメイン頭のみ）。ログに素のアドレスを残さない。
     */
    private function maskEmail(string $email): string
    {
        if (! str_contains($email, '@')) {
            return '***';
        }
        [$local, $domain] = explode('@', $email, 2);
        $localMasked = mb_substr($local, 0, 2).'***';
        $domainHead = mb_substr($domain, 0, 1).'***';

        return $localMasked.'@'.$domainHead;
    }
}
