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

    /** クォータ超過を検知したときの HTTP ステータス（403 or 429） */
    private ?int $quotaStatus = null;

    public function handle(): int
    {
        $apiKey = config('services.youtube.api_key');
        if (!$apiKey) {
            $this->error('YOUTUBE_API_KEY が設定されていません');
            return self::FAILURE;
        }

        $chunk = (int) $this->option('chunk');

        // 動画未取得の車種を優先、次に最後に更新した日時の古い順。
        // （MAX(updated_at) は動画が無ければ NULL になり、MySQL/SQLite とも ASC で先頭に来る）
        // クォータ超過で打ち切られても、次回の実行が続きから回る並びになっている。
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

        $total = $models->count();
        $this->info("YouTube動画取得を開始（{$total}車種）");

        $processed = 0;

        foreach ($models as $i => $model) {
            $num = $i + 1;
            $count = $this->fetchForModel($model, $apiKey);
            $processed++;

            if ($count >= 0) {
                $this->line("[{$num}/{$total}] {$model->name}: {$count}件取得");
            }

            // クォータ超過後はリクエストを投げても成功しない。ここで打ち切り、
            // ログは1件ごとではなくこの1行だけ出す。
            if ($this->quotaExceeded) {
                Log::warning('YouTube APIクォータ超過のため処理を打ち切り (youtube:fetch-videos)', [
                    'status' => $this->quotaStatus,
                    'processed' => $processed,
                    'total' => $total,
                    'stopped_at' => $model->name,
                    'saved' => $this->totalSaved,
                ]);
                $this->warn("クォータ超過（HTTP {$this->quotaStatus}）のため {$processed}/{$total} 件目で処理を打ち切りました");
                break;
            }
        }

        $this->info("完了: {$processed}/{$total}車種処理、{$this->totalSaved}件の動画を保存");

        // クォータ超過による打ち切りは想定内なので正常終了扱いにする。
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

            // 403=quotaExceeded / 429=rateLimitExceeded。どちらも以降のリクエストは
            // 成功しないので打ち切る。ここではログを出さず、打ち切り時に1行だけ出す。
            if ($response->status() === 403 || $response->status() === 429) {
                $this->quotaExceeded = true;
                $this->quotaStatus = $response->status();
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
