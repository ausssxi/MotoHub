<?php

declare(strict_types=1);

namespace App\Services\Bike;

use App\Models\BikeModelVideo;
use Illuminate\Support\Facades\Cache;

class BikeYouTubeService
{
    /**
     * 車種の動画を read-only で返す（render用）。
     *
     * 読むのは BikeModelVideo（DB）＋ youtubeキャッシュのみ。
     * 両方無ければ [] を返す（動画欄なし＝グレースフル）。
     * YouTube Data API は一切叩かない（実fetchは youtube:refresh コマンド専用）。
     */
    public function getForModel(int $modelId): array
    {
        // 1. DB（BikeModelVideo）優先
        $dbVideos = BikeModelVideo::where('bike_model_id', $modelId)
            ->orderBy('sort_order')
            ->get();

        if ($dbVideos->isNotEmpty()) {
            return $dbVideos->map(fn ($v) => [
                'video_id' => $v->video_id,
                'title' => $v->title,
                'channel' => $v->channel_name,
                'thumbnail' => $v->thumbnail_url,
                'date' => $v->published_at?->format('Y/m/d') ?? '',
            ])->values()->all();
        }

        // 2. 旧API fallbackが残した youtubeキャッシュ（あれば）
        return Cache::get("youtube_model_{$modelId}", []);
    }
}
