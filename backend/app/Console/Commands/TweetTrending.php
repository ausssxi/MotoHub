<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Listing;
use Abraham\TwitterOAuth\TwitterOAuth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TweetTrending extends Command
{
    protected $signature = 'bikes:tweet-trending {--dry-run : ツイートせずにテキストだけ表示}';
    protected $description = '週間トレンド車種をX(Twitter)に投稿します';

    public function handle(): void
    {
        $this->info('トレンド車種の集計を開始します...');

        // bike_model_id ごとに view_count_total を合計し上位3車種を取得
        $trending = Listing::select('bike_model_id', DB::raw('SUM(view_count_total) as total_views'))
            ->where('is_sold_out', false)
            ->whereNotNull('bike_model_id')
            ->groupBy('bike_model_id')
            ->orderByDesc('total_views')
            ->limit(3)
            ->with('bikeModel.manufacturer')
            ->get();

        if ($trending->isEmpty()) {
            $this->info('トレンドデータが見つかりませんでした。');
            return;
        }

        $medals = ['🥇', '🥈', '🥉'];
        $text = "🔥 今週の注目バイクTOP3！\n\n";

        foreach ($trending as $i => $item) {
            $bikeName = $item->bikeModel?->name ?? '不明';
            $medal = $medals[$i] ?? '';
            $text .= "{$medal} {$bikeName}\n";
        }

        $text .= "\nみんなが気になってるバイクをチェック👇\n";
        $text .= "https://www.motohub.jp/bikes/search\n\n";
        $text .= "#MotoHub #バイク #中古バイク #トレンド";

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

        // テキストのみ
        $result = $connection->post('tweets', ['text' => $text]);

        if ($connection->getLastHttpCode() == 201) {
            $this->info("トレンド車種をツイートしました");
        } else {
            $this->error("Tweet failed code: " . $connection->getLastHttpCode());
            Log::error("Twitter API Error (Trending)", (array)$result);
        }
    }
}
