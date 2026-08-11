<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BikeModel;
use App\Models\BikeNews;
use App\Models\Manufacturer;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FetchBikeNews extends Command
{
    protected $signature = 'news:fetch';
    protected $description = 'Google News RSSからバイク関連ニュースを取得してDBに保存';

    /** saveNews() の結果 */
    private const RESULT_CREATED = 'created';

    private const RESULT_UPDATED = 'updated';

    private const RESULT_DUPLICATE = 'duplicate';

    private const RESULT_FAILED = 'failed';

    /** MySQL: 一意制約違反（SQLSTATE 23000 / errno 1062） */
    private const SQLSTATE_INTEGRITY_CONSTRAINT_VIOLATION = '23000';

    private const MYSQL_ERRNO_DUPLICATE_ENTRY = 1062;

    /** @var array<string, int> メーカー名 => ID のキャッシュ */
    private array $manufacturerMap = [];

    /** @var array<string, array{id: int, manufacturer_id: int|null}> 車種名 => データのキャッシュ */
    private array $modelMap = [];

    public function handle(): int
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

        $fetched = 0;
        $created = 0;
        $updated = 0;
        $duplicated = 0;
        $rssFailed = 0;
        $saveFailed = 0;

        foreach ($queries as $query) {
            $items = $this->fetchRss($query, 10);

            // null は取得そのものの失敗（空配列＝0件ヒットとは区別する）
            if ($items === null) {
                $rssFailed++;

                continue;
            }

            $fetched += count($items);

            foreach ($items as $item) {
                switch ($this->saveNews($item)) {
                    case self::RESULT_CREATED:
                        $created++;
                        break;
                    case self::RESULT_UPDATED:
                        $updated++;
                        break;
                    case self::RESULT_DUPLICATE:
                        $duplicated++;
                        break;
                    default:
                        $saveFailed++;
                        break;
                }
            }
        }

        $failed = $rssFailed + $saveFailed;

        $this->info(sprintf(
            '完了: 取得 %d 件 / 新規登録 %d 件 / 重複スキップ %d 件 / 失敗 %d 件',
            $fetched,
            $created,
            $duplicated,
            $failed
        ));
        $this->line(sprintf(
            '  内訳: 既存更新 %d 件 / RSS取得失敗 %d クエリ（全 %d クエリ中） / 保存失敗 %d 件',
            $updated,
            $rssFailed,
            count($queries),
            $saveFailed
        ));

        if ($failed > 0) {
            Log::warning('news:fetch に失敗が含まれます', [
                'fetched' => $fetched,
                'created' => $created,
                'updated' => $updated,
                'duplicated' => $duplicated,
                'rss_failed' => $rssFailed,
                'save_failed' => $saveFailed,
                'queries' => count($queries),
            ]);
        }

        // 重複スキップは異常ではない（RSSは同じ記事URLを繰り返し配信する）ので成功に数える。
        // 1件も成功しなかったときだけ異常終了する。
        $succeeded = $created + $updated + $duplicated;
        if ($succeeded === 0 && $failed > 0) {
            $this->error('全件失敗しました。');

            return self::FAILURE;
        }

        return self::SUCCESS;
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
     *
     * @return array<int, array<string, mixed>>|null 取得に失敗した場合は null。
     *                                               空配列は「取得できたが0件」を意味する。
     */
    private function fetchRss(string $query, int $limit = 10): ?array
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
                Log::warning('news:fetch RSS取得に失敗', [
                    'query' => $query,
                    'status' => $response->status(),
                ]);
                $this->warn("RSS取得失敗 ({$query}): HTTP {$response->status()}");

                return null;
            }

            $xml = @simplexml_load_string($response->body());
            if ($xml === false || !isset($xml->channel->item)) {
                Log::warning('news:fetch RSSの解析に失敗', ['query' => $query]);
                $this->warn("RSS解析失敗 ({$query})");

                return null;
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
            Log::warning('news:fetch RSS取得で例外', [
                'query' => $query,
                'message' => $e->getMessage(),
            ]);
            $this->warn("RSS取得エラー ({$query}): {$e->getMessage()}");

            return null;
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

        // 3. OGP画像をフォールバック取得
        return $this->fetchOgImage($articleUrl);
    }

    private function fetchOgImage(string $url): ?string
    {
        try {
            $response = Http::timeout(5)
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

    /**
     * ニュース1件を保存する。
     *
     * bike_news.url の一意インデックスは TEXT 列に対する先頭191文字のプレフィックス
     * （bike_news_url_unique … url(191)）である一方、updateOrCreate の照合は URL 全体で行う。
     * Google News の記事URLは191文字を大きく超えるため、「先頭191文字は同じだが全体は異なる」
     * URLでは照合が空振りしたうえで INSERT が 1062 に当たる。updateOrCreate だけでは
     * 防ぎきれないので、1062 を明示的に捕まえて重複スキップに計上する。
     *
     * @return self::RESULT_*
     */
    private function saveNews(array $item): string
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

        // サムネイルのフォールバック: RSS → bike_model画像 → メーカーロゴ
        $thumbnailUrl = $item['thumbnail_url'];
        if (!$thumbnailUrl && $bikeModelId) {
            $model = BikeModel::find($bikeModelId);
            if ($model) {
                $thumbnailUrl = $model->image_url;
            }
        }
        if (!$thumbnailUrl && $manufacturerId) {
            $mfr = Manufacturer::find($manufacturerId);
            if ($mfr && $mfr->local_logo_path) {
                $thumbnailUrl = asset('storage/' . ltrim($mfr->local_logo_path, '/'));
            } elseif ($mfr && $mfr->logo_url) {
                $thumbnailUrl = $mfr->logo_url;
            }
        }

        try {
            $news = BikeNews::updateOrCreate(
                ['url' => $item['url']],
                [
                    'title'           => $item['title'],
                    'source'          => $item['source'],
                    'thumbnail_url'   => $thumbnailUrl,
                    'published_at'    => $item['published_at'],
                    'bike_model_id'   => $bikeModelId,
                    'manufacturer_id' => $manufacturerId,
                ]
            );

            return $news->wasRecentlyCreated ? self::RESULT_CREATED : self::RESULT_UPDATED;
        } catch (QueryException $e) {
            if ($this->isDuplicateEntry($e)) {
                // RSSは同じ記事URLを繰り返し配信するので、重複は異常ではなく通常。
                // バッチ全体を落とさず、スキップとして数えるだけにする。
                return self::RESULT_DUPLICATE;
            }

            // 一意制約違反以外のDBエラーは握りつぶさない（件数を数えてログに残す）
            Log::warning('news:fetch 保存に失敗（DBエラー）', [
                'url' => $item['url'],
                'sqlstate' => $e->getCode(),
                'message' => $e->getMessage(),
            ]);

            return self::RESULT_FAILED;
        } catch (\Throwable $e) {
            Log::warning('news:fetch 保存に失敗', [
                'url' => $item['url'],
                'message' => $e->getMessage(),
            ]);

            return self::RESULT_FAILED;
        }
    }

    /**
     * SQLSTATE 23000（整合性制約違反）かつ MySQL errno 1062（Duplicate entry）か。
     * 制約違反であれば何でも握りつぶす、という広い判定にはしない。
     */
    private function isDuplicateEntry(QueryException $e): bool
    {
        return (string) $e->getCode() === self::SQLSTATE_INTEGRITY_CONSTRAINT_VIOLATION
            && (int) ($e->errorInfo[1] ?? 0) === self::MYSQL_ERRNO_DUPLICATE_ENTRY;
    }
}
