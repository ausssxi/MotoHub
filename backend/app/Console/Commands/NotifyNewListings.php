<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Listing;
use App\Models\SavedSearch;
use App\Repositories\Bike\ListingRepository;
use Illuminate\Support\Facades\Mail; // 追加
use App\Mail\NewListingsNotification;  // 追加
use Illuminate\Support\Facades\Log;

class NotifyNewListings extends Command
{
    protected $signature = 'bikes:notify-new-listings';
    protected $description = '保存された検索条件に基づいて新着通知を送ります';

    public function handle(ListingRepository $repo): void
    {
        $this->info('新着通知のマッチングを開始します...');

        // 1. 直近1時間に追加された車両を取得
        $newListings = Listing::where('created_at', '>=', now()->subHour())
            ->active()
            ->get();

        if ($newListings->isEmpty()) {
            $this->info('新着車両はありませんでした。');
            return;
        }

        $this->info("新着車両: {$newListings->count()}台");

        // 2. 有効な検索条件を全件取得
        $savedSearches = SavedSearch::where('is_active', true)
            ->where('mail_notify', true) // メール通知ONのものだけ
            ->with('user')
            ->get();

        foreach ($savedSearches as $search) {
            // ユーザーが存在しない、またはメールアドレスがない場合はスキップ
            if (!$search->user || !$search->user->email) {
                continue;
            }

            $conditions = $search->conditions;
            $matchedListings = [];

            // 3. メモリ上でマッチング
            foreach ($newListings as $bike) {
                if ($this->isMatch($bike, $conditions)) {
                    $matchedListings[] = $bike;
                }
            }

            if (!empty($matchedListings)) {
                // ★修正: ログ出力だけでなく、メール送信を実行
                $this->info("HIT! User: {$search->user->email} / Matches: " . count($matchedListings));
                
                try {
                    // メール送信 (同期送信)
                    // ※ユーザー数が多い場合は Queue (非同期) の使用を検討してください
                    Mail::to($search->user->email)
                        ->send(new NewListingsNotification(
                            $search->user,
                            $search->name ?? '保存された条件',
                            $matchedListings
                        ));

                    // 通知済み日時を更新
                    $search->update(['last_notified_at' => now()]);

                } catch (\Exception $e) {
                    $this->error("Mail send failed: " . $e->getMessage());
                    Log::error("Notification Error", ['search_id' => $search->id, 'error' => $e->getMessage()]);
                }
            }
        }

        $this->info('完了');
    }

    /**
     * 車両が条件に合致するか判定
     */
    private function isMatch(Listing $bike, array $cond): bool
    {
        // メーカーID
        if (!empty($cond['manufacturer_id']) && $bike->manufacturer_id != $cond['manufacturer_id']) return false;
        
        // 車種ID
        if (!empty($cond['bike_model_id']) && $bike->bike_model_id != $cond['bike_model_id']) return false;

        // 価格
        if (!empty($cond['min_price']) && $bike->total_price < ($cond['min_price'] * 10000)) return false;
        if (!empty($cond['max_price']) && $bike->total_price > ($cond['max_price'] * 10000)) return false;

        // 走行距離
        if (!empty($cond['min_mileage']) && $bike->mileage < $cond['min_mileage']) return false;
        if (!empty($cond['max_mileage']) && $bike->mileage > $cond['max_mileage']) return false;

        // 排気量 (もし条件にあれば)
        if (!empty($cond['min_displacement']) && $bike->displacement < $cond['min_displacement']) return false;
        if (!empty($cond['max_displacement']) && $bike->displacement > $cond['max_displacement']) return false;

        return true;
    }
}