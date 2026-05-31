<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BikeModel;
use App\Models\BikeModelVideo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class RefreshBikeYouTube extends Command
{
    // ⚠️ YouTube Data API quota: search.list = 100 units/コール、1日上限 10,000 units。
    // デフォルト80件 = 8,000 units でヘッドルームを残す（render pathからAPIは叩かないため、
    // ここが唯一のquota消費。youtube:refresh-videos(週次)と同日でも403で自然に頭打ち）。
    protected $signature = 'youtube:refresh
        {--limit=80 : 1回の実行で取得する最大モデル数（quota安全枠）}
        {--all : 空マーカー/DB有無を問わず取得する}';

    protected $description = '在庫あり車種でDB動画が無いものをYouTube APIで取得しDB保存（render pathから分離・quota厳守）';

    private const EMPTY_MARKER_TTL = 2592000; // 30日 — 動画が見つからない車種の再試行抑制

    private bool $quotaExceeded = false;

    public function handle(): int
    {
        $apiKey = config('services.youtube.api_key');
        if (! $apiKey) {
            $this->error('YOUTUBE_API_KEY が設定されていません');

            return self::FAILURE;
        }

        $limit = max(1, (int) $this->option('limit'));
        $all = (bool) $this->option('all');

        // 在庫あり車種を人気順。DB動画が無いものだけを対象（人気順で優先）。
        $models = BikeModel::query()
            ->with('manufacturer')
            ->whereHas('listings', fn ($q) => $q->active())
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('bike_model_videos')
                    ->whereColumn('bike_model_videos.bike_model_id', 'bike_models.id');
            })
            ->withCount(['listings' => fn ($q) => $q->active()])
            ->orderByDesc('listings_count')
            ->get();

        $this->info("DB動画なし在庫車種: {$models->count()} 件 / 今回上限: {$limit}（quota枠）");

        $done = 0;
        $saved = 0;
        $empty = 0;
        $skipped = 0;

        foreach ($models as $model) {
            if ($this->quotaExceeded) {
                $this->warn('クォータ超過のため処理を停止');
                break;
            }

            if ($done >= $limit) {
                break;
            }

            if (! $all && Cache::has("youtube_empty_{$model->id}")) {
                $skipped++;

                continue;
            }

            $count = $this->fetchForModel($model, $apiKey);

            if ($count < 0) {
                // 403等エラー: quotaExceededなら次ループで停止。それ以外は1件消費扱いで継続。
                continue;
            }

            $done++;

            if ($count === 0) {
                // 動画ゼロ: 30日マーカーで再試行抑制
                Cache::put("youtube_empty_{$model->id}", true, self::EMPTY_MARKER_TTL);
                $empty++;
            } else {
                $saved += $count;
            }
        }

        $this->info("完了: 取得試行={$done} 保存動画={$saved}件 動画ゼロ={$empty} skip(空マーカー)={$skipped}");

        return self::SUCCESS;
    }

    private function fetchForModel(BikeModel $model, string $apiKey): int
    {
        $query = ($model->manufacturer->name ?? '')." {$model->name} レビュー";

        try {
            $response = Http::timeout(10)->get('https://www.googleapis.com/youtube/v3/search', [
                'part' => 'snippet',
                'q' => trim($query),
                'type' => 'video',
                'order' => 'relevance',
                'maxResults' => 3,
                'hl' => 'ja',
                'regionCode' => 'JP',
                'key' => $apiKey,
            ]);

            if ($response->status() === 403) {
                Log::warning('YouTube API クォータ超過 (youtube:refresh)', ['model' => $model->name]);
                $this->quotaExceeded = true;

                return -1;
            }

            if ($response->failed()) {
                Log::warning('YouTube API エラー (youtube:refresh)', [
                    'model' => $model->name,
                    'status' => $response->status(),
                ]);

                return -1;
            }

            $items = $response->json('items', []);
            $count = 0;

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
                $count++;
            }

            return $count;
        } catch (\Throwable $e) {
            Log::warning('YouTube API 例外 (youtube:refresh)', [
                'model' => $model->name,
                'message' => $e->getMessage(),
            ]);

            return -1;
        }
    }
}
