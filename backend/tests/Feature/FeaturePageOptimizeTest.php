<?php

declare(strict_types=1);

use App\Models\BikeModel;
use App\Models\Listing;
use App\Models\Manufacturer;
use App\Models\SeoFeature;
use App\Models\Site;
use App\Services\Bike\ListingSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Scout\EngineManager;

uses(RefreshDatabase::class);

beforeEach(function () {
    // ListingSearchService/Repository は final でモック不可、かつ実装の検索コールバックは Meili 専用シグネチャ。
    // NullEngine を継承した「DB直・コールバック非実行」の偽エンジンに固定して、Meili非依存で描画を通す。
    $engine = new class extends \Laravel\Scout\Engines\NullEngine
    {
        private function models(\Laravel\Scout\Builder $builder)
        {
            return $builder->model->newQuery()->where('is_sold_out', false)->get();
        }

        public function search(\Laravel\Scout\Builder $builder)
        {
            $m = $this->models($builder);

            return ['hits' => $m, 'total' => $m->count(), 'facetDistribution' => []];
        }

        public function paginate(\Laravel\Scout\Builder $builder, $perPage, $page)
        {
            $m = $this->models($builder);

            return ['hits' => $m, 'total' => $m->count(), 'facetDistribution' => []];
        }

        public function map(\Laravel\Scout\Builder $builder, $results, $model)
        {
            return collect($results['hits'] ?? []);
        }

        public function getTotalCount($results)
        {
            return $results['total'] ?? 0;
        }
    };
    app(EngineManager::class)->extend('faketest', fn () => $engine);
    config(['scout.driver' => 'faketest']);
    Cache::flush();
});

function makeFeature(string $slug, string $title, array $conditions): SeoFeature
{
    return SeoFeature::forceCreate([
        'slug' => $slug,
        'title' => $title,
        'meta_description' => 'テスト用の特集ページ。',
        'content_header' => '<p>テスト</p>',
        'guide_content' => 'テストガイド。',
        'search_conditions' => $conditions,
        'keyword' => null,
        'prefecture' => null,
        'sort' => 'latest',
        'is_active' => true,
        'sort_order' => 1,
    ]);
}

function featurePricedListing(string $title = 'テスト車両CB', int $yen = 150000): Listing
{
    $mfr = Manufacturer::where('slug', 'honda-ftest')->first();
    if (! $mfr) {
        $mfr = new Manufacturer;
        $mfr->forceFill(['name' => 'ホンダ', 'slug' => 'honda-ftest'])->save();
    }
    $model = BikeModel::where('slug', 'ftest-cb')->first();
    if (! $model) {
        $model = new BikeModel;
        $model->forceFill(['manufacturer_id' => $mfr->id, 'name' => 'テストCB', 'slug' => 'ftest-cb', 'displacement' => 250])->save();
    }
    $site = new Site;
    $site->forceFill(['name' => 'テストサイト '.uniqid()])->save();

    return Listing::forceCreate([
        'site_id' => $site->id,
        'bike_model_id' => $model->id,
        'source_url' => 'https://example.com/l/'.uniqid(),
        'title' => $title,
        'total_price' => $yen,
        'model_year' => 2021,
        'condition' => '中古車',
        'is_sold_out' => false,
        'image_urls' => ['https://example.com/img/1.jpg'],
    ]);
}

// ---- #2 seeder: title/meta の口語＋中古最適化 ----

it('updates the feature titles with colloquial + used wording via the seeder', function () {
    $this->seed(\Database\Seeders\SeoFeatureSeeder::class);

    expect(SeoFeature::where('slug', 'one-owner')->value('title'))->toContain('ワンオーナーの中古バイク一覧')
        ->and(SeoFeature::where('slug', 'low-mileage')->value('title'))->toContain('低走行の中古バイク一覧')
        ->and(SeoFeature::where('slug', 'full-normal-used')->value('title'))->toContain('フルノーマルの中古バイク一覧');
});

// ---- #4 検索サイドバーに「ノーマル車」露出 ----

it('exposes ノーマル車 in the popular tags (search sidebar)', function () {
    expect(app(ListingSearchService::class)->getPopularTags())->toContain('ノーマル車')
        ->toContain('ワンオーナー'); // 既存も維持
});

// ---- #1 title に動的【N台】＋BreadcrumbList（KPI/blade由来） ----

it('appends the live 【N台】 count to the feature title and emits BreadcrumbList', function () {
    makeFeature('ftest-count', 'テスト特集', []); // 条件なし＝全active priced をKPIが数える
    featurePricedListing();

    $html = $this->get('/features/ftest-count')->assertOk()->getContent();

    expect($html)->toContain('【1台】')          // KPI(直DB)由来
        ->toContain('BreadcrumbList')
        ->toContain('特集一覧');                 // パンくずの中間
});

// ---- #4 在庫ゼロ: noindex＋ItemList非出力＋【N台】を付けない（doorway無し） ----

it('noindexes and emits no ItemList when the feature has zero inventory', function () {
    makeFeature('ftest-empty', 'カラ特集', []); // 在庫を作らない＝0件

    $html = $this->get('/features/ftest-empty')->assertOk()->getContent();

    expect($html)->toContain('noindex')                 // 0台は noindex
        ->not->toContain('"@type":"ItemList"')          // 空ItemListを出さない
        ->not->toContain('カラ特集【');                 // 0台は【N台】を付けない
});

// ---- #3 ItemList(Product/Offer)＋在庫カード（ListingResource） ----

it('emits ItemList Product/Offer and renders listing cards for in-stock inventory', function () {
    makeFeature('ftest-items', 'ザイコ特集', []);
    featurePricedListing('テスト車両CB', 150000);

    $html = $this->get('/features/ftest-items')->assertOk()->getContent();

    expect($html)->toContain('"@type":"ItemList"')
        ->toContain('"@type":"Offer"')
        ->toContain('"price":150000')        // 万円文字列→円に復元
        ->toContain('"priceCurrency":"JPY"')
        ->toContain('ホンダ');               // カードがListingResource経由でメーカー名を表示
});
