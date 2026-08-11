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

    /** クォータ超過を検知したときの HTTP ステータス（403 or 429） */
    private ?int $quotaStatus = null;

    public function handle(): int
    {
        $apiKey = config('services.youtube.api_key');
        if (!$apiKey) {
            $this->error('YOUTUBE_API_KEY が設定されていません');
            return self::FAILURE;
        }

        $days = (int) $this->option('days');
        $threshold = now()->subDays($days);

        // 古い動画を持つ車種を、最後に更新した日時の古い順に取得する。
        // クォータ超過で途中打ち切りになっても、次回の実行が続きから回るようにするため。
        // （refreshForModel は対象車種の動画を作り直すので、処理済みの車種は updated_at が
        //   新しくなり threshold の外へ出る。未処理の車種だけが先頭に残る）
        $models = BikeModel::query()
            ->join('bike_model_videos', 'bike_models.id', '=', 'bike_model_videos.bike_model_id')
            ->select('bike_models.*')
            ->groupBy('bike_models.id')
            ->havingRaw('MIN(bike_model_videos.updated_at) < ?', [$threshold])
            ->orderByRaw('MIN(bike_model_videos.updated_at) ASC')
            ->get();

        if ($models->isEmpty()) {
            $this->info("{$days}日以上前の動画はありません");
            return self::SUCCESS;
        }

        $total = $models->count();
        $this->info("再取得対象: {$total}車種（{$days}日以上前・更新が古い順）");

        $processed = 0;

        foreach ($models as $i => $model) {
            $num = $i + 1;
            $count = $this->refreshForModel($model, $apiKey);
            $processed++;

            if ($count >= 0) {
                $this->line("[{$num}/{$total}] {$model->name}: {$count}件更新");
            }

            // クォータ超過後はリクエストを投げても成功しない。ここで打ち切り、
            // ログは1件ごとではなくこの1行だけ出す。
            if ($this->quotaExceeded) {
                Log::warning('YouTube APIクォータ超過のため処理を打ち切り (youtube:refresh-videos)', [
                    'status' => $this->quotaStatus,
                    'processed' => $processed,
                    'total' => $total,
                    'stopped_at' => $model->name,
                    'updated' => $this->totalUpdated,
                ]);
                $this->warn("クォータ超過（HTTP {$this->quotaStatus}）のため {$processed}/{$total} 件目で処理を打ち切りました");
                break;
            }
        }

        $this->info("完了: {$processed}/{$total}車種処理、{$this->totalUpdated}件の動画を更新");

        // クォータ超過による打ち切りは想定内なので正常終了扱いにする。
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

            // 403=quotaExceeded / 429=rateLimitExceeded。どちらも以降のリクエストは
            // 成功しないので打ち切る。ここではログを出さず、打ち切り時に1行だけ出す。
            if ($response->status() === 403 || $response->status() === 429) {
                $this->quotaExceeded = true;
                $this->quotaStatus = $response->status();
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
