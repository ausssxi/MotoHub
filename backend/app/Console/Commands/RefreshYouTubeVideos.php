<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BikeModel;
use App\Models\BikeModelVideo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RefreshYouTubeVideos extends Command
{
    protected $signature = 'youtube:refresh-videos {--days=30 : この日数以上前に取得した動画を再取得}';
    protected $description = '古いYouTube動画情報を再取得して更新';

    private int $totalUpdated = 0;
    private bool $quotaExceeded = false;

    public function handle(): int
    {
        $apiKey = config('services.youtube.api_key');
        if (!$apiKey) {
            $this->error('YOUTUBE_API_KEY が設定されていません');
            return self::FAILURE;
        }

        $days = (int) $this->option('days');
        $threshold = now()->subDays($days);

        // 古い動画を持つ車種を取得
        $modelIds = BikeModelVideo::where('updated_at', '<', $threshold)
            ->distinct()
            ->pluck('bike_model_id');

        if ($modelIds->isEmpty()) {
            $this->info("{$days}日以上前の動画はありません");
            return self::SUCCESS;
        }

        $models = BikeModel::whereIn('id', $modelIds)->get();
        $this->info("再取得対象: {$models->count()}車種（{$days}日以上前）");

        foreach ($models as $i => $model) {
            if ($this->quotaExceeded) {
                $this->warn('クォータ超過のため処理を停止');
                break;
            }

            $num = $i + 1;
            $count = $this->refreshForModel($model, $apiKey);

            if ($count >= 0) {
                $this->line("[{$num}/{$models->count()}] {$model->name}: {$count}件更新");
            }
        }

        $this->info("完了: {$this->totalUpdated}件の動画を更新");

        return self::SUCCESS;
    }

    private function refreshForModel(BikeModel $model, string $apiKey): int
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
                Log::warning('YouTube API クォータ超過 (refresh)', ['model' => $model->name]);
                $this->quotaExceeded = true;
                return -1;
            }

            if ($response->failed()) {
                Log::warning('YouTube API エラー (refresh)', [
                    'model' => $model->name,
                    'status' => $response->status(),
                ]);
                return -1;
            }

            // 既存レコードを削除して新しい結果に差し替え
            BikeModelVideo::where('bike_model_id', $model->id)->delete();

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

                BikeModelVideo::create([
                    'bike_model_id' => $model->id,
                    'video_id' => $videoId,
                    'title' => $snippet['title'] ?? '',
                    'thumbnail_url' => $thumbnail,
                    'channel_name' => $snippet['channelTitle'] ?? '',
                    'published_at' => isset($snippet['publishedAt'])
                        ? \Carbon\Carbon::parse($snippet['publishedAt'])
                        : null,
                    'sort_order' => $index,
                ]);
                $saved++;
            }

            $this->totalUpdated += $saved;
            return $saved;
        } catch (\Throwable $e) {
            Log::warning('YouTube API 例外 (refresh)', [
                'model' => $model->name,
                'message' => $e->getMessage(),
            ]);
            return -1;
        }
    }
}
