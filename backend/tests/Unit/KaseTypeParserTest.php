<?php

declare(strict_types=1);

use App\Services\RentalGarage\KaseTypeParser;

/*
 * 加瀬倉庫 物件ページの区画種別パーサ。
 * ★ 実際に外部サイトへはアクセスしない。Flight ペイロードの構造を模した断片で検証する。
 *
 * 断片には次を含める（実HTMLと同じ罠を再現）:
 *  - storageTypes 凡例（サイト共通・拾ってはいけない）
 *  - 対象物件 101025（cntn / bike / bike-out をインライン types で持つ。画像URL・空き状況も含む）
 *  - nearbyObjects の近隣物件 031307（trnk を持つ。拾ってはいけない）
 *  - 参照形の同一物件（types が "$..." 文字列。拾ってはいけない）
 */

function kaseFixtureHtml(): string
{
    // nowdoc: バックスラッシュはそのまま。Flight と同じく二重引用符は \" でエスケープされている。
    return <<<'HTML'
<script>self.__next_f.push([1,"7:[\"$\",\"div\",null,{\"storageTypes\":[{\"id\":\"cntn\",\"name\":\"レンタルボックス（屋外型）\"},{\"id\":\"trnk\",\"name\":\"トランクルーム（屋内型）\"},{\"id\":\"bike\",\"name\":\"バイク収納\"}]}]"])</script>
<script>self.__next_f.push([1,"8:{\"object\":{\"id\":\"101025\",\"number\":101025,\"name\":\"戸塚シャドー\",\"minPrice\":3960,\"maxPrice\":44880,\"thumbnail\":{\"id\":\"101025a\",\"url\":\"https://www.kase3535.com/image/101025/101025a.jpg\",\"caption\":\"\"},\"types\":[{\"id\":\"cntn\",\"name\":\"レンタルボックス\",\"isAvailable\":true,\"availableCount\":15},{\"id\":\"bike\",\"name\":\"バイクヤード\",\"isAvailable\":true,\"availableCount\":1},{\"id\":\"bike-out\",\"name\":\"バイクヤード\",\"isAvailable\":false,\"availableCount\":0}]},\"nearbyObjects\":[{\"id\":\"031307\",\"name\":\"戸塚区影取町\",\"types\":[{\"id\":\"trnk\",\"name\":\"トランクルーム\",\"isAvailable\":true,\"availableCount\":3}]}]}"])</script>
<script>self.__next_f.push([1,"9:{\"object\":{\"id\":\"101025\",\"name\":\"戸塚シャドー\",\"types\":\"$5:4:props:object:types\"}}"])</script>
HTML;
}

it('対象物件自身の types だけを拾う（周辺物件・凡例・参照形は拾わない）', function () {
    $codes = KaseTypeParser::parse(kaseFixtureHtml(), '101025');

    // 対象物件 101025 は cntn / bike / bike-out。順序も出現順。
    expect($codes)->toBe(['cntn', 'bike', 'bike-out']);
    // 近隣物件 031307 の trnk は混ざらない。
    expect($codes)->not->toContain('trnk');
});

it('bike と bike-out を別のものとして返す（表示名が同じでも id で区別）', function () {
    $codes = KaseTypeParser::parse(kaseFixtureHtml(), '101025');

    expect($codes)->toContain('bike');
    expect($codes)->toContain('bike-out');
});

it('返す配列は type_code の文字列のみ（isAvailable / availableCount を含まない）', function () {
    $codes = KaseTypeParser::parse(kaseFixtureHtml(), '101025');

    foreach ($codes as $code) {
        expect($code)->toBeString();
    }
    // 配列全体を文字列化しても空き状況キーは現れない。
    $flat = implode('|', $codes);
    expect($flat)->not->toContain('isAvailable');
    expect($flat)->not->toContain('availableCount');
});

it('画像に関するキーを一切拾わない', function () {
    $codes = KaseTypeParser::parse(kaseFixtureHtml(), '101025');

    $flat = implode('|', $codes);
    expect($flat)->not->toContain('url');
    expect($flat)->not->toContain('thumbnail');
    expect($flat)->not->toContain('.jpg');
    expect($flat)->not->toContain('image');
});

it('存在しない物件ID・不正なIDでは空配列を返す', function () {
    expect(KaseTypeParser::parse(kaseFixtureHtml(), '999999'))->toBe([]);
    expect(KaseTypeParser::parse(kaseFixtureHtml(), 'abc'))->toBe([]);
    expect(KaseTypeParser::parse('', '101025'))->toBe([]);
});

it('許可外コードは除外する', function () {
    // allowed を cntn のみに絞ると bike / bike-out は落ちる。
    $codes = KaseTypeParser::parse(kaseFixtureHtml(), '101025', ['cntn']);
    expect($codes)->toBe(['cntn']);
});
