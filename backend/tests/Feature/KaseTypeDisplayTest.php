<?php

declare(strict_types=1);

use App\Models\RentalGarage;

/*
 * 加瀬倉庫の区画種別に応じた表示の出し分け。
 *  - 1.6畳注記（安武さん要望）: cntn / trnk を含む物件のみ。bike / bike-out には出さない。
 *  - 参考価格（宿題①）: 詳細・一覧の両方に出る。
 *  - スロープ（宿題④）: 対象県 + cntn + 対象外でない物件のみ。「無料」だけを残さず送料を併記。
 */

/**
 * @param  array<int, string>  $typeCodes
 * @param  array<string, mixed>  $overrides
 */
function makeKaseGarage(array $typeCodes, array $overrides = []): RentalGarage
{
    $garage = RentalGarage::create(array_merge([
        'name' => '加瀬倉庫 テスト店',
        'operator' => '加瀬倉庫',
        'garage_type' => 'container',
        'prefecture' => '神奈川県',
        'city' => '横浜市戸塚区',
        'address' => '神奈川県横浜市戸塚区影取町1-1',
        'monthly_fee_min' => 7700,
        'monthly_fee_max' => 25300,
        'size_text' => '1.6畳～8畳',
        'source_url' => 'https://www.kase3535.com/kanagawa/yokohamashitotsukaku/'.random_int(100000, 999999).'/',
        'website_url' => 'https://www.kase3535.com/kanagawa/yokohamashitotsukaku/101025/',
        'is_active' => true,
    ], $overrides));

    foreach ($typeCodes as $code) {
        $garage->types()->create(['type_code' => $code]);
    }

    return $garage;
}

const SIZE_NOTE_TEXT = '原則として下段・1.6畳以上となります';
const SLOPE_TITLE = 'バイクスロープの無料レンタルあり';

it('cntn のみの物件は 1.6畳注記が出る', function () {
    $garage = makeKaseGarage(['cntn']);

    $this->get('/rental-garages/'.$garage->id)
        ->assertOk()
        ->assertSee(SIZE_NOTE_TEXT);
});

it('bike のみの物件は 1.6畳注記が出ない', function () {
    // バイク専用（バイクヤード）。1.6畳注記を出すと逆に誤解を生むため出さない。
    $garage = makeKaseGarage(['bike'], ['name' => '加瀬倉庫 バイクヤードテスト']);

    $this->get('/rental-garages/'.$garage->id)
        ->assertOk()
        ->assertDontSee(SIZE_NOTE_TEXT);
});

it('cntn + bike の物件は 1.6畳注記が出る', function () {
    $garage = makeKaseGarage(['cntn', 'bike']);

    $this->get('/rental-garages/'.$garage->id)
        ->assertOk()
        ->assertSee(SIZE_NOTE_TEXT);
});

it('詳細ページに参考価格が出る', function () {
    $garage = makeKaseGarage(['cntn']);

    $this->get('/rental-garages/'.$garage->id)
        ->assertOk()
        ->assertSee('参考価格')
        ->assertSee('料金は変更される場合があります');
});

it('一覧ページに参考価格が出る', function () {
    makeKaseGarage(['cntn'], ['prefecture' => '東京都', 'city' => '大田区', 'address' => '東京都大田区1-1']);

    $this->get('/rental-garages/area/'.rawurlencode('東京都').'/'.rawurlencode('大田区'))
        ->assertOk()
        ->assertSee('参考価格');
});

it('対象県のレンタルボックスにはスロープの案内が出て、送料の断りを必ず併記する', function () {
    $garage = makeKaseGarage(['cntn'], [
        'prefecture' => '東京都', 'city' => '大田区', 'address' => '東京都大田区1-1',
    ]);

    $res = $this->get('/rental-garages/'.$garage->id)->assertOk();
    $res->assertSee(SLOPE_TITLE);
    // 「無料」だけを残さない: 送料の断りが同じページに必ずある。
    $res->assertSee('送料は別途必要です');
});

it('スロープ対象外物件（config の名称）にはスロープの案内を出さない', function () {
    // config の excluded は「実 name の完全一致」。掲載がある対象外物件は『レンタルボックス旭ハ』のみ。
    $garage = makeKaseGarage(['cntn'], [
        'name' => 'レンタルボックス旭ハ', 'prefecture' => '千葉県', 'city' => '旭市', 'address' => '千葉県旭市ハ1-1',
    ]);

    $this->get('/rental-garages/'.$garage->id)
        ->assertOk()
        ->assertDontSee(SLOPE_TITLE);
});

