<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PriceHistory;
use App\Mail\PriceDropMail;
use Illuminate\Support\Facades\Mail;
use App\Services\Line\LineNotificationService;

class SendPriceDropAlerts extends Command
{
    protected $signature = 'bikes:send-price-alerts {--dry-run : 実際に送信せず対象を表示}';
    protected $description = 'お気に入り登録されている車両が値下げされた際、該当ユーザーにアラートメールを非同期で送信します';

    public function handle()
    {
        $this->info('値下げアラートの送信処理を開始します...');

        // 未通知の価格変更履歴を取得（値下げのみ: old_price > new_price）
        $histories = PriceHistory::with(['listing.favoritedByUsers', 'listing.bikeModel.manufacturer'])
            ->where('is_notified', false)
            ->whereColumn('old_price', '>', 'new_price')
            ->get();

        if ($histories->isEmpty()) {
            $this->info('通知すべき値下げ履歴はありません。');
            return;
        }

        $this->info("未通知の値下げ履歴: {$histories->count()}件");

        $sentCount = 0;
        $isDryRun = $this->option('dry-run');

        foreach ($histories as $history) {
            $listing = $history->listing;

            // 車両が既に削除されている、またはお気に入りしているユーザーがいない場合はスキップ
            if (!$listing || $listing->favoritedByUsers->isEmpty()) {
                if (!$isDryRun) {
                    $history->update(['is_notified' => true]);
                }
                continue;
            }

            $users = $listing->favoritedByUsers;
            $bikeName = $listing->title ?? $listing->bikeModel?->name ?? 'バイク';
            $diff = number_format(($history->old_price - $history->new_price) / 10000, 1);

            if ($isDryRun) {
                $this->line("  {$bikeName}: {$diff}万円値下げ → {$users->count()}人に通知");
                $sentCount += $users->count();
                continue;
            }

            foreach ($users as $user) {
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
                    $sentCount++;
                } catch (\Exception $e) {
                    $this->error("送信失敗 (User#{$user->id}): {$e->getMessage()}");
                }
            }

            $history->update(['is_notified' => true]);
        }

        if ($isDryRun) {
            $this->info("--- Dry Run: {$sentCount}通の通知が送信される予定 ---");
        } else {
            $this->info("処理完了！ {$sentCount} 通のアラートを送信しました。");
        }
    }
}