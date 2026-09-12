<?php

declare(strict_types=1);

use App\Console\Commands\GenerateNewModelImpactNews;
use App\Models\BikeModel;
use App\Models\BikeNews;
use App\Models\Manufacturer;
use App\Services\News\ModelImpactTitleBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function impactModel(array $attrs = []): BikeModel
{
    $mfr = Manufacturer::where('slug', 'kawasaki')->first();
    if (! $mfr) {
        $mfr = new Manufacturer(['slug' => 'kawasaki']);
        $mfr->name = 'カワサキ';
        $mfr->save();
    }

    return BikeModel::create(array_merge([
        'manufacturer_id' => $mfr->id,
        'name' => 'z900rs',
        'display_name' => 'Z900RS',
        'slug' => 'z900rs',
    ], $attrs));
}

function impactArticle(BikeModel $model, array $attrs = []): BikeNews
{
    return BikeNews::create(array_merge([
        'title' => 'z900rsの新型発表で旧型中古相場はどう動く？｜データで予測',
        'url' => 'https://motohub.jp/news/'.uniqid(),
        'source' => BikeNews::SOURCE_ORIGINAL,
        'content' => '<p>本文</p>',
        'published_at' => now(),
        'bike_model_id' => $model->id,
        'manufacturer_id' => $model->manufacturer_id,
    ], $attrs));
}

// ─────────── 30日ルール ───────────

it('reports a recent article when one exists within 30 days', function () {
    $model = impactModel();
    impactArticle($model, ['published_at' => now()->subDays(10)]);

    $command = new GenerateNewModelImpactNews;

    expect($command->hasRecentModelArticle($model->id))->toBeTrue();
});

it('does not report an article older than 30 days', function () {
    $model = impactModel();
    $news = impactArticle($model, ['published_at' => now()->subDays(31)]);
    // created_at は $fillable 外なので明示的に過去へ倒す。
    $news->forceFill(['created_at' => now()->subDays(31)])->save();

    $command = new GenerateNewModelImpactNews;

    expect($command->hasRecentModelArticle($model->id))->toBeFalse();
});

it('is scoped per bike_model_id', function () {
    $modelA = impactModel();
    $modelB = impactModel(['name' => 'z650', 'display_name' => 'Z650', 'slug' => 'z650']);
    impactArticle($modelA, ['published_at' => now()->subDays(5)]);

    $command = new GenerateNewModelImpactNews;

    expect($command->hasRecentModelArticle($modelA->id))->toBeTrue();
    expect($command->hasRecentModelArticle($modelB->id))->toBeFalse();
});

it('ignores RSS (non-MotoHub) articles for the recency check', function () {
    $model = impactModel();
    impactArticle($model, ['source' => 'goo-net.com', 'published_at' => now()]);

    $command = new GenerateNewModelImpactNews;

    expect($command->hasRecentModelArticle($model->id))->toBeFalse();
});

// ─────────── 正式車種名の解決 ───────────

it('resolves the official name from display_name, not the lowercase name', function () {
    $model = impactModel(['name' => 'z900rs', 'display_name' => 'Z900RS']);

    expect(ModelImpactTitleBuilder::officialName($model))->toBe('Z900RS');
});

it('returns null when only an all-lowercase latin name is available', function () {
    $model = impactModel(['name' => 'pcx', 'display_name' => null]);

    expect(ModelImpactTitleBuilder::officialName($model))->toBeNull();
});

it('accepts a name that already contains japanese characters', function () {
    $model = impactModel(['name' => 'ハンターカブ', 'display_name' => null]);

    expect(ModelImpactTitleBuilder::officialName($model))->toBe('ハンターカブ');
});
