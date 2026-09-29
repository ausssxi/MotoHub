<?php

use App\Models\RentalBikePrice;
use App\Models\RentalBikeShop;
use Illuminate\Support\Carbon;

/**
 * 第3段階: 車格別の料金比較ページ（/rental-bikes/price, /rental-bikes/price/{class}）。
 *
 * ★外部アクセスはしない。★参考価格・取得日・保険別・公式で確認の注記が必ず出ること。
 * ★70日超は非表示（scopeFresh）。★819/AJ は料金なし＝公式リンクのみ。
 * ★店舗数は10店未満なら数字を伏せる。★ルート宣言順で "price" が都道府県に飲まれないこと。
 */
afterEach(fn () => Carbon::setTestNow());

/** 料金比較テスト用の店舗を1件作る。 */
function makePriceShop(string $slug, string $company, string $name): RentalBikeShop
{
    $address = '東京都千代田区'.$name;

    return RentalBikeShop::create([
        'company' => $company,
        'company_slug' => $slug,
        'external_id' => null,
        'name' => $name,
        'postal_code' => null,
        'address' => $address,
        'prefecture' => '東京都',
        'city' => '千代田区',
        'tel' => null,
        'opening_hours' => null,
        'official_url' => 'https://example.test/'.$slug,
        'is_active' => true,
        'fetched_at' => now(),
        'dedup_key' => RentalBikeShop::makeDedupKey($slug, null, $name, $address),
    ]);
}

/** 料金行を1件作る。 */
function makePrice(string $slug, string $class, int $yen, string $label, int $daysAgo, bool $isFrom = false, ?string $note = null): void
{
    RentalBikePrice::create([
        'company_slug' => $slug,
        'vehicle_class' => $class,
        'plan' => 'daily',
        'plan_label' => $label,
        'price_yen' => $yen,
        'price_is_from' => $isFrom,
        'note' => $note,
        'source_url' => 'https://example.test/price',
        'fetched_at' => now()->subDays($daysAgo),
    ]);
}

beforeEach(function () {
    Carbon::setTestNow('2026-10-01 12:00:00');

    // ヤマハ 12店（>=10 → 店舗数を数字で出す）。
    for ($i = 1; $i <= 12; $i++) {
        makePriceShop('yamaha', 'ヤマハ バイクレンタル', "ヤマハ店{$i}");
    }
    // 二輪処 8店（<10 → 数字を伏せる）。
    for ($i = 1; $i <= 8; $i++) {
        makePriceShop('nirinsho', '二輪処グループ', "二輪処店{$i}");
    }
    // レンタル819 15店（per_shop・料金なし → 公式リンクのみ）。
    for ($i = 1; $i <= 15; $i++) {
        makePriceShop('rental819', 'レンタル819', "819店{$i}");
    }

    // 250cc: ヤマハ（24時間・鮮度内）と 二輪処（1日・鮮度内）。安い順に並ぶ。
    makePrice('yamaha', '250cc', 8000, '24時間', 5);
    makePrice('nirinsho', '250cc', 6000, '1日（当日返却）', 5);

    // 大型: ヤマハの料金は 80日前＝70日超 → 非表示（fresh の回帰）。
    makePrice('yamaha', '大型', 12000, '24時間', 80, true, '車種により異なります');
});

it('renders the price hub (ハブが都道府県ルートに飲まれない)', function () {
    $res = $this->get('/rental-bikes/price');

    $res->assertOk()
        ->assertSee('レンタルバイクの料金比較')
        ->assertSee('原付')
        ->assertSee('大型')
        // 必須注記
        ->assertSee('参考価格')
        ->assertSee('保険・補償は別途');
});

it('renders a class page with providers sorted by price and the required notices', function () {
    $res = $this->get('/rental-bikes/price/250cc');

    $res->assertOk()
        ->assertSee('レンタルバイク 250cc の料金比較')
        ->assertSee('ヤマハ バイクレンタル')
        ->assertSee('二輪処グループ')
        // 条件差（plan_label）を出す
        ->assertSee('24時間')
        ->assertSee('1日（当日返却）')
        // 金額
        ->assertSee('¥8,000')
        ->assertSee('¥6,000')
        // 必須注記
        ->assertSee('参考価格')
        ->assertSee('2026年9月26日') // fetched_at（5日前）が「○年○月○日時点」で出る
        ->assertSee('保険・補償は別途')
        ->assertSee('最新の料金は各公式サイトでご確認ください');

    // 安い順（二輪処6,000が先、ヤマハ8,000が後）。
    $content = $res->getContent();
    expect(strpos($content, '¥6,000'))->toBeLessThan(strpos($content, '¥8,000'));
});

it('hides prices older than 70 days on the class page', function () {
    // 大型はヤマハの料金が80日前 → 表示できる料金なし＝空メッセージ、金額は出さない。
    $res = $this->get('/rental-bikes/price/oogata');

    $res->assertOk()
        ->assertSee('掲載できる事業者がありません')
        ->assertDontSee('¥12,000');
});

it('shows store count only for providers with >=10 shops', function () {
    $res = $this->get('/rental-bikes/price/250cc');

    $res->assertOk()
        ->assertSee('全国12店舗')      // ヤマハ 12店 → 数字を出す
        ->assertDontSee('全国8店舗');  // 二輪処 8店 → 数字は伏せる
});

it('shows per-shop providers (819) as official-link only, no price', function () {
    $res = $this->get('/rental-bikes/price/250cc');

    $res->assertOk()
        ->assertSee('店舗ごとに料金が異なる事業者')
        ->assertSee('レンタル819')
        ->assertSee('全国15店舗');
});

it('renders visible FAQ and FAQPage structured data from real data only', function () {
    $res = $this->get('/rental-bikes/price/250cc');

    $res->assertOk()
        ->assertSee('よくある質問')
        ->assertSee('表示されている料金に保険は含まれますか？')
        ->assertSee('"@type":"FAQPage"', false);
});

it('404s an unknown class slug', function () {
    $this->get('/rental-bikes/price/999cc')->assertNotFound();
});
