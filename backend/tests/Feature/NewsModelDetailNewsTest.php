<?php

use App\Models\BikeModel;
use App\Models\BikeNews;
use App\Models\Manufacturer;
use App\Services\Bike\BikeNewsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

function regModel(): BikeModel
{
    $mfr = new Manufacturer(['slug' => 'honda']);
    $mfr->name = 'ホンダ';
    $mfr->save();

    return BikeModel::create(['manufacturer_id' => $mfr->id, 'name' => 'レブル250', 'slug' => 'rebel-250']);
}

function mkNews(string $source, array $attrs = []): BikeNews
{
    return BikeNews::create(array_merge([
        'title' => 'ニュース', 'url' => 'https://e.test/'.uniqid(),
        'source' => $source, 'published_at' => now(),
    ], $attrs));
}

// ─────────── グローバルスコープ回帰の番人（最重要） ───────────

it('does NOT register scopeOriginal as a global scope (raw BikeNews query still returns RSS)', function () {
    mkNews(BikeNews::SOURCE_ORIGINAL);
    mkNews('goo-net.com'); // RSS

    // 素の BikeNews クエリは全ソースを返す（グローバルスコープなら RSS が消えて 1 件になる）
    expect(BikeNews::count())->toBe(2)
        ->and(BikeNews::query()->pluck('source')->all())->toContain('goo-net.com')
        ->and(BikeNews::whereNotNull('bike_model_id')->orWhereNull('bike_model_id')->pluck('source')->all())->toContain('goo-net.com');

    // original() は明示呼び出し時のみ効く（ローカルスコープ）
    expect(BikeNews::original()->count())->toBe(1);
});

// ─────────── 車種詳細ページ(model_detail)の news 経路は RSS を返す ───────────

it('returns RSS news for model_detail via getForModel (cache), independent of the /news filter', function () {
    $model = regModel();

    // model_detail は BikeNewsService::getForModel＝キャッシュ(Google News RSS)のみを読む。
    // bike_news テーブルの source 絞りとは無関係。RSS由来の項目がそのまま返る。
    Cache::put(BikeNewsService::cacheKey($model->id), [
        ['title' => 'この車種のRSSニュース', 'url' => 'https://goo-net.com/x', 'source' => 'goo-net.com', 'date' => '2026/07/09', 'image' => null],
    ], 600);

    $news = app(BikeNewsService::class)->getForModel($model);

    expect($news)->toHaveCount(1)
        ->and($news[0]['source'])->toBe('goo-net.com')       // RSS が model_detail に出る
        ->and($news[0]['title'])->toBe('この車種のRSSニュース');
});

// ─────────── 車両詳細ページ(listing)の車種ニュースクエリは RSS を含む ───────────

it('keeps RSS in the listing-page car-model news query (bike_news table, unfiltered)', function () {
    $model = regModel();
    mkNews(BikeNews::SOURCE_ORIGINAL, ['bike_model_id' => $model->id, 'title' => 'オリジナル']);
    mkNews('VAGUE', ['bike_model_id' => $model->id, 'title' => 'RSS記事']);

    // BikeController の listing news と同じ生クエリ（source 非フィルタ）
    $listingNews = BikeNews::where('bike_model_id', $model->id)->latest()->get();

    expect($listingNews->pluck('source')->all())->toContain('VAGUE')                 // RSS 残る
        ->and($listingNews->pluck('source')->all())->toContain(BikeNews::SOURCE_ORIGINAL);
});

// ─────────── /news/model/{id}（車種スコープ）は RSS を含む ───────────

it('shows RSS on the model-scoped news page /news/model/{id}', function () {
    $model = regModel();
    mkNews(BikeNews::SOURCE_ORIGINAL, ['bike_model_id' => $model->id, 'title' => '車種オリジナル記事']);
    mkNews('goo-net.com', ['bike_model_id' => $model->id, 'title' => '車種RSS記事']);

    $this->get("/news/model/{$model->id}")->assertOk()
        ->assertSee('車種RSS記事')          // 車種ページは RSS を残す（従来どおり）
        ->assertSee('車種オリジナル記事');
});

// ─────────── /news 本体はオリジナルのみ（維持） ───────────

it('still keeps /news original-only after the fix', function () {
    mkNews(BikeNews::SOURCE_ORIGINAL, ['title' => 'オリジナル本体']);
    mkNews('goo-net.com', ['title' => 'RSSは主導線に出ない']);

    $this->get('/news')->assertOk()
        ->assertSee('オリジナル本体')
        ->assertDontSee('RSSは主導線に出ない');
});
