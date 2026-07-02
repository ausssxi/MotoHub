<?php

use App\Models\Shop;
use App\Models\ShopAcceptanceReport;
use App\Models\ShopSubmission;
use App\Models\User;
use App\Services\Shop\ShopAreaService;
use App\Services\Shop\ShopSubmissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function makeSubmission(array $overrides = []): ShopSubmission
{
    return ShopSubmission::create(array_merge([
        'shop_name' => 'テスト整備',
        'prefecture' => '東京都',
        'city' => '世田谷区',
        'address' => '東京都世田谷区1-2-3',
        'phone' => '03-1234-5678',
        'service_tags' => ['認証工場', '車検受付'],
        'acceptance_flags' => ['accepts_other_store', 'walk_in_ok'],
        'comment' => 'よく見てもらえた',
        'submitter_name' => 'たろう',
        'ip_hash' => str_repeat('a', 64),
        'status' => ShopSubmission::STATUS_PENDING,
    ], $overrides));
}

// ---- submission endpoint ----

it('accepts an anonymous submission as pending', function () {
    $resp = $this->post('/shops/submit', [
        'shop_name' => '匿名モータース',
        'prefecture' => '東京都',
        'city' => '世田谷区',
        'submitter_name' => 'ゲスト',
        'fax_number' => '', // honeypot empty
    ]);

    $resp->assertRedirect(route('shops.submit.create'));
    $s = ShopSubmission::first();
    expect($s->shop_name)->toBe('匿名モータース')
        ->and($s->status)->toBe('pending')
        ->and($s->user_id)->toBeNull()
        ->and(strlen($s->ip_hash))->toBe(64)
        ->and($s->submitter_name)->toBe('ゲスト');
});

it('rejects a submission when the honeypot is filled', function () {
    $this->post('/shops/submit', [
        'shop_name' => 'スパム',
        'prefecture' => '東京都',
        'city' => '世田谷区',
        'fax_number' => 'bot-filled-this',
    ])->assertSessionHasErrors('fax_number');

    expect(ShopSubmission::count())->toBe(0);
});

it('rejects an invalid prefecture', function () {
    $this->post('/shops/submit', [
        'shop_name' => 'X', 'prefecture' => '存在しない県', 'city' => 'y', 'fax_number' => '',
    ])->assertSessionHasErrors('prefecture');
});

// ---- approve as new ----

it('approves as a new user shop with geocoding + auto acceptance report', function () {
    Http::fake(['*' => Http::response([['geometry' => ['coordinates' => [139.65, 35.64]]]], 200)]);

    $s = makeSubmission();
    $shop = app(ShopSubmissionService::class)->approveAsNew($s);

    expect($shop->source)->toBe(Shop::SOURCE_USER)
        ->and($shop->shop_type)->toBe('repair_only')
        ->and($shop->prefecture)->toBe('東京都')
        ->and($shop->service_tags)->toContain('認証工場')
        ->and(round($shop->latitude, 2))->toBe(35.64)   // coords[1]
        ->and(round($shop->longitude, 2))->toBe(139.65); // coords[0]

    $s->refresh();
    expect($s->status)->toBe('approved')->and($s->linked_shop_id)->toBe($shop->id);

    $report = ShopAcceptanceReport::where('shop_id', $shop->id)->first();
    expect($report)->not->toBeNull()
        ->and((bool) $report->is_approved)->toBeTrue()
        ->and((bool) $report->accepts_other_store)->toBeTrue()
        ->and((bool) $report->walk_in_ok)->toBeTrue()
        ->and((bool) $report->accepts_bring_in)->toBeFalse()
        ->and($report->submitter_name)->toBe('たろう');
});

it('skips geocoding when the address is empty', function () {
    Http::fake(['*' => Http::response([['geometry' => ['coordinates' => [139.65, 35.64]]]], 200)]);

    $s = makeSubmission(['address' => null]);
    $shop = app(ShopSubmissionService::class)->approveAsNew($s);

    expect($shop->latitude)->toBeNull()->and($shop->longitude)->toBeNull();
    Http::assertNothingSent();
});

// ---- merge ----

