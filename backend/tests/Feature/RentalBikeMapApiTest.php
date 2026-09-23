<?php

use App\Models\RentalBikeShop;

/**
 * ライダースマップ「レンタルバイク」レイヤーの API（/api/rental-bikes）。
 * ★RentalGarageApiController と同様の bbox 検索。is_active=true かつ座標ありのみ返す。
 * ★画像・紹介文・在庫は扱わない（返却キーにも含めない）。
 */

/** テスト用の店舗を1件作る（dedup_key はモデルのキー生成に合わせる）。 */
function makeMapRentalBikeShop(array $attrs): RentalBikeShop
{
    $slug = $attrs['company_slug'] ?? 'bikecenter';
    $name = $attrs['name'] ?? '店舗';
    $address = $attrs['address'] ?? '住所';

    return RentalBikeShop::create(array_merge([
        'company' => 'レンタルバイクセンター',
        'company_slug' => $slug,
        'external_id' => null,
        'name' => $name,
        'address' => $address,
        'tel' => null,
        'official_url' => 'https://example.test/shop',
        'is_active' => true,
        'fetched_at' => now(),
        'dedup_key' => RentalBikeShop::makeDedupKey($slug, null, $name, $address),
    ], $attrs));
}

// 東京付近を囲む bbox。
const BBOX = ['ne_lat' => 35.9, 'ne_lng' => 140.0, 'sw_lat' => 35.5, 'sw_lng' => 139.5];

it('returns only active shops with coordinates inside the bounding box', function () {
    // 圏内・公開 → 返る。
    makeMapRentalBikeShop([
        'company' => 'レンタル819', 'company_slug' => 'rental819', 'name' => '東京店',
        'address' => '東京都千代田区丸の内1-1', 'tel' => '03-1234-5678',
        'latitude' => 35.6812, 'longitude' => 139.7671,
    ]);
    // 圏外（大阪）→ 返らない。
    makeMapRentalBikeShop([
        'name' => '大阪店', 'address' => '大阪府大阪市北区1-1',
        'latitude' => 34.7025, 'longitude' => 135.4959,
    ]);
    // 圏内だが非公開 → 返らない。
    makeMapRentalBikeShop([
        'name' => '非公開店', 'address' => '東京都新宿区西新宿1-1', 'is_active' => false,
        'latitude' => 35.6896, 'longitude' => 139.6917,
    ]);
    // 圏内だが座標なし → 返らない。
    makeMapRentalBikeShop([
        'name' => '座標なし店', 'address' => '東京都港区1-1',
        'latitude' => null, 'longitude' => null,
    ]);

    $res = $this->getJson(route('api.rental_bikes', BBOX));

    $res->assertOk()->assertJsonCount(1);
    $res->assertJsonPath('0.name', '東京店')
        ->assertJsonPath('0.company', 'レンタル819')
        ->assertJsonPath('0.tel', '03-1234-5678');
});

it('does not expose image-related keys', function () {
    makeMapRentalBikeShop([
        'name' => '東京店', 'address' => '東京都千代田区丸の内1-1',
        'latitude' => 35.6812, 'longitude' => 139.7671,
    ]);

    $item = $this->getJson(route('api.rental_bikes', BBOX))->assertOk()->json('0');

    $banned = ['image', 'image_url', 'images', 'photo', 'photos', 'logo', 'thumbnail', 'thumb', 'img'];
    expect(array_intersect($banned, array_keys($item)))->toBe([]);
});

it('validates the required bbox params', function () {
    $this->getJson(route('api.rental_bikes'))->assertStatus(422);
});
