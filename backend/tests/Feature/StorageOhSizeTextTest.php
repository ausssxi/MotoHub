<?php

declare(strict_types=1);

use App\Services\RentalGarage\Scrapers\StorageOhScraper;

/*
 * StorageOhScraper の size_text 整形回帰テスト。
 *
 * 一部店舗は高さ（まれに幅/奥行も）を 0.00m で掲載しており、そのまま出すと画面に
 * 「高さ0.00m」と実在しない寸法が表示されていた。0/取得不可の辺は size_text に含めず、
 * 3辺すべてが 0/取得不可なら size_text は null にする（空文字ではなく null）。
 */

/**
 * parseDetail が要求する最小限の詳細HTML（JSON-LD + バイク区画寸法1行）を組み立てる。
 * ×・（税込）は全角（スクレイパーの正規表現に合わせる）。$w/$d/$h は寸法(m)の文字列。
 */
function storageohDetailHtml(string $w, string $d, string $h): string
{
    $jsonLd = json_encode([
        '@type' => 'SelfStorage',
        'name' => 'テスト北新宿トランクルーム',
        'url' => 'https://www.storageoh.jp/search/detail/9001',
        'address' => ['addressRegion' => '東京都', 'addressLocality' => '新宿区', 'streetAddress' => '1-1-1'],
        'priceRange' => '11000 - 11000',
    ], JSON_UNESCAPED_UNICODE);

    $unit = "屋内バイク1B1階幅{$w}m×奥行{$d}m×高さ{$h}m月額賃料（税込）11,000円";

    return '<html><head><script type="application/ld+json">'.$jsonLd.'</script></head>'
        .'<body>'.$unit.'</body></html>';
}

function storageohParse(string $w, string $d, string $h): ?array
{
    $s = new StorageOhScraper;
    $m = new ReflectionMethod($s, 'parseDetail');
    $m->setAccessible(true);

    return $m->invoke($s, storageohDetailHtml($w, $d, $h), 'https://www.storageoh.jp/search/detail/9001', 'indoor');
}

it('高さが0のとき size_text に「高さ」を含めない', function () {
    $row = storageohParse('1.15', '2.60', '0.00');

    expect($row)->not->toBeNull()
        ->and($row['size_text'])->toBe('幅1.15m×奥行2.60m')
        ->and($row['size_text'])->not->toContain('高さ')
        ->and($row['size_text'])->not->toContain('0.00');
});

it('幅・奥行・高さがすべて0のとき size_text は null（空文字ではない）', function () {
    $row = storageohParse('0.00', '0.00', '0.00');

    expect($row)->not->toBeNull()
        ->and($row['size_text'])->toBeNull();
});

it('3辺すべて有効なら従来どおり全辺を出す', function () {
    $row = storageohParse('1.22', '2.40', '1.88');

    expect($row['size_text'])->toBe('幅1.22m×奥行2.40m×高さ1.88m');
});

it('formatBikeDimensions: 0/空の辺を除外し、全滅なら null', function () {
    $s = new StorageOhScraper;
    $m = new ReflectionMethod($s, 'formatBikeDimensions');
    $m->setAccessible(true);

    expect($m->invoke($s, '1.15', '2.60', '0.00'))->toBe('幅1.15m×奥行2.60m')
        ->and($m->invoke($s, '0.00', '2.39', '1.50'))->toBe('奥行2.39m×高さ1.50m')
        ->and($m->invoke($s, '0.00', '0.00', '0.00'))->toBeNull()
        ->and($m->invoke($s, '', '', ''))->toBeNull();
});
