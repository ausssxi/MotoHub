<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Listing;
use App\Services\Twitter\DealChartService;
use Abraham\TwitterOAuth\TwitterOAuth;
use Illuminate\Support\Facades\Log;

class TweetBargains extends Command
{
    protected $signature = 'bikes:tweet-bargains {--dry-run : Twitter APIを呼ばず、テキストと画像をコンソールに出力するだけ}';
    protected $description = 'お買い得車両を探してX(Twitter)に投稿します';

    public function __construct(
        private readonly DealChartService $chartService,
    ) {
        parent::__construct();
    }

    public function handle(): void
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('[DRY-RUN] Twitter APIは呼びません。テキストと画像の確認のみ。');
        }

        $this->info('お買い得車両の探索を開始します...');

        $connection = null;
        if (!$dryRun) {
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
        }

        $listings = Listing::with(['bikeModel.manufacturer'])
            ->whereNull('tweeted_at')
            ->where('is_sold_out', false)
            ->whereNotNull('bike_model_id')
            ->whereNotNull('total_price')
            ->where('total_price', '>', 0)
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        $tweetCount = 0;
        $maxTweets = 1;

        foreach ($listings as $listing) {
            if ($tweetCount >= $maxTweets) {
                $this->info("投稿上限({$maxTweets}件)に達したため終了します。");
                break;
            }

            $averagePrice = Listing::where('bike_model_id', $listing->bike_model_id)
                ->where('is_sold_out', false)
                ->where('id', '!=', $listing->id)
                ->avg('total_price');

            if (!$averagePrice || $averagePrice == 0) {
                if (!$dryRun) {
                    $listing->update(['tweeted_at' => now()]);
                }
                continue;
            }

            $discountRate = 0.8;

            if ($listing->total_price < ($averagePrice * $discountRate)) {
                $percentOff = (int) round((($averagePrice - $listing->total_price) / $averagePrice) * 100);
                $priceInMan = number_format($listing->total_price / 10000, 1);
                $rawName = $listing->title ?? $listing->bikeModel?->name ?? '車種名不明';
                $displayName = preg_replace('#[/／].*$#u', '', $rawName);
                $makerName = $listing->bikeModel?->manufacturer?->name ?? '';
                $makerSlug = $listing->bikeModel?->manufacturer?->slug ?? '';

                // --- ツイート文言 ---
                $catchCopies = [
                    "激アツ車両発見！急げ！",
                    "相場崩壊！？この価格は見逃せない",
                    "探していた人、チャンスです！",
                    "掘り出し物を発見しました！",
                ];
                $catch = $catchCopies[array_rand($catchCopies)];

                $makerDisplay = $makerName ? "（{$makerName}）" : '';
                $url = route('bikes.show', $listing->id);

                $makerTag = $makerSlug ? " #{$makerSlug}" : '';

                $text = "{$catch}\n\n";
                $text .= "🏍 {$displayName}{$makerDisplay}\n";
                $text .= "💰 {$priceInMan}万円（相場より{$percentOff}%安い！）\n\n";
                $text .= "{$url}\n\n";
                $text .= "#中古バイク #MotoHub{$makerTag}";

                // --- 画像生成 ---
                $png = $this->chartService->generateChartImage($listing);

                if ($dryRun) {
                    $this->newLine();
                    $this->line('========================================');
                    $this->info("Listing ID: {$listing->id}");
                    $this->line('--- テキスト ---');
                    $this->line($text);

                    if ($png) {
                        $dir = storage_path('app/temp');
                        if (!is_dir($dir)) {
                            mkdir($dir, 0755, true);
                        }
                        $imagePath = $dir . "/bargain_{$listing->id}.png";
                        file_put_contents($imagePath, $png);
                        $this->line('--- 画像 ---');
                        $this->info("保存先: {$imagePath}");
                    } else {
                        $this->warn('画像生成に失敗しました');
                    }

                    $this->line('========================================');
                    $tweetCount++;
                    continue;
                }

                // --- 本番: 画像アップロード + ツイート ---
                $mediaIds = [];
                $tempPath = null;

                if ($png) {
                    $tempPath = storage_path('app/public/temp_tweet_' . uniqid() . '.png');
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
                        if ($tempPath && file_exists($tempPath)) {
                            unlink($tempPath);
                        }
                    }
                }

                $payload = ['text' => $text];
                if (!empty($mediaIds)) {
                    $payload['media'] = ['media_ids' => $mediaIds];
                }

                $result = $connection->post('tweets', $payload);

                if ($connection->getLastHttpCode() == 201) {
                    $this->info("Tweeted: {$displayName} (ID: {$listing->id})");
                    $listing->update(['tweeted_at' => now()]);
                    $tweetCount++;
                } else {
                    $this->error("Tweet failed code: " . $connection->getLastHttpCode());
                    Log::error("Twitter API Error", (array) $result);
                    break;
                }
            } else {
                if (!$dryRun) {
                    $listing->update(['tweeted_at' => now()]);
                }
            }
        }

        $this->info("完了: {$tweetCount}件" . ($dryRun ? '確認' : 'ツイート') . "しました。");
    }
}
