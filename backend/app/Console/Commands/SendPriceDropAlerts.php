<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PriceHistory;
use App\Mail\PriceDropMail;
use Illuminate\Support\Facades\Mail;
use App\Services\Line\LineNotificationService;

class SendPriceDropAlerts extends Command
{
    protected $signature = 'bikes:send-price-alerts';
    protected $description = 'お気に入り登録されている車両が値下げされた際、該当ユーザーにアラートメールを非同期で送信します';

    public function handle()
    {
        $this->info('値下げアラートの送信処理を開始します...');

        // 未通知の価格変更履歴を取得
        $histories = PriceHistory::with(['listing.favoritedByUsers'])->where('is_notified', false)->get();

        if ($histories->isEmpty()) {
            $this->info('通知すべき値下げ履歴はありません。');
            return;
        }

        $sentCount = 0;

        foreach ($histories as $history) {
            $listing = $history->listing;

            // 車両が既に削除されている、またはお気に入りしているユーザーがいない場合はスキップ
            if (!$listing || $listing->favoritedByUsers->isEmpty()) {
                $history->update(['is_notified' => true]);
                continue;
            }

            $users = $listing->favoritedByUsers;

            foreach ($users as $user) {
                if ($user->hasLineLinked()) {
                    // LINE連携済み → LINEで通知
                    $lineService = app(LineNotificationService::class);
                    $lineService->sendPriceDropAlert(
                        $user,
                        $listing,
                        $history->old_price,
                        $history->new_price
                    );
                } else {
                    // LINE未連携 → メールで通知
                    Mail::to($user->email)->send(new PriceDropMail($user, $listing, $history));
                }
                $sentCount++;
            }

            // ユーザー全員をキューに入れたら通知済みに更新
            $history->update(['is_notified' => true]);
        }

        $this->info("処理完了！ {$sentCount} 通のアラートメールを送信キューに追加しました。");
    }
}