it('バイクヤード（bike のみ・対象外県）にはスロープを出さない', function () {
    $garage = makeKaseGarage(['bike'], ['prefecture' => '大阪府', 'city' => '大阪市', 'address' => '大阪府大阪市1-1']);

    $this->get('/rental-garages/'.$garage->id)
        ->assertOk()
        ->assertDontSee(SLOPE_TITLE);
});

/*
 * 区画サイズの書き換え廃止（誤認防止）。下限がバイク不可でも size_text をそのまま出す。
 */
it('詳細ページは size_text をそのまま出す（1.6畳以上に書き換えない）', function () {
    $garage = makeKaseGarage(['cntn'], ['size_text' => '1.4畳～8畳']);

    $this->get('/rental-garages/'.$garage->id)
        ->assertOk()
        ->assertSee('1.4畳')                    // 実サイズがそのまま出る
        ->assertDontSee('区画サイズは1.6畳以上'); // 自動生成文も書き換えていない
});

it('一覧ページも size_text をそのまま出す', function () {
    makeKaseGarage(['cntn'], [
        'prefecture' => '東京都', 'city' => '大田区', 'address' => '東京都大田区1-1', 'size_text' => '1.4畳～8畳',
    ]);

    $this->get('/rental-garages/area/'.rawurlencode('東京都').'/'.rawurlencode('大田区'))
        ->assertOk()
        ->assertSee('1.4畳')
        ->assertDontSee('1.6畳以上');
});

/*
 * 区画種別ごとの説明（阿部さん要望）。物件が持つ種別のぶんだけ config から出す。
 */
it('cntn + bike-out の物件は種別説明が2つ出る', function () {
    $garage = makeKaseGarage(['cntn', 'bike-out']);

    $this->get('/rental-garages/'.$garage->id)
        ->assertOk()
        ->assertSee('レンタルボックス（屋外型コンテナ）')
        ->assertSee('扉付きのコンテナなので、雨風から守れます。')        // cntn の body
        ->assertSee('白線で区切られた屋外の駐輪スペースです。')          // bike-out の body
        ->assertDontSee('屋内型の収納スペースです。');                  // trnk（持っていない）は出さない
});

it('bike のみの物件は種別説明が1つ出る（cntn 無しでもブロックは出る）', function () {
    $garage = makeKaseGarage(['bike'], ['name' => '加瀬倉庫 バイクヤードテスト']);

    $this->get('/rental-garages/'.$garage->id)
        ->assertOk()
        ->assertSee('バイクの保管場所として使えます')
        ->assertSee('バイク専用の個別コンテナです。')
        ->assertSee('1台ごとに区切られています。')                      // typo(independent)修正の確認
        ->assertDontSee('independent')
        ->assertDontSee('扉付きのコンテナなので、雨風から守れます。');    // cntn（持っていない）は出さない
});

it('種別を持たない物件には種別説明ブロックを出さない', function () {
    $garage = makeKaseGarage([]);

    $this->get('/rental-garages/'.$garage->id)
        ->assertOk()
        ->assertDontSee('バイクの保管場所として使えます');
});

it('バイクライン系の種別説明に畳数を書かない', function () {
    $defs = config('rental_garage.kase_type_descriptions');
    foreach (['bike', 'bike-in', 'bike-out'] as $code) {
        expect($defs[$code]['body'])->not->toContain('畳');
    }
});

/*
 * 種別アイコン（inline SVG パーシャル）。既知コードは専用、未知コードは汎用で落とさない。
 */
it('種別説明ブロックに inline SVG アイコンが出る（trnk は汎用アイコン）', function () {
    $garage = makeKaseGarage(['trnk'], ['name' => '加瀬倉庫 トランクテスト']);

    $this->get('/rental-garages/'.$garage->id)
        ->assertOk()
        ->assertSee('屋内型の収納スペースです。')
        ->assertSee('<svg', false); // アイコンがサーバー側 HTML に inline SVG で入る
});

it('type-icon パーシャルは未知コードでも例外を投げず SVG を返す', function () {
    $html = view('rental_garage.partials.type-icon', ['code' => 'unknown-xyz'])->render();
    expect($html)->toContain('<svg');
});

it('type-icon パーシャルは code 未指定でも例外を投げず SVG を返す', function () {
    $html = view('rental_garage.partials.type-icon')->render();
    expect($html)->toContain('<svg');
});
