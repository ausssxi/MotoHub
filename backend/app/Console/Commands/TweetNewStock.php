<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\BikeModel;
use App\Services\Twitter\NewStockChartService;
use Abraham\TwitterOAuth\TwitterOAuth;
use Illuminate\Support\Facades\Log;

class TweetNewStock extends Command
{
    protected $signature = 'bikes:tweet-new-stock {--dry-run : ツイートせずにテキストと画像を確認}';
    protected $description = '新着入荷まとめをX(Twitter)に投稿します';

    public function __construct(
        private readonly NewStockChartService $chartService,
    ) {
        parent::__construct();
    }

    public function handle(): void
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('[DRY-RUN] Twitter APIは呼びません。');
        }

        $this->info('新着入荷まとめの集計を開始します...');

        $total = $this->chartService->getTotalCount();

        if ($total === 0) {
            $this->info('直近24時間の新着入荷はありませんでした。');
            return;
        }

        // --- テキスト ---
        $formattedTotal = number_format($total);
        $topModels = $this->chartService->getTopModels();

        $text = "📦 本日の新着入荷 {$formattedTotal}台！\n\n";
        foreach ($topModels as $model) {
            $text .= "🏍 {$model->name} {$model->count}台\n";
        }
        $text .= "\nhttps://motohub.jp/bikes/new-arrivals\n\n";
        $text .= implode(' ', $this->buildTags($topModels));

        // --- 画像生成 ---
        $png = $this->chartService->generateDashboardImage();

        if ($dryRun) {
            $this->newLine();
            $this->line('========================================');
            $this->info("新着入荷: {$total}台");
            $this->line('--- テキスト ---');
            $this->line($text);

            if ($png) {
                $dir = storage_path('app/temp');
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
                $imagePath = $dir . '/new_stock_dashboard.png';
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
            $tempPath = storage_path('app/public/temp_newstock_' . uniqid() . '.png');
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
            $this->info("新着入荷まとめをツイートしました（{$total}台）");
        } else {
            $this->error("Tweet failed code: " . $connection->getLastHttpCode());
            Log::error("Twitter API Error (NewStock)", (array) $result);
        }
    }

    private function buildTags($topModels): array
    {
        $tags = [
            '#バイク乗りと繋がりたい', '#バイク好きと繋がりたい', '#バイクのある生活',
            '#中古バイク', '#MotoHub', '#ツーリング',
            '#新着入荷',
        ];

        // TOP車種の名前とメーカーをタグに追加
        $modelIds = collect($topModels)->pluck('bike_model_id')->filter()->values();
        if ($modelIds->isNotEmpty()) {
            $bikeModels = BikeModel::with('manufacturer')
                ->whereIn('id', $modelIds)
                ->get()
                ->keyBy('id');

            $addedMakers = [];
            foreach ($topModels as $model) {
                $bikeModel = $bikeModels->get($model->bike_model_id);
                if (!$bikeModel) continue;

                $clean = preg_replace('/[\s　\(\)（）\/]+/u', '', $bikeModel->name);
                if ($clean) $tags[] = "#{$clean}";

                $makerSlug = $bikeModel->manufacturer?->slug;
                if ($makerSlug && !in_array($makerSlug, $addedMakers, true)) {
                    $addedMakers[] = $makerSlug;
                    $tags = array_merge($tags, match ($makerSlug) {
                        'yamaha'   => ['#YAMAHAが美しい'],
                        'honda'    => ['#Honda党'],
                        'kawasaki' => ['#漢は黙ってカワサキ'],
                        'suzuki'   => ['#鈴菌'],
                        default    => ["#{$makerSlug}"],
                    });
                }
            }
        }

        return array_slice(array_unique($tags), 0, 13);
    }
}
