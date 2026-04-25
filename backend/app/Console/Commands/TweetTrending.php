<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Twitter\TrendingChartService;
use Abraham\TwitterOAuth\TwitterOAuth;
use Illuminate\Support\Facades\Log;

class TweetTrending extends Command
{
    protected $signature = 'bikes:tweet-trending {--dry-run : ツイートせずにテキストと画像を確認}';
    protected $description = '週間売れ筋ランキングをX(Twitter)に投稿します';

    public function __construct(
        private readonly TrendingChartService $chartService,
    ) {
        parent::__construct();
    }

    public function handle(): void
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('[DRY-RUN] Twitter APIは呼びません。');
        }

        $this->info('売れ筋ランキングの集計を開始します...');

        $ranking = $this->chartService->getWeeklyRanking();

        if ($ranking->isEmpty()) {
            $this->info('今週の販売データが見つかりませんでした。');
            return;
        }

        // --- テキスト ---
        $medals = ['🥇', '🥈', '🥉', '4⃣', '5⃣'];
        $text = "🔥 今週の売れ筋ランキング TOP5！\n\n";

        foreach ($ranking as $i => $item) {
            $medal = $medals[$i] ?? '';
            $text .= "{$medal} {$item->bike_name} {$item->sold_count}台\n";
        }

        $text .= "\nhttps://motohub.jp/ranking\n\n";
        $text .= '#中古バイク #MotoHub #売れ筋ランキング';

        // --- 画像生成 ---
        $png = $this->chartService->generateDashboardImage($ranking);

        if ($dryRun) {
            $this->newLine();
            $this->line('========================================');
            $this->line('--- テキスト ---');
            $this->line($text);

            if ($png) {
                $dir = storage_path('app/temp');
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
                $imagePath = $dir . '/trending_dashboard.png';
                file_put_contents($imagePath, $png);
                $this->line('--- 画像 ---');
                $this->info("保存先: {$imagePath}");
            } else {
                $this->warn('画像生成に失敗しました');
            }

            $this->line('========================================');
            return;
        }

        // --- Twitter接続 ---
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

        // --- 画像アップロード ---
        $mediaIds = [];
        if ($png) {
            $tempPath = storage_path('app/public/temp_trending_' . uniqid() . '.png');
            file_put_contents($tempPath, $png);

            try {
                $connection->setApiVersion('1.1');
                $media = $connection->upload('media/upload', ['media' => $tempPath]);
                if (isset($media->media_id_string)) {
                    $mediaIds[] = $media->media_id_string;
                }
            } catch (\Exception $e) {
                $this->error("Image upload failed: " . $e->getMessage());
            } finally {
                $connection->setApiVersion('2');
                if (file_exists($tempPath)) {
                    unlink($tempPath);
                }
            }
        }

        // --- ツイート投稿 ---
        $payload = ['text' => $text];
        if (!empty($mediaIds)) {
            $payload['media'] = ['media_ids' => $mediaIds];
        }

        $result = $connection->post('tweets', $payload);

        if ($connection->getLastHttpCode() == 201) {
            $this->info('売れ筋ランキングをツイートしました');
        } else {
            $this->error("Tweet failed code: " . $connection->getLastHttpCode());
            Log::error("Twitter API Error (Trending)", (array) $result);
        }
    }
}
