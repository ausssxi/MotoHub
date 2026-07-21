<?php

declare(strict_types=1);

use App\Support\TheftStats;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function nsResetTheftCache(): void
{
    $rp = new ReflectionProperty(TheftStats::class, 'cache');
    $rp->setAccessible(true);
    $rp->setValue(null, null);
}

function installTheftData(array $data): void
{
    file_put_contents(base_path('storage/_theft_test.json'), json_encode($data));
    config(['theft.data_path' => 'storage/_theft_test.json']);
    nsResetTheftCache();
}

afterEach(function () {
    @unlink(base_path('storage/_theft_test.json'));
    nsResetTheftCache();
});

function theftSample(): array
{
    return [
        'years' => [2023, 2024],
        'prefectures' => [
            '東京都' => ['2023' => ['recognized' => 500, 'cleared' => 50], '2024' => ['recognized' => 600, 'cleared' => 60]],
            '大阪府' => ['2024' => ['recognized' => 550, 'cleared' => 110]],
        ],
    ];
}

// ─────────── ハブ /theft ───────────

it('renders the hub and hides ranking + CTA when data/affiliate are empty (no fake button, no invented numbers)', function () {
    installTheftData(['years' => [], 'prefectures' => []]);
    config(['theft.affiliate.url' => '']);

    $html = $this->get(route('theft'))->assertOk()->getContent();

    expect($html)->toContain('バイクの盗難対策の基本')        // 対策一般論は常に出る
        ->toContain('統計データは準備中')                      // データ未投入は準備中（数字を創作しない）
        ->toContain('警察庁')                                  // 出典表記
        ->not->toContain('rel="nofollow sponsored');           // CTA非表示（偽ボタンを置かない）
});

it('shows the ranking table and PR CTA when data + affiliate url are present', function () {
    installTheftData(theftSample());
    config(['theft.affiliate.url' => 'https://example.com/zuttoride']);

    $html = $this->get(route('theft'))->assertOk()->getContent();

    expect($html)->toContain('都道府県別 オートバイ盗ランキング')
        ->toContain('東京都')
        ->toContain('600')                                     // 認知件数がテキストで入る（クロール可能）
        ->toContain('rel="nofollow sponsored noopener"')       // PRアフィリCTA
        ->toContain('PR');
});

// ─────────── 面② 県別ページ差し込み ───────────

it('injects the theft block on an area page only when data exists (else hidden)', function () {
    installTheftData(theftSample());
    $html = $this->get(route('bikes.area_index', '東京都'))->assertOk()->getContent();
    expect($html)->toContain('東京都のバイク盗難データ')
        ->toContain('第1位')                                   // 全国順位
        ->toContain('全国のバイク盗難ランキングを見る');        // /theft への相互リンク

    // データ空 → ブロックごと非表示（安全なフォールバック）
    installTheftData(['years' => [], 'prefectures' => []]);
    $html2 = $this->get(route('bikes.area_index', '大阪府'))->assertOk()->getContent();
    expect($html2)->not->toContain('のバイク盗難データ');
});

// ─────────── TheftStats 算出ロジック ───────────

it('computes clearance rate / rank / series and resolves prefecture names', function () {
    installTheftData(theftSample());

    $t = TheftStats::forPrefecture('東京'); // 前方一致で「東京都」へ名寄せ
    expect($t['prefecture'])->toBe('東京都')
        ->and($t['recognized'])->toBe(600)
        ->and($t['clearance_rate'])->toBe(10.0)                // 60/600
        ->and($t['rank'])->toBe(1)
        ->and(array_column($t['series'], 'recognized'))->toBe([500, 600]);

    expect(TheftStats::forPrefecture('存在しない県'))->toBeNull();
    expect(collect(TheftStats::rankingTable())->pluck('prefecture')->all())->toBe(['東京都', '大阪府']);
});