it('merges into an existing shop without changing its columns', function () {
    $existing = Shop::create([
        'name' => '既存バイク店', 'prefecture' => '東京都', 'city' => '世田谷区',
        'address' => '元の住所', 'phone' => '03-0000-0000', 'shop_type' => 'dealer', 'source' => Shop::SOURCE_SCRAPER,
    ]);
    $before = $existing->only(['name', 'address', 'phone', 'shop_type', 'source', 'service_tags']);

    $s = makeSubmission();
    app(ShopSubmissionService::class)->mergeInto($s, $existing);

    $existing->refresh();
    expect($existing->only(['name', 'address', 'phone', 'shop_type', 'source', 'service_tags']))->toBe($before);
    $s->refresh();
    expect($s->status)->toBe('merged')->and($s->linked_shop_id)->toBe($existing->id);
    expect(ShopAcceptanceReport::where('shop_id', $existing->id)->count())->toBe(1);
});

it('rejects a submission', function () {
    $s = makeSubmission();
    app(ShopSubmissionService::class)->reject($s);
    $s->refresh();
    expect($s->status)->toBe('rejected')->and($s->processed_at)->not->toBeNull();
});

// ---- duplicate candidates ----

it('finds duplicate candidates by normalized name and by phone', function () {
    Shop::create(['name' => '（株）テスト整備', 'prefecture' => '東京都', 'city' => '世田谷区', 'address' => 'a', 'shop_type' => 'dealer', 'source' => 'scraper']); // name match after normalize
    Shop::create(['name' => '全然ちがう店', 'prefecture' => '東京都', 'city' => '世田谷区', 'address' => 'b', 'phone' => '0312345678', 'shop_type' => 'dealer', 'source' => 'scraper']); // phone match
    Shop::create(['name' => '無関係', 'prefecture' => '東京都', 'city' => '世田谷区', 'address' => 'c', 'phone' => '0399999999', 'shop_type' => 'dealer', 'source' => 'scraper']); // no match

    $cands = app(ShopSubmissionService::class)->duplicateCandidates(makeSubmission());
    $names = $cands->pluck('name')->all();

    expect($names)->toContain('（株）テスト整備')   // normalized name
        ->and($names)->toContain('全然ちがう店')     // phone
        ->and($names)->not->toContain('無関係');
});

// ---- display merge + classify skip ----

it('shows a user shop on the repair city page and counts toward the gate', function () {
    for ($i = 1; $i <= 3; $i++) {
        Shop::create([
            'name' => "ユーザー整備{$i}", 'prefecture' => '東京都', 'city' => '世田谷区',
            'address' => "addr{$i}", 'latitude' => 35.6, 'longitude' => 139.6,
            'shop_type' => 'repair_only', 'source' => Shop::SOURCE_USER,
        ]);
    }
    expect(app(ShopAreaService::class)->getRepairShopCountForCity('東京都', '世田谷区'))->toBe(3);

    $resp = $this->get('/shops/repair/東京都/世田谷区');
    $resp->assertStatus(200)->assertSee('ユーザー整備1');

    // detail badge + 0-stock does not break
    $shop = Shop::where('source', Shop::SOURCE_USER)->first();
    $this->get("/shops/{$shop->id}")->assertStatus(200)->assertSee('ユーザー投稿による掲載');
});

// ---- A: duplicate-check endpoint ----

it('check endpoint returns candidates by name and phone', function () {
    Shop::create(['name' => '（株）テスト整備', 'prefecture' => '東京都', 'city' => '世田谷区', 'address' => 'a', 'shop_type' => 'dealer', 'source' => 'scraper']);
    Shop::create(['name' => '別店', 'prefecture' => '東京都', 'city' => '世田谷区', 'address' => 'b', 'phone' => '0312345678', 'shop_type' => 'dealer', 'source' => 'scraper']);
    Shop::create(['name' => '無関係', 'prefecture' => '東京都', 'city' => '世田谷区', 'address' => 'c', 'shop_type' => 'dealer', 'source' => 'scraper']);

    $resp = $this->getJson('/shops/submit/check?'.http_build_query([
        'shop_name' => 'テスト整備', 'prefecture' => '東京都', 'city' => '世田谷区', 'phone' => '03-1234-5678',
    ]));

    $resp->assertOk();
    $names = collect($resp->json('candidates'))->pluck('name')->all();
    expect($names)->toContain('（株）テスト整備')     // name match
        ->and($names)->toContain('別店')              // phone match
        ->and($names)->not->toContain('無関係');
    expect($resp->json('candidates.0'))->toHaveKeys(['id', 'name', 'address', 'matched_by', 'detail_url']);
});

it('check endpoint requires prefecture and city', function () {
    $this->getJson('/shops/submit/check?shop_name=x')->assertStatus(422);
});

// ---- B: official_site_url ----

