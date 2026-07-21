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

function installTheftData(array $national): void
{
    file_put_contents(base_path('storage/_theft_test.json'), json_encode(['national' => $national]));
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
        '2022' => ['recognized' => 0, 'cleared' => 0],           // 未投入（0）→除外
        '2023' => ['recognized' => 16000, 'cleared' => 3000],
        '2024' => ['recognized' => 11641, 'cleared' => 2400],    // 実データ 2024=11641
        '2025' => ['recognized' => 14552, 'cleared' => 2970],    // 実データ 2025=14552
    ];
}

// ─────────── ハブ /theft（全国版） ───────────

it('renders the hub and hides summary + CTA when data/affiliate are empty (no fake numbers, no fake button)', function () {
    installTheftData(['2025' => ['recognized' => 0, 'cleared' => 0]]); // 全て0＝未投入
    config(['theft.affiliate.url' => '']);

    $html = $this->get(route('theft'))->assertOk()->getContent();

    expect($html)->toContain('バイクの盗難対策の基本')   // 対策一般論は常に出る
        ->toContain('統計データは準備中')                 // データ未投入は準備中
        ->not->toContain('認知件数（')                     // 最新年サマリは出さない
        ->not->toContain('rel="nofollow sponsored');       // CTA非表示（偽ボタンを置かない）
});

it('shows the national summary, trend and PR CTA when data + affiliate url are present', function () {
    installTheftData(theftSample());
    config(['theft.affiliate.url' => 'https://example.com/zuttoride']);

    $html = $this->get(route('theft'))->assertOk()->getContent();

    expect($html)->toContain('14,552')                     // 最新年の認知件数（テキスト＝クロール可能）
        ->toContain('20.4')                                // 検挙率 2970/14552
        ->toContain('+25.0')                               // 前年比 (14552-11641)/11641 = +25.0%
        ->toContain('認知件数の推移')                       // 折れ線見出し
        // グラフ要素（inline SVG・依存ゼロ）
        ->toContain('<polyline')                           // 折れ線
        ->toContain('theftArea')                           // エリア塗りの linearGradient
        ->toContain('>2023<')                              // 横軸の年ラベル（fixtureは2022を0で除外＝2023〜2025）
        ->toContain('>2025<')                              // 全年ラベルが出ている（最新年）
        // CTA（env設定時・景表法PR表記・自前テキストボタン）
        ->toContain('rel="nofollow sponsored noopener"')
        ->toContain('PR・広告')
        ->toContain('見積もりをみる')
        // 本文の文脈内部リンク（nofollow無し）
        ->toContain(route('hoken'))
        ->toContain(route('bikes.models'))
        ->toContain(route('mybikes.index'))
        ->toContain('警察庁');                             // 出典
});

it('links /theft and /hoken from the global footer (reachable on every page)', function () {
    // 軽量な /hoken ページのフッターで検証＝全ページから /theft へ辿れることの担保。
    $html = $this->get(route('hoken'))->assertOk()->getContent();
    expect($html)->toContain(route('theft'))
        ->toContain('バイクの盗難データ（全国）'); // フッターの /theft アンカー文言
});

// ─────────── TheftStats 算出ロジック ───────────

it('computes latest summary (rate/yoy) and excludes un-entered (0) years from the series', function () {
    installTheftData(theftSample());

    $latest = TheftStats::latest();
    expect($latest['year'])->toBe(2025)
        ->and($latest['recognized'])->toBe(14552)
        ->and($latest['clearance_rate'])->toBe(20.4)
        ->and($latest['yoy_pct'])->toBe(25.0); // (14552-11641)/11641

    // 0の年(2022)は除外・年昇順
    expect(collect(TheftStats::series())->pluck('year')->all())->toBe([2023, 2024, 2025]);

    // データ全欠損 → hasData false・latest null
    installTheftData([]);
    expect(TheftStats::hasData())->toBeFalse()
        ->and(TheftStats::latest())->toBeNull();
});

// ─────────── 撤去確認（県別ブロックの痕跡が area-index に無い） ───────────

it('leaves no county theft block on area pages after scope reduction', function () {
    installTheftData(theftSample());

    $html = $this->get(route('bikes.area_index', '東京都'))->assertOk()->getContent();
    expect($html)->not->toContain('のバイク盗難データ')           // 県別ブロック撤去済
        ->not->toContain('全国のバイク盗難ランキングを見る');
});
