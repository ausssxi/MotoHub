<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Review;
use App\Services\Twitter\ReviewChartService;
use Abraham\TwitterOAuth\TwitterOAuth;
use Illuminate\Support\Facades\Log;

class TweetReviews extends Command
{
    protected $signature = 'bikes:tweet-reviews {--dry-run : ツイートせずにテキストと画像を確認}';
    protected $description = '新着レビューをX(Twitter)に投稿します';

    public function __construct(
        private readonly ReviewChartService $chartService,
    ) {
        parent::__construct();
    }

    public function handle(): void
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('[DRY-RUN] Twitter APIは呼びません。');
        }

        $this->info('新着レビューの探索を開始します...');

        $review = Review::with(['bikeModel.manufacturer'])
            ->where('is_approved', true)
            ->whereNull('tweeted_at')
            ->orderBy('created_at', 'asc')
            ->first();

        if (!$review) {
            $this->info('未ツイートのレビューはありませんでした。');
            return;
        }

        // --- テキスト ---
        $bikeName = $review->bikeModel->name ?? '車種不明';
        $makerName = $review->bikeModel->manufacturer->name ?? '';
        $rating = $review->rating;
        $stars = str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
        $avg = number_format($this->calcAvgRating($review), 1);

        $bodySnippet = mb_substr($review->body, 0, 40);
        if (mb_strlen($review->body) > 40) {
            $bodySnippet .= '...';
        }

        $text = "⭐ ユーザーレビュー紹介\n";
        $text .= "🏍 {$bikeName}";
        if ($makerName) {
            $text .= " ({$makerName})";
        }
        $text .= "\n";
        $text .= "総合評価: {$stars} ({$avg})\n";
        $text .= "「{$bodySnippet}」\n\n";
        $text .= 'https://motohub.jp' . ($review->bikeModel->seo_url ?? '') . "#reviews\n\n";

        $cleanBikeName = preg_replace('/[\s　\(\)（）\/]+/u', '', $bikeName);
        $text .= '#中古バイク #MotoHub #バイクレビュー';
        if ($cleanBikeName && $cleanBikeName !== '車種不明') {
            $text .= " #{$cleanBikeName}";
        }

        // --- 画像生成 ---
        $png = $this->chartService->generateReviewCard($review);

        if ($dryRun) {
            $this->newLine();
            $this->line('========================================');
            $this->info("Review ID: {$review->id}");
            $this->line('--- テキスト ---');
            $this->line($text);

            if ($png) {
                $dir = storage_path('app/temp');
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
                $imagePath = $dir . '/review_card.png';
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
            $tempPath = storage_path('app/public/temp_review_' . uniqid() . '.png');
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
            $this->info("Tweeted Review ID: {$review->id}");
            $review->update(['tweeted_at' => now()]);
        } else {
            $this->error("Tweet failed code: " . $connection->getLastHttpCode());
            Log::error("Twitter API Error (Review)", (array) $result);
        }
    }

    private function calcAvgRating(Review $review): float
    {
        $ratings = array_filter([
            $review->rating_design,
            $review->rating_engine,
            $review->rating_handling,
            $review->rating_fuel_economy,
            $review->rating_cost_performance,
        ]);

        if (empty($ratings)) {
            return (float) $review->rating;
        }

        return array_sum($ratings) / count($ratings);
    }
}
