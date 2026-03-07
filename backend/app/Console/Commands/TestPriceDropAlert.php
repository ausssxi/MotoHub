<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\PriceDropMail;
use App\Models\Listing;
use App\Models\PriceHistory;
use App\Models\User;
use App\Services\Line\LineNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class TestPriceDropAlert extends Command
{
    protected $signature = 'bikes:test-price-alert
        {--email= : 送信先メールアドレス（指定なしでログ出力のみ）}
        {--line : LINE通知もテスト送信する}
        {--user-id= : テスト対象のユーザーID}
        {--listing-id= : テスト対象のリスティングID}
        {--dry-run : 実際に送信せず内容を表示}';

    protected $description = '値下げアラートの手動テスト送信（1件）';

    public function handle(): void
    {
        // テスト対象の PriceHistory を取得
        $history = $this->findTestHistory();
        if (!$history) {
            $this->error('テスト対象の値下げ履歴が見つかりません。');
            return;
        }

        $listing = $history->listing;
        $listing->loadMissing('bikeModel.manufacturer');

        $bikeName = $listing->title ?? $listing->bikeModel?->name ?? 'バイク';
        $oldPrice = number_format($history->old_price / 10000, 1);
        $newPrice = number_format($history->new_price / 10000, 1);
        $diff = number_format(($history->old_price - $history->new_price) / 10000, 1);

        $this->info('=== テスト対象 ===');
        $this->line("車両: {$bikeName}");
        $this->line("Listing ID: {$listing->id}");
        $this->line("旧価格: {$oldPrice}万円 → 新価格: {$newPrice}万円 (差額: {$diff}万円)");
        $this->line("PriceHistory ID: {$history->id}");

        // テスト対象のユーザーを特定
        $user = $this->findTestUser($listing);
        if (!$user) {
            $this->error('テスト送信先のユーザーが見つかりません。');
            return;
        }

        $this->line("送信先: {$user->name} ({$user->email})");
        $this->line("LINE連携: " . ($user->hasLineLinked() ? "あり (line_id: {$user->line_id})" : 'なし'));

        if ($this->option('dry-run')) {
            $this->info('--- Dry Run: 送信をスキップしました ---');
            return;
        }

        // メール送信
        $email = $this->option('email') ?: $user->email;
        $this->info("メール送信中 → {$email}");

        try {
            Mail::to($email)->send(new PriceDropMail($user, $listing, $history));
            $this->info('メール送信成功！');
        } catch (\Exception $e) {
            $this->error("メール送信失敗: {$e->getMessage()}");
        }

        // LINE送信
        if ($this->option('line') && $user->hasLineLinked()) {
            $this->info('LINE送信中...');
            try {
                $lineService = app(LineNotificationService::class);
                $result = $lineService->sendPriceDropAlert(
                    $user,
                    $listing,
                    (float) $history->old_price,
                    (float) $history->new_price
                );
                $this->info($result ? 'LINE送信成功！' : 'LINE送信失敗（ログを確認）');
            } catch (\Exception $e) {
                $this->error("LINE送信失敗: {$e->getMessage()}");
            }
        }
    }

    private function findTestHistory(): ?PriceHistory
    {
        $listingId = $this->option('listing-id');

        if ($listingId) {
            return PriceHistory::with('listing')
                ->where('listing_id', $listingId)
                ->orderByDesc('created_at')
                ->first();
        }

        // 値下げ額が大きい順に、お気に入りされている車両を優先
        return PriceHistory::with('listing')
            ->whereHas('listing', fn ($q) => $q->where('is_sold_out', false))
            ->orderByRaw('(old_price - new_price) DESC')
            ->first();
    }

    private function findTestUser(Listing $listing): ?User
    {
        $userId = $this->option('user-id');

        if ($userId) {
            return User::find($userId);
        }

        // この車両をお気に入り登録しているユーザーを優先
        $favUser = $listing->favoritedByUsers()->first();
        if ($favUser) {
            return $favUser;
        }

        // なければ管理者ユーザー
        return User::where('is_admin', true)->first()
            ?? User::first();
    }
}
