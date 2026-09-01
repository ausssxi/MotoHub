<?php

declare(strict_types=1);

use App\Models\BikeModel;
use App\Models\Listing;
use App\Models\Manufacturer;
use App\Models\Shop;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * BikeModel::image_url アクセサの「掲載停止サイト回避」回帰テスト。
 *
 * 背景: 権利者・株式会社リバークレイン（ウェビック=site_id 3）より 2026-08-10 付で
 *       取得済み画像を含む掲載停止を要請され承諾済み（Listing::IMAGE_SUPPRESSED_SITE_IDS）。
 *       image_url アクセサは代表Listing/別Listing探索で抑止サイトを引き当てると
 *       アクセサ側で空になり、許諾サイトの画像に辿り着けず null（メーカーロゴ落ち）になっていた。
 *       修正: 代表・探索クエリで抑止サイトを除外し、許諾サイトの画像を選ぶ。
 */

uses(RefreshDatabase::class);

// Listing は Scout(Searchable)。このテストは検索同期と無関係なので、
// Meilisearch へ同期させない（NullEngine 化）。実DB挙動は変わらない。
beforeEach(fn () => config(['scout.driver' => null]));

// suppressed = 3 前提（定数がこの値を含むこと自体もここで担保する）
const SUPPRESSED_SITE_ID = 3;   // ウェビック
const PERMITTED_SITE_ID = 2;    // BDS（許諾サイト）

function suModel(): BikeModel
{
    $mfr = Manufacturer::where('slug', 'honda-su')->first();
    if (! $mfr) {
        $mfr = new Manufacturer;
        $mfr->forceFill(['name' => 'ホンダ', 'slug' => 'honda-su'])->save();
    }
    $bm = new BikeModel;
    $bm->forceFill([
        'manufacturer_id' => $mfr->id,
        'name' => 'ジョルノ',
        'slug' => 'giorno-su-'.uniqid(),
        'displacement' => 50,
    ])->save();

    return $bm;
}

function suSite(int $id): Site
{
    $site = Site::find($id);
    if (! $site) {
        $site = new Site;
        $site->forceFill(['id' => $id, 'name' => 'site-'.$id])->save();
    }

    return $site;
}

function suShop(): Shop
{
    $s = new Shop;
    $s->forceFill([
        'name' => 'shop-'.uniqid(),
        'address' => 'addr-'.uniqid(),
        'prefecture' => '東京都',
        'source' => Shop::SOURCE_SCRAPER,
    ])->save();

    return $s;
}

/**
 * @param  list<string>  $localPaths
 */
function suListing(BikeModel $model, int $siteId, array $localPaths): Listing
{
    suSite($siteId);

    return Listing::forceCreate([
        'site_id' => $siteId,
        'shop_id' => suShop()->id,
        'bike_model_id' => $model->id,
        'source_url' => 'https://ex/'.uniqid(),
        'title' => 'ジョルノ中古',
        'total_price' => 200000,
        'model_year' => 2022,
        'condition' => '中古車',
        'is_sold_out' => false,
        'local_image_paths' => $localPaths,
    ]);
}

it('代表が抑止サイト（site_id=3）でも許諾サイトの画像を選ぶ', function () {
    $model = suModel();

    // 許諾サイト（BDS）を先に = 小さいid
    suListing($model, PERMITTED_SITE_ID, ['listings/permitted/ok.jpg']);
    // 抑止サイト（ウェビック）を後に = 最大id → 従来はこれが代表に選ばれて null 落ちしていた
    suListing($model, SUPPRESSED_SITE_ID, ['listings/webike/suppressed.jpg']);

    $url = $model->fresh()->image_url;

    expect($url)->not->toBeNull()
        ->and($url)->toContain('listings/permitted/ok.jpg');
});

it('許諾サイトの在庫が1件も無ければ null のまま', function () {
    $model = suModel();

    // 抑止サイトだけ（複数件）。BikeModel 自身のローカル画像も無い。
    suListing($model, SUPPRESSED_SITE_ID, ['listings/webike/a.jpg']);
    suListing($model, SUPPRESSED_SITE_ID, ['listings/webike/b.jpg']);

    expect($model->fresh()->image_url)->toBeNull();
});

it('★抑止サイトの画像URLは絶対に返さない（掲載停止の約束を守る）', function () {
    $model = suModel();

    // 許諾サイト（BDS）を先に = 小さいid。
    suListing($model, PERMITTED_SITE_ID, ['listings/permitted/ok.jpg']);
    // 抑止サイト（ウェビック）を最後 = 最大id。ローカル画像・先方CDN直リンクの両方を持つ
    // “最新在庫”＝従来なら代表に選ばれ、それを表示に使ってしまう最悪パターンを再現。
    suSite(SUPPRESSED_SITE_ID);
    Listing::forceCreate([
        'site_id' => SUPPRESSED_SITE_ID,
        'shop_id' => suShop()->id,
        'bike_model_id' => $model->id,
        'source_url' => 'https://ex/'.uniqid(),
        'title' => 'ジョルノ中古',
        'total_price' => 200000,
        'model_year' => 2022,
        'condition' => '中古車',
        'is_sold_out' => false,
        'local_image_paths' => ['listings/webike/suppressed-local.jpg'],
        'image_urls' => ['https://webike.example/suppressed-remote.jpg'],
    ]);

    $url = (string) $model->fresh()->image_url;

    // 許諾サイトの画像を実際に返していること（null でお茶を濁していない＝アサートが空振りしない）。
    expect($url)->toContain('listings/permitted/ok.jpg');

    // かつ、抑止サイトのローカルパスも先方CDN直リンクも一切含まれないこと。
    expect($url)
        ->not->toContain('webike/suppressed-local.jpg')
        ->not->toContain('webike.example')
        ->not->toContain('suppressed');

    // 定数が実際に site_id=3 を抑止対象に含んでいることも固定（回帰の土台）
    expect(Listing::IMAGE_SUPPRESSED_SITE_IDS)->toContain(SUPPRESSED_SITE_ID);
});
