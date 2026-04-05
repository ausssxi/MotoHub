<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BikeModel;
use App\Models\BikeNews;
use App\Models\Manufacturer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class FetchBikeNews extends Command
{
    protected $signature = 'news:fetch';
    protected $description = 'Google News RSSからバイク関連ニュースを取得してDBに保存';

    /** @var array<string, int> メーカー名 => ID のキャッシュ */
    private array $manufacturerMap = [];

    /** @var array<string, array{id: int, manufacturer_id: int|null}> 車種名 => データのキャッシュ */
    private array $modelMap = [];

    public function handle(): void
    {
        $this->info('バイクニュースの取得を開始します...');

        // メーカー・車種マッピングを事前ロード
        $this->loadMappings();

        // 汎用クエリ4件
        $queries = [
            'バイク 新型',
            'バイク ニュース',
            'オートバイ 新車',
            'バイク モデルチェンジ',
        ];

        // 人気車種Top50のニュースも取得
        $popularModels = BikeModel::withCount('listings')
            ->having('listings_count', '>', 0)
            ->orderByDesc('listings_count')
            ->limit(50)
            ->get();

        foreach ($popularModels as $model) {
            $makerName = $model->manufacturer?->name ?? '';
            $queries[] = "{$makerName} {$model->name} バイク";
        }

        $totalSaved = 0;

        foreach ($queries as $query) {
            $items = $this->fetchRss($query, 10);

            foreach ($items as $item) {
                $result = $this->saveNews($item);
                if ($result) {
                    $totalSaved++;
                }
            }
        }

        $this->info("完了: {$totalSaved} 件のニュースを保存/更新しました。");
    }

    private function loadMappings(): void
    {
        // メーカーマッピング（日本語名のみ）
        Manufacturer::all()->each(function (Manufacturer $m) {
            $this->manufacturerMap[$m->name] = $m->id;
        });

        // 車種マッピング（人気車種200件に絞り、nameをキーにする）
        $this->modelMap = BikeModel::with('manufacturer')
            ->withCount('listings')
            ->orderByDesc('listings_count')
            ->limit(200)
            ->get()
            ->mapWithKeys(fn (BikeModel $m) => [
                $m->name => ['id' => $m->id, 'manufacturer_id' => $m->manufacturer_id],
            ])
            ->toArray();
    }

    /**
     * Google News RSSからニュースを取得（BikeNewsService::fetch のロジックを再利用）
     */
    private function fetchRss(string $query, int $limit = 10): array
    {
        try {
            $url = 'https://news.google.com/rss/search?' . http_build_query([
                'q'    => $query,
                'hl'   => 'ja',
                'gl'   => 'JP',
                'ceid' => 'JP:ja',
            ]);

            $response = Http::timeout(10)->get($url);

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
                $pubDate = (string) $item->pubDate;

                $items[] = [
                    'title'        => $title,
                    'url'          => $link,
                    'source'       => $source,
                    'thumbnail_url' => $image,
                    'published_at' => $pubDate ? date('Y-m-d H:i:s', strtotime($pubDate)) : null,
                ];
            }

            return $items;
        } catch (\Throwable $e) {
            $this->warn("RSS取得エラー ({$query}): {$e->getMessage()}");
            return [];
        }
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

        return null;
    }

    private function saveNews(array $item): bool
    {
        // 自動タグ付け
        $bikeModelId = null;
        $manufacturerId = null;

        // 車種名でマッチ（より具体的なので先にチェック）
        foreach ($this->modelMap as $name => $data) {
            if (mb_strlen($name) >= 2 && str_contains($item['title'], $name)) {
                $bikeModelId = $data['id'];
                $manufacturerId = $data['manufacturer_id'];
                break;
            }
        }

        // メーカー名でマッチ（車種が見つからなかった場合）
        if ($manufacturerId === null) {
            foreach ($this->manufacturerMap as $name => $id) {
                if (mb_strlen($name) >= 2 && str_contains($item['title'], $name)) {
                    $manufacturerId = $id;
                    break;
                }
            }
        }

        $news = BikeNews::updateOrCreate(
            ['url' => $item['url']],
            [
                'title'           => $item['title'],
                'source'          => $item['source'],
                'thumbnail_url'   => $item['thumbnail_url'],
                'published_at'    => $item['published_at'],
                'bike_model_id'   => $bikeModelId,
                'manufacturer_id' => $manufacturerId,
            ]
        );

        return $news->wasRecentlyCreated;
    }
}
