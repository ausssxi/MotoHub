<?php

declare(strict_types=1);

use App\Models\BikeModel;
use App\Models\BikeNews;
use App\Models\Manufacturer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

function retitleModel(array $attrs = []): BikeModel
{
    $mfr = new Manufacturer(['slug' => 'kawasaki']);
    $mfr->name = 'カワサキ';
    $mfr->save();

    return BikeModel::create(array_merge([
        'manufacturer_id' => $mfr->id,
        'name' => 'z900rs',
        'display_name' => 'Z900RS',
        'slug' => 'z900rs',
    ], $attrs));
}

function retitleArticle(BikeModel $model, array $attrs = []): BikeNews
{
    return BikeNews::create(array_merge([
        'title' => 'z900rsの新型発表で旧型中古相場はどう動く？｜データで予測',
        'url' => 'https://motohub.jp/news/'.uniqid(),
        'source' => BikeNews::SOURCE_ORIGINAL,
        'content' => '<p>中古車は<strong>886台</strong>が掲載されており、平均価格は<strong>147.5万円</strong>です。</p>',
        'published_at' => Carbon::parse('2026-08-31 09:00:00'),
        'bike_model_id' => $model->id,
        'manufacturer_id' => $model->manufacturer_id,
    ], $attrs));
}

it('does not touch the DB in dry-run mode', function () {
    $model = retitleModel();
    $news = retitleArticle($model);
    $original = $news->title;

    $this->artisan('news:retitle-model-impact', ['--dry-run' => true])->assertSuccessful();

    expect($news->fresh()->title)->toBe($original);
});

it('rewrites the title with numbers that match the body', function () {
    $model = retitleModel();
    $news = retitleArticle($model);

    $this->artisan('news:retitle-model-impact')->assertSuccessful();

    $newTitle = $news->fresh()->title;
    expect($newTitle)->toBe('Z900RSの中古相場、平均147.5万円・在庫886台（2026年8月）');

    // 本文から数字を取り出し、タイトルの数字と一致することを保証する。
    preg_match('/<strong>([\d,]+)台/u', $news->content, $countMatch);
    preg_match('/平均価格は<strong>([\d.]+)万円/u', $news->content, $avgMatch);
    expect($newTitle)->toContain($countMatch[1].'台');
    expect($newTitle)->toContain('平均'.$avgMatch[1].'万円');
});

it('falls back to a date-based title when numbers cannot be extracted', function () {
    $model = retitleModel();
    $news = retitleArticle($model, ['content' => '<p>数字のない本文。</p>']);

    $this->artisan('news:retitle-model-impact')->assertSuccessful();

    expect($news->fresh()->title)->toBe('Z900RSの中古相場レポート（2026年8月31日時点）');
});

it('does not touch ranking articles', function () {
    $model = retitleModel();
    $ranking = BikeNews::create([
        'title' => '【MotoHub調べ】8月31日 バイク売れ筋デイリーランキング',
        'url' => 'https://motohub.jp/news/'.uniqid(),
        'source' => BikeNews::SOURCE_ORIGINAL,
        'content' => '<p>ランキング本文</p>',
        'published_at' => now(),
        'bike_model_id' => $model->id,
    ]);
    $original = $ranking->title;

    $this->artisan('news:retitle-model-impact')->assertSuccessful();

    expect($ranking->fresh()->title)->toBe($original);
});

it('breaks identical-title collisions with the date form', function () {
    $model = retitleModel();
    // 同一車種・同一年月・同一数字 → 生成タイトルが衝突する。
    $a = retitleArticle($model, ['published_at' => Carbon::parse('2026-08-19 09:00:00')]);
    $b = retitleArticle($model, ['published_at' => Carbon::parse('2026-08-31 09:00:00')]);

    $this->artisan('news:retitle-model-impact')->assertSuccessful();

    $titleA = $a->fresh()->title;
    $titleB = $b->fresh()->title;

    expect($titleA)->not->toBe($titleB);
    expect($titleA)->toBe('Z900RSの中古相場レポート（2026年8月19日時点）');
    expect($titleB)->toBe('Z900RSの中古相場レポート（2026年8月31日時点）');
});

it('leaves articles whose model lacks an official name untouched', function () {
    $model = retitleModel(['name' => 'pcx', 'display_name' => null]);
    $news = retitleArticle($model, ['title' => 'pcxの新型発表で旧型中古相場はどう動く？｜データで予測']);
    $original = $news->title;

    $this->artisan('news:retitle-model-impact')->assertSuccessful();

    expect($news->fresh()->title)->toBe($original);
});
