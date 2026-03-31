<?php

declare(strict_types=1);

namespace App\Services\Bike;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class BikeNewsService
{
    public function fetch(string $query, int $limit = 5): array
    {
        $slug = str($query)->slug();
        $cacheKey = "bike_news_{$slug}";

        return Cache::remember($cacheKey, 86400, function () use ($query, $limit) {
            try {
                $url = 'https://news.google.com/rss/search?' . http_build_query([
                    'q'    => $query,
                    'hl'   => 'ja',
                    'gl'   => 'JP',
                    'ceid' => 'JP:ja',
                ]);

                $response = Http::timeout(5)->get($url);

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
                    $image = $this->extractImage($item, $link);

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

    private function extractImage(\SimpleXMLElement $item, string $articleUrl): ?string
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

        // 3. OGP画像をフォールバック取得
        return $this->fetchOgImage($articleUrl);
    }

    private function fetchOgImage(string $url): ?string
    {
        try {
            $response = Http::timeout(3)
                ->withHeaders(['User-Agent' => 'MotoHub/1.0'])
                ->get($url);

            if ($response->failed()) {
                return null;
            }

            $body = $response->body();
            if (preg_match('/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']/i', $body, $m)) {
                return $m[1];
            }
            if (preg_match('/<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:image["\']/i', $body, $m)) {
                return $m[1];
            }

            return null;
        } catch (\Throwable) {
            return null;
        }
    }
}