it('approve routes the URL into official_site_url (not website_url)', function () {
    Http::fake(['*' => Http::response([], 200)]);
    $s = makeSubmission(['address' => null, 'website_url' => 'https://example-shop.jp']);
    $shop = app(ShopSubmissionService::class)->approveAsNew($s);
    expect($shop->official_site_url)->toBe('https://example-shop.jp')
        ->and($shop->website_url)->toBeNull();
});

it('merge fills empty official_site_url but preserves an existing one', function () {
    $empty = Shop::create(['name' => 'URL空店', 'prefecture' => '東京都', 'city' => '世田谷区', 'address' => 'a', 'shop_type' => 'dealer', 'source' => 'scraper']);
    $filled = Shop::create(['name' => 'URL有店', 'prefecture' => '東京都', 'city' => '世田谷区', 'address' => 'b', 'official_site_url' => 'https://existing.jp', 'shop_type' => 'dealer', 'source' => 'scraper']);

    app(ShopSubmissionService::class)->mergeInto(makeSubmission(['website_url' => 'https://new.jp']), $empty);
    app(ShopSubmissionService::class)->mergeInto(makeSubmission(['website_url' => 'https://new.jp']), $filled);

    expect($empty->fresh()->official_site_url)->toBe('https://new.jp')       // 空→充填
        ->and($filled->fresh()->official_site_url)->toBe('https://existing.jp'); // 既存→保持
});

it('merge with only a URL (no flags/comment) still completes', function () {
    $shop = Shop::create(['name' => 'X店', 'prefecture' => '東京都', 'city' => '世田谷区', 'address' => 'a', 'shop_type' => 'dealer', 'source' => 'scraper']);
    $s = makeSubmission(['acceptance_flags' => null, 'comment' => null, 'website_url' => 'https://only-url.jp']);

    app(ShopSubmissionService::class)->mergeInto($s, $shop);

    expect($s->fresh()->status)->toBe('merged')
        ->and($shop->fresh()->official_site_url)->toBe('https://only-url.jp')
        ->and(ShopAcceptanceReport::where('shop_id', $shop->id)->count())->toBe(0); // report無しでOK
});

it('detail page renders the official site link with rel="nofollow ugc noopener"', function () {
    $shop = Shop::create([
        'name' => 'リンク店', 'prefecture' => '東京都', 'city' => '世田谷区', 'address' => 'a',
        'latitude' => 35.6, 'longitude' => 139.6, 'official_site_url' => 'https://link-shop.jp',
        'shop_type' => 'repair_only', 'source' => Shop::SOURCE_USER,
    ]);

    $this->get("/shops/{$shop->id}")
        ->assertStatus(200)
        ->assertSee('rel="nofollow ugc noopener"', false)
        ->assertSee('https://link-shop.jp', false);
});

// ---- A-1: 主要都市チップ集計 ----

function seedRepairShops(string $pref, string $city, int $n): void
{
    for ($i = 1; $i <= $n; $i++) {
        Shop::create([
            'name' => "{$pref}{$city}整備{$i}", 'prefecture' => $pref, 'city' => $city,
            'address' => "addr{$i}", 'shop_type' => 'repair_only', 'source' => Shop::SOURCE_USER,
        ]);
    }
}

it('getRepairTopCitiesByPrefecture: per-pref top5, excludes <3, counts, single query', function () {
    seedRepairShops('東京都', '世田谷区', 5);
    seedRepairShops('東京都', '新宿区', 4);
    seedRepairShops('東京都', '港区', 3);
    seedRepairShops('東京都', '渋谷区', 2);      // <3 → 除外
    seedRepairShops('神奈川県', '横浜市', 3);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $top = app(ShopAreaService::class)->getRepairTopCitiesByPrefecture();
    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queryCount)->toBe(1); // N+1でない（1クエリで全県集計）

    $tokyo = collect($top['東京都'])->pluck('count', 'city')->all();
    expect(array_keys($tokyo))->toBe(['世田谷区', '新宿区', '港区'])   // 降順・渋谷区除外
        ->and($tokyo['世田谷区'])->toBe(5)
        ->and($top['神奈川県'][0])->toBe(['city' => '横浜市', 'count' => 3]);
});

it('repair index renders top-city chips linking to city pages', function () {
    seedRepairShops('東京都', '世田谷区', 3);

    $this->get('/shops/repair')
        ->assertStatus(200)
        ->assertSee('世田谷区')
        ->assertSee('/shops/repair/'.rawurlencode('東京都').'/'.rawurlencode('世田谷区'), false);
});

// ---- B: 公式URL提案 ----

