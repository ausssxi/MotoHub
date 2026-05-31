<?php

declare(strict_types=1);

namespace App\Services\Bike;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class BikeNewsService
{
    public function fetch(string $query, int $limit = 5, ?int $modelId = null): array
    {
        // 取り違え防止: 日本語名はStr::slugで落ちて衝突する（bike_news_650 等）ため、
        // model_idがあればそれをキーに使う。無ければ従来のslugにフォールバック。
        $cacheKey = $modelId !== null
            ? "bike_news_model_{$modelId}"
            : 'bike_news_' . str($query)->slug();

        return Cache::remember($cacheKey, 86400, function () use ($query, $limit) {
            try {
                $url = 'https://news.google.com/rss/search?' . http_build_query([
                    'q'    => $query,
                    'hl'   => 'ja',
                    'gl'   => 'JP',
                    'ceid' => 'JP:ja',
                ]);

                $response = Http::timeout(2)->get($url);

                if ($response->failed()) {
                    return [];
                }

                $xml = @simplexml_load_string($response->body());
                if ($xml === false || !isset($xml->channel->item)) {
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
                        'title'  => $title,
                        'url'    => $link,
                        'source' => $source,
                        'date'   => date('Y/m/d', strtotime((string) $item->pubDate)),
                        'image'  => $image,
                    ];
                }

                return $items;
            } catch (\Throwable) {
                return [];
            }
        });
    }

    private function extractImage(\SimpleXMLElement $item): ?string
    {
        // 1. <media:content> からサムネイル取得
        $media = $item->children('http://search.yahoo.com/mrss/');
        if (isset($media->group->content)) {
            foreach ($media->group->content as $content) {
                $attrs = $content->attributes();
                if (!empty($attrs['url'])) {
                    return (string) $attrs['url'];
                }
            }
        }
        if (isset($media->content)) {
            $attrs = $media->content->attributes();
            if (!empty($attrs['url'])) {
                return (string) $attrs['url'];
            }
        }
        if (isset($media->thumbnail)) {
            $attrs = $media->thumbnail->attributes();
            if (!empty($attrs['url'])) {
                return (string) $attrs['url'];
            }
        }

        // 2. <enclosure> タグ
        if (isset($item->enclosure)) {
            $attrs = $item->enclosure->attributes();
            $type = (string) ($attrs['type'] ?? '');
            if (str_starts_with($type, 'image/') && !empty($attrs['url'])) {
                return (string) $attrs['url'];
            }
        }

        // 3. RSSにサムネが無い場合は画像なし。
        //    各記事ページへの同期og:imageスクレイプ(最大5件×3s)はコールドスパイクの主因のため廃止。
        return null;
    }
}
