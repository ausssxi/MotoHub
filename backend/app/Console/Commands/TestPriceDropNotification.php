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

class TestPriceDropNotification extends Command
{
    protected $signature = 'notify:price-drop-test
        {--user-id= : 通知先ユーザーID}
        {--listing-id= : 値下げ車両のListing ID}
        {--dry-run : 送信せずプレビューのみ}';

    protected $description = '値下げ通知の手動テスト送信（メール + LINE）';

    public function handle(): void
    {
        $userId = $this->option('user-id');
        $listingId = $this->option('listing-id');

        // ---- ユーザー取得 ----
        if ($userId) {
            $user = User::find($userId);
        } else {
            $user = User::where('is_admin', true)->first() ?? User::first();
        }

        if (!$user) {
            $this->error('ユーザーが見つかりません。--user-id を指定してください。');
            return;
        }

        // ---- Listing & PriceHistory 取得 ----
        if ($listingId) {
            $listing = Listing::with('bikeModel.manufacturer')->find($listingId);
            if (!$listing) {
                $this->error("Listing ID: {$listingId} が見つかりません。");
                return;
            }
            $history = PriceHistory::where('listing_id', $listing->id)
                ->whereColumn('old_price', '>', 'new_price')
                ->latest()
                ->first();
        } else {
            // 値下げ額が大きい順で自動選択
            $history = PriceHistory::with('listing.bikeModel.manufacturer')
                ->whereColumn('old_price', '>', 'new_price')
                ->whereHas('listing', fn ($q) => $q->where('is_sold_out', false))
                ->orderByRaw('(old_price - new_price) DESC')
                ->first();
            $listing = $history?->listing;
        }

        if (!$history) {
            $this->error('値下げ履歴が見つかりません。--listing-id で値下げのあるListingを指定してください。');
            return;
        }

        $listing->loadMissing('bikeModel.manufacturer');

        // ---- プレビュー表示 ----
        $bikeName = $listing->title ?? $listing->bikeModel?->name ?? 'バイク';
        $maker = $listing->bikeModel?->manufacturer?->name ?? '';
        $oldPrice = number_format($history->old_price / 10000, 1);
        $newPrice = number_format($history->new_price / 10000, 1);
        $diff = number_format(($history->old_price - $history->new_price) / 10000, 1);

        $this->newLine();
        $this->info('┌─────────────────────────────────────');
        $this->info('│ 通知プレビュー');
        $this->info('├─────────────────────────────────────');
        $this->line("│ 宛先:     {$user->name} ({$user->email})");
        $this->line("│ LINE:     " . ($user->hasLineLinked() ? "連携済み ({$user->line_id})" : '未連携'));
        $this->line("│ 車両:     {$bikeName}");
        $this->line("│ メーカー: {$maker}");
        $this->line("│ Listing:  #{$listing->id}");
        $this->line("│ 価格:     {$oldPrice}万円 → {$newPrice}万円 (-{$diff}万円)");
        $this->info('└─────────────────────────────────────');
        $this->newLine();

        if ($this->option('dry-run')) {
            $this->warn('(dry-run: 送信スキップ)');
            return;
        }

        // ---- メール送信 ----
        $this->info('📧 メール送信中...');
        try {
            Mail::to($user->email)->send(new PriceDropMail($user, $listing, $history));
            $this->info('   → メール送信成功！');
        } catch (\Exception $e) {
            $this->error("   → メール送信失敗: {$e->getMessage()}");
        }

        // ---- LINE送信 ----
        if ($user->hasLineLinked()) {
            $this->info('💬 LINE送信中...');
            try {
                $lineService = app(LineNotificationService::class);
                $result = $lineService->sendPriceDropAlert(
                    $user,
                    $listing,
                    (float) $history->old_price,
                    (float) $history->new_price
                );
                $this->info($result ? '   → LINE送信成功！' : '   → LINE送信失敗（storage/logs/laravel.log を確認）');
            } catch (\Exception $e) {
                $this->error("   → LINE送信失敗: {$e->getMessage()}");
            }
        } else {
            $this->warn('💬 LINE未連携のためスキップ');
        }

        $this->newLine();
        $this->info('完了！');
    }
}