it('suggest-url creates a target submission, server-fills name/pref/city, ignores spoofed form values', function () {
    $shop = Shop::create(['name' => '本当の店名', 'prefecture' => '東京都', 'city' => '世田谷区', 'address' => 'a', 'shop_type' => 'repair_only', 'source' => 'scraper']);

    $this->post("/shops/{$shop->id}/suggest-url", [
        'website_url' => 'https://real-shop.jp',
        'shop_name' => '改ざんされた名前', // フォーム値は無視されるべき
        'prefecture' => '大阪府',
        'fax_number' => '',
    ])->assertRedirect(route('shops.show', $shop));

    $sub = ShopSubmission::first();
    expect($sub->target_shop_id)->toBe($shop->id)
        ->and($sub->website_url)->toBe('https://real-shop.jp')
        ->and($sub->shop_name)->toBe('本当の店名')     // 対象店から（改ざん無視）
        ->and($sub->prefecture)->toBe('東京都')
        ->and($sub->status)->toBe('pending');
});

it('suggest-url rejects when honeypot filled', function () {
    $shop = Shop::create(['name' => 'X', 'prefecture' => '東京都', 'city' => '世田谷区', 'address' => 'a', 'shop_type' => 'repair_only', 'source' => 'scraper']);
    $this->post("/shops/{$shop->id}/suggest-url", ['website_url' => 'https://x.jp', 'fax_number' => 'bot'])
        ->assertSessionHasErrors('fax_number');
    expect(ShopSubmission::count())->toBe(0);
});

it('suggest-url is ignored when the shop already has an official site url', function () {
    $shop = Shop::create(['name' => 'X', 'prefecture' => '東京都', 'city' => '世田谷区', 'address' => 'a', 'official_site_url' => 'https://existing.jp', 'shop_type' => 'repair_only', 'source' => 'scraper']);
    $this->post("/shops/{$shop->id}/suggest-url", ['website_url' => 'https://new.jp', 'fax_number' => ''])
        ->assertRedirect(route('shops.show', $shop));
    expect(ShopSubmission::count())->toBe(0);
});

it('approveUrlSuggestion fills empty url, preserves existing, creates no new shop', function () {
    $empty = Shop::create(['name' => '空店', 'prefecture' => '東京都', 'city' => '世田谷区', 'address' => 'a', 'shop_type' => 'repair_only', 'source' => 'scraper']);
    $filled = Shop::create(['name' => '有店', 'prefecture' => '東京都', 'city' => '世田谷区', 'address' => 'b', 'official_site_url' => 'https://existing.jp', 'shop_type' => 'repair_only', 'source' => 'scraper']);
    $shopCountBefore = Shop::count();

    $sub1 = ShopSubmission::create(['shop_name' => '空店', 'prefecture' => '東京都', 'city' => '世田谷区', 'website_url' => 'https://new.jp', 'ip_hash' => str_repeat('a', 64), 'status' => 'pending', 'target_shop_id' => $empty->id]);
    $sub2 = ShopSubmission::create(['shop_name' => '有店', 'prefecture' => '東京都', 'city' => '世田谷区', 'website_url' => 'https://ignored.jp', 'ip_hash' => str_repeat('a', 64), 'status' => 'pending', 'target_shop_id' => $filled->id]);

    app(ShopSubmissionService::class)->approveUrlSuggestion($sub1);
    app(ShopSubmissionService::class)->approveUrlSuggestion($sub2);

    expect($empty->fresh()->official_site_url)->toBe('https://new.jp')        // 空→充填
        ->and($filled->fresh()->official_site_url)->toBe('https://existing.jp') // 既存→保持
        ->and(Shop::count())->toBe($shopCountBefore)                            // 新規店を作らない
        ->and($sub1->fresh()->status)->toBe('merged');
});

it('shops:classify skips user shops', function () {
    Shop::create(['name' => 'user店', 'prefecture' => '東京都', 'city' => 'x', 'address' => 'a',
        'service_tags' => ['認証工場'], 'shop_type' => 'repair_only', 'source' => Shop::SOURCE_USER]);
    Shop::create(['name' => 'scraper店', 'prefecture' => '東京都', 'city' => 'x', 'address' => 'b',
        'service_tags' => ['HONDA正規店'], 'shop_type' => null, 'source' => Shop::SOURCE_SCRAPER]);

    $this->artisan('shops:classify')->assertOk();

    // scraper shop got classified (dealer), user shop untouched (still repair_only, service_tags intact)
    expect(Shop::where('name', 'scraper店')->first()->shop_type)->toBe('dealer')
        ->and(Shop::where('name', 'user店')->first()->shop_type)->toBe('repair_only');
});
