<?php

declare(strict_types=1);

use App\Services\Bike\ListingSearchService;
use Illuminate\Support\Facades\Cache;

/**
 * 回帰防止: getFilteredCount() は宣言どおり必ず int を返す。
 *
 * 旧バグ: Redisキャッシュ命中時にキャッシュ値が string で返り、
 * `: int` 宣言と衝突して TypeError。これが search() の0件時relaxed提案で発火し、
 * landing() の catch に伝播して area×model LP が 301 で離脱→空ページ化していた。
 * (ListingSearchService.php / 本番ログ "Landing search failed" の主因)
 *
 * cache-hit を再現するため、closure を実行させないよう同一キーに文字列を仕込む
 * → 計算経路(Meilisearch/DB)を一切踏まずに型のみ検証する。
 */

function filteredCountCacheKey($k, $p, $f): string
{
    return 'search_count_' . md5(json_encode([$k, $p, $f]));
}

it('returns an int even when the cache holds a string (redis hit)', function () {
    $service = app(ListingSearchService::class);

    $k = 'cfモト';
    $p = '群馬県';
    $f = [];

    // Redisが文字列で返す状況を再現（cache hit → closureは実行されない）
    Cache::put(filteredCountCacheKey($k, $p, $f), '5', 600);

    $count = $service->getFilteredCount($k, $p, $f);

    expect($count)->toBeInt();
    expect($count)->toBe(5);
});

it('coerces a non-numeric cached value to int 0 without throwing', function () {
    $service = app(ListingSearchService::class);

    $k = 'z125pro';
    $p = '京都府';
    $f = ['min_displacement' => 50];

    Cache::put(filteredCountCacheKey($k, $p, $f), '', 600);

    $count = $service->getFilteredCount($k, $p, $f);

    expect($count)->toBeInt();
    expect($count)->toBe(0);
});
