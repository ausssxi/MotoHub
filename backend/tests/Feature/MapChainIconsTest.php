<?php

declare(strict_types=1);

use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function mapShop(string $name, float $lat = 35.68, float $lng = 139.76): Shop
{
    $s = new Shop;
    $s->forceFill([
        'name' => $name,
        'address' => 'addr-'.uniqid(),
        'latitude' => $lat,
        'longitude' => $lng,
        'prefecture' => '東京都',
        'source' => Shop::SOURCE_SCRAPER,
    ])->save();

    return $s;
}

// ---- チェーン判定ヘルパ（config/bike.php pattern・チェーン横断ページと同一） ----

it('resolves the chain slug from a shop name (null for non-chain)', function () {
    expect(Shop::chainSlug('レッドバロン府中'))->toBe('red-baron')
        ->and(Shop::chainSlug('バイク王 なんば店'))->toBe('bikeo')
        ->and(Shop::chainSlug('カワサキ プラザ横浜'))->toBeNull()   // 半角空白入りは pattern 不一致
        ->and(Shop::chainSlug('カワサキプラザ横浜'))->toBe('kawasaki-plaza')
        ->and(Shop::chainSlug('街の個人バイク店'))->toBeNull()
        ->and(Shop::chainSlug(null))->toBeNull();
});

// ---- マップAPIが各shopに chain slug を付与 ----

it('tags each shop with its chain slug in the /shops/api/area payload', function () {
    mapShop('レッドバロン東京');
    mapShop('バイク館 立川店');
    mapShop('街の個人バイク店');

    $rows = collect($this->getJson('/shops/api/area?ne_lat=36&ne_lng=140&sw_lat=35&sw_lng=139')->assertOk()->json());

    expect($rows->firstWhere('name', 'レッドバロン東京')['chain'])->toBe('red-baron')
        ->and($rows->firstWhere('name', 'バイク館 立川店')['chain'])->toBe('bikekan')
        ->and($rows->firstWhere('name', '街の個人バイク店')['chain'])->toBeNull(); // 非チェーンは null＝従来アイコン
});

// ===== v2: 表記ゆれ吸収・追加チェーン・メーカー正規店・独立店 =====

function mapDealerShop(string $name, array $tags, float $lat = 35.68, float $lng = 139.76): Shop
{
    $s = new Shop;
    $s->forceFill([
        'name' => $name, 'address' => 'addr-'.uniqid(),
        'latitude' => $lat, 'longitude' => $lng, 'prefecture' => '東京都',
        'source' => Shop::SOURCE_SCRAPER, 'service_tags' => $tags,
    ])->save();

    return $s;
}

it('absorbs notation variants and resolves the added reverse-auto chain', function () {
    // 支店名・英語表記・空白・全角/半角ゆれを吸収
    expect(Shop::chainSlug('リバースオート八王子店'))->toBe('reverse-auto')
        ->and(Shop::chainSlug('REVERSE AUTO 甲府'))->toBe('reverse-auto')
        ->and(Shop::chainSlug('Reverse Auto'))->toBe('reverse-auto')
        ->and(Shop::chainSlug('バイカーズステーションSOX 三鷹'))->toBe('sox') // 英字別名
        ->and(Shop::chainSlug('SBS 鈴木'))->toBe('sbs')                       // 半角→全角pattern吸収
        ->and(Shop::chainSlug('ＳＢＳ 鈴木'))->toBe('sbs')                     // 全角
        ->and(Shop::chainSlug('街の個人バイク店'))->toBeNull();
});

it('detects maker authorized dealers from 正規店 badges, chain takes priority', function () {
    expect(Shop::makerDealer(['ハーレーダビッドソン正規店'], null))->toBe('ハーレーダビッドソン')
        ->and(Shop::makerDealer(['認証工場', 'トライアンフ正規店'], null))->toBe('トライアンフ')
        ->and(Shop::makerDealer(['HONDA正規店'], 'honda-dream'))->toBeNull()  // チェーン優先＝二重分類しない
        ->and(Shop::makerDealer(['認証工場', '車検受付'], null))->toBeNull()   // 正規店バッジ無し＝その他
        ->and(Shop::makerDealer(null, null))->toBeNull();
});

it('tags chain / maker_dealer / other in the map API and hides raw service_tags', function () {
    mapDealerShop('ハーレーダビッドソン東京', ['ハーレーダビッドソン正規店']);
    mapShop('リバースオート府中');                     // 追加チェーン（service_tagsなし）
    mapDealerShop('個人のバイクショップ', ['認証工場']); // 正規店バッジ無し＝その他

    $rows = collect($this->getJson('/shops/api/area?ne_lat=36&ne_lng=140&sw_lat=35&sw_lng=139')->assertOk()->json());

    $hd = $rows->firstWhere('name', 'ハーレーダビッドソン東京');
    $ra = $rows->firstWhere('name', 'リバースオート府中');
    $other = $rows->firstWhere('name', '個人のバイクショップ');

    expect($hd['chain'])->toBeNull()
        ->and($hd['maker_dealer'])->toBe('ハーレーダビッドソン')
        ->and($ra['chain'])->toBe('reverse-auto')
        ->and($ra['maker_dealer'])->toBeNull()
        ->and($other['chain'])->toBeNull()
        ->and($other['maker_dealer'])->toBeNull()
        ->and($hd)->not->toHaveKey('service_tags'); // 生バッジはクライアントに送らない
});
