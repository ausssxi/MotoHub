<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BikeModel;
use App\Models\BikeModelVideo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FetchYouTubeVideos extends Command
{
    protected $signature = 'youtube:fetch-videos {--chunk=50 : 1回で処理する車種数}';
    protected $description = 'YouTube動画をバッチ取得してDBに保存';

    private int $totalSaved = 0;
    private bool $quotaExceeded = false;

    public function handle(): int
    {
        $apiKey = config('services.youtube.api_key');
        if (!$apiKey) {
            $this->error('YOUTUBE_API_KEY が設定されていません');
            return self::FAILURE;
        }

        $chunk = (int) $this->option('chunk');

        // 動画未取得の車種を優先、次に古い順
        $models = BikeModel::query()
            ->leftJoin('bike_model_videos', 'bike_models.id', '=', 'bike_model_videos.bike_model_id')
            ->select('bike_models.*')
            ->groupBy('bike_models.id')
            ->orderByRaw('COUNT(bike_model_videos.id) ASC, MAX(bike_model_videos.updated_at) ASC')
            ->limit($chunk)
            ->get();

        if ($models->isEmpty()) {
            $this->info('処理対象の車種がありません');
            return self::SUCCESS;
        }

        $this->info("YouTube動画取得を開始（{$models->count()}車種）");

        foreach ($models as $i => $model) {
            if ($this->quotaExceeded) {
                $this->warn('クォータ超過のため処理を停止');
                break;
            }

            $num = $i + 1;
            $count = $this->fetchForModel($model, $apiKey);

            if ($count >= 0) {
                $this->line("[{$num}/{$models->count()}] {$model->name}: {$count}件取得");
            }
        }

        $this->info("完了: {$models->count()}車種処理、{$this->totalSaved}件の動画を保存");

        return self::SUCCESS;
    }

    private function fetchForModel(BikeModel $model, string $apiKey): int
    {
        $query = $model->name . ' バイク レビュー';

        try {
            $response = Http::timeout(10)->get('https://www.googleapis.com/youtube/v3/search', [
                'part' => 'snippet',
                'q' => $query,
                'type' => 'video',
                'order' => 'relevance',
                'maxResults' => 3,
                'hl' => 'ja',
                'regionCode' => 'JP',
                'key' => $apiKey,
            ]);

            if ($response->status() === 403) {
                Log::warning('YouTube API クォータ超過', ['model' => $model->name]);
                $this->quotaExceeded = true;
                return -1;
            }

            if ($response->failed()) {
                Log::warning('YouTube API エラー', [
                    'model' => $model->name,
                    'status' => $response->status(),
                ]);
                return -1;
            }

            $items = $response->json('items', []);
            $saved = 0;

            foreach ($items as $index => $item) {
                $videoId = $item['id']['videoId'] ?? '';
                if ($videoId === '') {
                    continue;
                }

                $snippet = $item['snippet'] ?? [];
                $thumbnail = $snippet['thumbnails']['high']['url']
                    ?? $snippet['thumbnails']['medium']['url']
                    ?? $snippet['thumbnails']['default']['url']
                    ?? '';

                BikeModelVideo::updateOrCreate(
                    [
                        'bike_model_id' => $model->id,
                        'video_id' => $videoId,
                    ],
                    [
                        'title' => $snippet['title'] ?? '',
                        'thumbnail_url' => $thumbnail,
                        'channel_name' => $snippet['channelTitle'] ?? '',
                        'published_at' => isset($snippet['publishedAt'])
                            ? \Carbon\Carbon::parse($snippet['publishedAt'])
                            : null,
                        'sort_order' => $index,
                    ]
                );
                $saved++;
            }

            $this->totalSaved += $saved;
            return $saved;
        } catch (\Throwable $e) {
            Log::warning('YouTube API 例外', [
                'model' => $model->name,
                'message' => $e->getMessage(),
            ]);
            return -1;
        }
    }
}
