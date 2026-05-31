<?php

declare(strict_types=1);

namespace App\Services\Bike;

use App\Models\BikeModel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class BikeNewsService
{
    private const CACHE_TTL = 604800;      // 7日（parts/youtubeと揃えてローテ）

    private const EMPTY_CACHE_TTL = 86400; // 空結果は24時間で再試行

    /**
     * 車種別ニュースのキャッシュキー
     */
    public static function cacheKey(int $modelId): string
    {
        return "bike_news_model_{$modelId}";
    }

    /**
     * 車種別ニュースをキャッシュから読むだけ（render用・read-only）
     *
     * ミス時は [] を返し、RSSへは一切アクセスしない。
     * 実fetchは news:refresh コマンド（refreshForModel）が裏方で担う。
     */
    public function getForModel(BikeModel $model): array
    {
        return Cache::get(self::cacheKey($model->id), []);
    }

    /**
     * Google News RSSから車種別ニュースを取得しキャッシュへ書き込む（ジョブ用）
     *
     * render pathからは呼ばない（news:refresh専用）。
     */
    public function refreshForModel(BikeModel $model, int $limit = 5): array
    {
        $query = trim(($model->manufacturer->name ?? '')." {$model->name} バイク");
        $items = $this->fetchRss($query, $limit);

        // 空結果（取得失敗/ニュース無し）は24時間で再試行。取得できた分は7日保持。
        $ttl = empty($items) ? self::EMPTY_CACHE_TTL : self::CACHE_TTL;
        Cache::put(self::cacheKey($model->id), $items, $ttl);

        return $items;
    }

    private function fetchRss(string $query, int $limit): array
    {
        try {
            $url = 'https://news.google.com/rss/search?'.http_build_query([
                'q' => $query,
                'hl' => 'ja',
                'gl' => 'JP',
                'ceid' => 'JP:ja',
            ]);

            $response = Http::timeout(2)->get($url);

            if ($response->failed()) {
                return [];
            }

            $xml = @simplexml_load_string($response->body());
            if ($xml === false || ! isset($xml->channel->item)) {
                return [];
            }

            $items = [];
            foreach ($xml->channel->item as $item) {
                if (count($items) >= $limit) {
                    break;
                }

                $title = (string) $item->title;
                $source = '';
                if (str_contains($title, ' - ')) {
                    $parts = explode(' - ', $title);
                    $source = array_pop($parts);
                    $title = implode(' - ', $parts);
                }

                $link = (string) $item->link;
                $image = $this->extractImage($item);

                $items[] = [
                    'title' => $title,
                    'url' => $link,
                    'source' => $source,
                    'date' => date('Y/m/d', strtotime((string) $item->pubDate)),
                    'image' => $image,
                ];
            }

            return $items;
        } catch (\Throwable) {
            return [];
        }
    }

    private function extractImage(\SimpleXMLElement $item): ?string
    {
        // 1. <media:content> からサムネイル取得
        $media = $item->children('http://search.yahoo.com/mrss/');
        if (isset($media->group->content)) {
            foreach ($media->group->content as $content) {
                $attrs = $content->attributes();
                if (! empty($attrs['url'])) {
                    return (string) $attrs['url'];
                }
            }
        }
        if (isset($media->content)) {
            $attrs = $media->content->attributes();
            if (! empty($attrs['url'])) {
                return (string) $attrs['url'];
            }
        }
        if (isset($media->thumbnail)) {
            $attrs = $media->thumbnail->attributes();
            if (! empty($attrs['url'])) {
                return (string) $attrs['url'];
            }
        }

        // 2. <enclosure> タグ
        if (isset($item->enclosure)) {
            $attrs = $item->enclosure->attributes();
            $type = (string) ($attrs['type'] ?? '');
            if (str_starts_with($type, 'image/') && ! empty($attrs['url'])) {
                return (string) $attrs['url'];
            }
        }

        // 3. RSSにサムネが無い場合は画像なし。
        //    各記事ページへの同期og:imageスクレイプ(最大5件×3s)はコールドスパイクの主因のため廃止。
        return null;
    }
}
