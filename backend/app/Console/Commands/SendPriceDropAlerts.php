<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\PriceDropMail;
use App\Models\PriceHistory;
use App\Models\User;
use App\Services\Line\LineNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendPriceDropAlerts extends Command
{
    protected $signature = 'bikes:send-price-alerts {--dry-run : 実際に送信せず対象を表示}';
    protected $description = 'お気に入り車両の値下げ通知を送信（メール + LINE）';

    /** 1ユーザーあたりの1日最大通知件数 */
    private const MAX_PER_USER = 3;

    public function handle(): void
    {
        $this->info('値下げアラートの送信処理を開始します...');
        $isDryRun = $this->option('dry-run');

        // 過去24時間 & 未通知 & 値下げのみ
        $histories = PriceHistory::with(['listing.favoritedByUsers', 'listing.bikeModel.manufacturer'])
            ->where('is_notified', false)
            ->where('created_at', '>=', now()->subDay())
            ->whereColumn('old_price', '>', 'new_price')
            ->orderByRaw('(old_price - new_price) DESC')  // 値下げ額が大きい順（上限対策）
            ->get();

        if ($histories->isEmpty()) {
            $this->info('通知すべき値下げ履歴はありません。');
            return;
        }

        $this->info("対象の値下げ履歴: {$histories->count()}件");

        // ユーザーごとの送信カウンター
        $userSentCount = [];
        $totalSent = 0;
        $totalSkipped = 0;

        foreach ($histories as $history) {
            $listing = $history->listing;

            // 車両なし or 売切 or お気に入りユーザーなし → スキップ
            if (!$listing || $listing->is_sold_out || $listing->favoritedByUsers->isEmpty()) {
                if (!$isDryRun) {
                    $history->update(['is_notified' => true]);
                }
                continue;
            }

            $bikeName = $listing->title ?? $listing->bikeModel?->name ?? 'バイク';
            $diff = number_format(($history->old_price - $history->new_price) / 10000, 1);

            foreach ($listing->favoritedByUsers as $user) {
                // 通知オフのユーザーはスキップ
                if (!$user->notify_price_drop) {
                    continue;
                }

                // 1日の上限チェック
                $uid = $user->id;
                $userSentCount[$uid] = ($userSentCount[$uid] ?? 0);
                if ($userSentCount[$uid] >= self::MAX_PER_USER) {
                    $totalSkipped++;
                    continue;
                }

                if ($isDryRun) {
                    $channel = $user->hasLineLinked() ? 'LINE' : 'メール';
                    $this->line("  [{$channel}] {$user->name} ← {$bikeName} (-{$diff}万円)");
                    $userSentCount[$uid]++;
                    $totalSent++;
                    continue;
                }

                // 送信
                try {
                    if ($user->hasLineLinked()) {
                        $lineService = app(LineNotificationService::class);
                        $lineService->sendPriceDropAlert(
                            $user,
                            $listing,
                            (float) $history->old_price,
                            (float) $history->new_price
                        );
                    } else {
                        Mail::to($user->email)->send(new PriceDropMail($user, $listing, $history));
                    }
                    $userSentCount[$uid]++;
                    $totalSent++;
                } catch (\Exception $e) {
                    $this->error("  送信失敗 (User#{$uid}): {$e->getMessage()}");
                }
            }

            if (!$isDryRun) {
                $history->update(['is_notified' => true]);
            }
        }

        $this->newLine();
        if ($isDryRun) {
            $this->info("--- Dry Run ---");
        }
        $this->info("送信: {$totalSent}通 / スキップ(上限超過): {$totalSkipped}通");
        $this->info("対象ユーザー: " . count($userSentCount) . "人");
    }
}
