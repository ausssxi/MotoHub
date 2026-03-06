<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Listing;
use Abraham\TwitterOAuth\TwitterOAuth;
use Illuminate\Support\Facades\Log;

class TweetNewStock extends Command
{
    protected $signature = 'bikes:tweet-new-stock {--dry-run : ツイートせずにテキストだけ表示}';
    protected $description = '新着入荷まとめをX(Twitter)に投稿します';

    public function handle(): void
    {
        $this->info('新着入荷まとめの集計を開始します...');

        $yesterday = now()->subDay()->startOfDay();
        $todayStart = now()->startOfDay();

        // 前日作成のアクティブなリスティングをメーカー別に集計
        $listings = Listing::with('bikeModel.manufacturer')
            ->where('is_sold_out', false)
            ->whereBetween('created_at', [$yesterday, $todayStart])
            ->get();

        $total = $listings->count();

        if ($total === 0) {
            $this->info('昨日の新着入荷はありませんでした。');
            return;
        }

        // メーカー別の集計
        $makerCounts = $listings->groupBy(function ($listing) {
            return $listing->bikeModel?->manufacturer?->name ?? '不明';
        })->map->count()->sortDesc();

        // ツイート文面
        $text = "📦 昨日の新着入荷まとめ！\n\n";
        $text .= "合計 {$total}台 が新たに登場！\n\n";
        $text .= "🔥 人気メーカー\n";

        $count = 0;
        foreach ($makerCounts as $maker => $num) {
            if ($count >= 5) break;
            $text .= "・{$maker}: {$num}台\n";
            $count++;
        }

        $text .= "\n最新の在庫をチェック👇\n";
        $text .= "https://www.motohub.jp/bikes/search\n\n";
        $text .= "#MotoHub #バイク #中古バイク #新着入荷";

        if ($this->option('dry-run')) {
            $this->info("--- Dry Run ---");
            $this->line($text);
            return;
        }

        // Twitter接続
        try {
            $connection = new TwitterOAuth(
                config('services.twitter.consumer_key'),
                config('services.twitter.consumer_secret'),
                config('services.twitter.access_token'),
                config('services.twitter.access_token_secret')
            );
            $connection->setApiVersion('2');
        } catch (\Exception $e) {
            $this->error('Twitter接続エラー: 設定を確認してください。');
            return;
        }

        // テキストのみ（画像なし）
        $result = $connection->post('tweets', ['text' => $text]);

        if ($connection->getLastHttpCode() == 201) {
            $this->info("新着入荷まとめをツイートしました（{$total}台）");
        } else {
            $this->error("Tweet failed code: " . $connection->getLastHttpCode());
            Log::error("Twitter API Error (NewStock)", (array)$result);
        }
    }
}
