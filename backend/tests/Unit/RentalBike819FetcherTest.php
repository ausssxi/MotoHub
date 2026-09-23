<?php

use App\Services\Parking\AddressParser;
use App\Support\RentalBike\Fetchers\Rental819Fetcher;

/**
 * レンタル819 Fetcher の単体テスト。
 * ★保存したHTMLの断片を食わせて期待する配列が返ること（★外部アクセスはしない）。
 * ★返る配列に画像キーが無いこと。★取れない項目（TEL/営業時間）は null で残ること。
 * ★住所→都道府県/市区町村は既存 AddressParser に揃うこと。
 * ★取得ヘッダ（Accept-Language: ja）の回帰は tests/Feature/RentalBikeFetchCommandTest.php（要アプリbootstrap）。
 *
 * 一覧の断片は本番HTMLの実構造（2026-09 確認・日本語版 ~/rental819-store-list-ja.html）:
 *   <div class="p-store-list__area-wrap">
 *     <p class="p-store-list__pref">北海道</p>              ← ★都道府県見出し（日本語フルネーム）
 *     <div class="p-store-list__area-inner">
 *       <a href="/store/{id}">
 *         <span class="p-store-list__store-name"><i class="las la-store-alt"></i>店名</span>
 *       </a>
 *     </div>
 *   </div>
 *
 * 詳細の断片は本番HTMLの実構造（2026-09 確認・store/70, store/37）:
 *   <dl class="p-store-detail__store-info">
 *     <dt>住所</dt><dd>〒NNN-NNNN 市区町村＋番地（都道府県は入らない）</dd>
 *     <dt>アクセス</dt><dd>…案内文（住所として拾ってはいけない）…</dd>
 *     <dt>電話番号</dt><dd>0xx-xxx-xxxx</dd>
 *     <dt>営業時間</dt><dd>…（dt が無い店もある → null）…</dd>
 *   </dl>
 * ★店名 span 内の <i> アイコンはタグ除去で落とす。店名末尾の付記「(…移転)」は除去する。
 * ★都道府県は「直前の p-store-list__pref 見出し」から取る（詳細に都道府県が無い特別区・一般市でも埋まる）。
 * ★住所・電話・営業時間は情報 dl からラベルで引く（本文の案内文・お知らせ・「移転前」ノートを混入させない）。
 */

// AddressParser の city 突合を決定的にする（DB非依存）。都道府県は一覧見出しから付くので市区町村だけあれば足りる。
uses(Tests\TestCase::class);

beforeEach(function () {
    AddressParser::setMunicipalitiesForTesting([
        '北見市', '港区', '杉並区', '横浜市緑区', '吾妻郡嬬恋村',
    ]);
});

afterEach(function () {
    AddressParser::flushMunicipalityCache(); // 静的キャッシュを他テストへ持ち越さない
});

// 一覧ページ断片（実構造）。都道府県見出しごとに店舗が並ぶ。
// 重複ID・店名spanを持たない /store/ リンク・/store/ トップは店舗ではない。
$listHtml = <<<'HTML'
<div class="p-store-list__area-wrap">
  <p id="prefecture_1" class="p-store-list__pref">北海道</p>
  <div class="p-store-list__area-inner">
    <a href="/store/39">
      <span class="p-store-list__store-name"><i class="las la-store-alt"></i>北見店</span>
      <span class="p-store-list__store-adress">北見市並木町144-19</span>
    </a>
  </div>
</div>
<div class="p-store-list__area-wrap">
  <p id="prefecture_13" class="p-store-list__pref">東京都</p>
  <div class="p-store-list__area-inner">
    <a href="/store/31">
      <span class="p-store-list__store-name"><i class="las la-store-alt"></i>お台場店</span>
      <span class="p-store-list__store-adress">港区台場1-6-1</span>
    </a>
  </div>
  <div class="p-store-list__area-inner">
    <a href="/store/39">
      <span class="p-store-list__store-name"><i class="las la-store-alt"></i>北見店（重複導線）</span>
    </a>
  </div>
</div>
<a href="/store/">店舗一覧トップ</a>
<nav><a href="/store/999">フッターのstoreリンク（店名span無し）</a></nav>
HTML;

// 店舗詳細（情報 dl）: 住所・アクセス・電話・営業時間が揃うケース。★アクセス dd は住所に拾わない。
$detailFull = <<<'HTML'
<p class="p-store-detail__intro">こんにちは！お台場店へようこそ！</p>
<div class="p-store-detail__info">
  <dl class="p-store-detail__store-info">
    <dt>住所</dt><dd>〒135-0091 港区台場1-6-1 DECKS東京ビーチ シーサイドモール1F</dd>
    <dt>アクセス</dt><dd>ゆりかもめ「お台場海浜公園駅」から徒歩2分</dd>
    <dt>電話番号</dt><dd>03-3599-2235</dd>
    <dt>営業時間</dt><dd>11:00〜20:00</dd>
  </dl>
</div>
HTML;

// 店舗詳細（情報 dl）: 電話番号・営業時間の dt が無いケース（→ null で残す）。
$detailNoTel = <<<'HTML'
<div class="p-store-detail__info">
  <dl class="p-store-detail__store-info">
    <dt>住所</dt><dd>〒090-0037 北見市並木町144-19</dd>
  </dl>
</div>
HTML;

it('extracts store name + detail url + prefecture from the list, stripping the icon and dupes/non-stores', function () use ($listHtml) {
    $stores = (new Rental819Fetcher)->extractStores($listHtml);

    expect($stores)->toHaveCount(2);

    expect($stores[0]['id'])->toBe('39')
        ->and($stores[0]['url'])->toBe('https://rental819.com/store/39')
        ->and($stores[0]['name'])->toBe('北見店')     // ★<i> アイコンは混入しない
        ->and($stores[0]['prefecture'])->toBe('北海道'); // ★直前の見出しから

    expect($stores[1]['id'])->toBe('31')
        ->and($stores[1]['name'])->toBe('お台場店')
        ->and($stores[1]['prefecture'])->toBe('東京都'); // ★見出しが変われば追従

    // 店名は英字クラス片やアイコン文字を含まない。
    expect($stores[0]['name'])->not->toContain('la-store')
        ->and($stores[0]['name'])->not->toContain('las');
});

it('strips a trailing parenthetical note from the store name', function () {
    $list = <<<'HTML'
<div class="p-store-list__area-wrap">
  <p class="p-store-list__pref">群馬県</p>
  <div class="p-store-list__area-inner">
    <a href="/store/37">
      <span class="p-store-list__store-name"><i class="las la-store-alt"></i>北軽井沢店(2026年4月移転)</span>
    </a>
  </div>
</div>
HTML;

    $stores = (new Rental819Fetcher)->extractStores($list);

    expect($stores)->toHaveCount(1);
    expect($stores[0]['name'])->toBe('北軽井沢店')       // ★末尾の (…) を除去
        ->and($stores[0]['prefecture'])->toBe('群馬県');
});

it('builds a record using the list name + prefecture and the detail dl (address / postal / tel / hours)', function () use ($detailFull) {
    $store = ['id' => '31', 'url' => 'https://rental819.com/store/31', 'name' => 'お台場店', 'prefecture' => '東京都'];
    $record = (new Rental819Fetcher)->buildRecord($store, $detailFull);

    expect($record)->not->toBeNull()
        ->and($record['name'])->toBe('お台場店')                 // ★店名は一覧から
        ->and($record['postal_code'])->toBe('135-0091')
        ->and($record['address'])->toBe('東京都港区台場1-6-1 DECKS東京ビーチ シーサイドモール1F') // ★市区町村・番地は詳細から
        ->and($record['prefecture'])->toBe('東京都')             // ★都道府県は一覧見出しから
        ->and($record['city'])->toBe('港区')
        ->and($record['tel'])->toBe('03-3599-2235')
        ->and($record['opening_hours'])->toBe('11:00〜20:00')
        ->and($record['external_id'])->toBe('31')
        ->and($record['official_url'])->toBe('https://rental819.com/store/31');

    // ★アクセス dd の案内文は住所に混入しない。
    expect($record['address'])->not->toContain('ゆりかもめ')
        ->and($record['address'])->not->toContain('徒歩');
});

// ★核心の回帰: 詳細ページに都道府県が1度も出ない店（特別区・一般市）でも、一覧見出しの都道府県で埋まる。
//   以前はこの手の店が buildRecord で null 落ちして 103→32 に脱落していた。
it('fills prefecture from the list heading even when the detail address has none (Tokyo ward)', function () {
    $store = ['id' => '3', 'url' => 'https://rental819.com/store/3', 'name' => '阿佐ヶ谷店', 'prefecture' => '東京都'];
    // 詳細の住所 dd に「東京都」が一切出てこない（本番 store/3 と同じ状況）。
    $detail = '<dl class="p-store-detail__store-info"><dt>住所</dt><dd>〒166-0001 杉並区阿佐谷北4-27-3</dd></dl>';

    $record = (new Rental819Fetcher)->buildRecord($store, $detail);

    expect($record)->not->toBeNull()
        ->and($record['postal_code'])->toBe('166-0001')
        ->and($record['prefecture'])->toBe('東京都')                 // ★一覧見出しから補完
        ->and($record['city'])->toBe('杉並区')                       // ★市区町村は詳細から
        ->and($record['address'])->toBe('東京都杉並区阿佐谷北4-27-3'); // ★都道府県を前置して連結
});

// ★回帰: アクセス案内文を住所にしない／営業時間 dt が無ければ null（本文「変更のお知らせ」を拾わない）。
it('ignores access prose and news; hours null when the 営業時間 dt is absent', function () {
    $store = ['id' => '70', 'url' => 'https://rental819.com/store/70', 'name' => '東名横浜店', 'prefecture' => '神奈川県'];
    $detail = <<<'HTML'
<p class="p-store-detail__intro">こんにちは！東名横浜へようこそ！</p>
<div class="p-store-detail__info">
  <dl class="p-store-detail__store-info">
    <dt>住所</dt><dd>〒226-0026 横浜市緑区長津田町5799</dd>
    <dt>アクセス</dt><dd>電車でお越しの方は田園都市線から徒歩5分</dd>
    <dt>電話番号</dt><dd>045-924-6222</dd>
  </dl>
</div>
<section class="p-store-detail__news"><p>営業時間変更のお知らせ</p></section>
HTML;

    $record = (new Rental819Fetcher)->buildRecord($store, $detail);

    expect($record)->not->toBeNull()
        ->and($record['prefecture'])->toBe('神奈川県')
        ->and($record['city'])->toBe('横浜市緑区')
        ->and($record['address'])->toBe('神奈川県横浜市緑区長津田町5799')
        ->and($record['tel'])->toBe('045-924-6222')
        ->and($record['opening_hours'])->toBeNull(); // ★「変更のお知らせ」を拾わない

    // アクセス dd の案内文が住所に混入しない。
    expect($record['address'])->not->toContain('電車')
        ->and($record['address'])->not->toContain('徒歩');
});

// ★回帰: dl の住所は「現住所」。本文の「移転前」を拾わない／住所末尾の括弧書きは除去する。
it('uses the current address from the dl (ignores 移転前 prose) and strips a trailing parenthetical note', function () {
    $store = ['id' => '37', 'url' => 'https://rental819.com/store/37', 'name' => '北軽井沢店', 'prefecture' => '群馬県'];
    $detail = <<<'HTML'
<p class="p-store-detail__intro">【移転のご案内】移転前: 群馬県吾妻郡長野原町北軽井沢1234</p>
<div class="p-store-detail__info">
  <dl class="p-store-detail__store-info">
    <dt>住所</dt><dd>〒377-1524 吾妻郡嬬恋村大字鎌原1053-26（ASAMA PEAKs内）</dd>
    <dt>電話番号</dt><dd>0279-86-3551</dd>
  </dl>
</div>
HTML;

    $record = (new Rental819Fetcher)->buildRecord($store, $detail);

    expect($record)->not->toBeNull()
        ->and($record['prefecture'])->toBe('群馬県')
        ->and($record['city'])->toBe('吾妻郡嬬恋村')                       // ★移転前(長野原町)ではなく現住所
        ->and($record['address'])->toBe('群馬県吾妻郡嬬恋村大字鎌原1053-26') // ★末尾（ASAMA PEAKs内）を除去
        ->and($record['tel'])->toBe('0279-86-3551');

    expect($record['address'])->not->toContain('長野原')
        ->and($record['address'])->not->toContain('ASAMA');
});

it('leaves tel and hours null when the detail dl has no 電話番号 / 営業時間 dt', function () use ($detailNoTel) {
    $store = ['id' => '39', 'url' => 'https://rental819.com/store/39', 'name' => '北見店', 'prefecture' => '北海道'];
    $record = (new Rental819Fetcher)->buildRecord($store, $detailNoTel);

    expect($record)->not->toBeNull()
        ->and($record['name'])->toBe('北見店')
        ->and($record['postal_code'])->toBe('090-0037')
        ->and($record['prefecture'])->toBe('北海道')
        ->and($record['city'])->toBe('北見市')
        ->and($record['tel'])->toBeNull()        // ★無理に埋めない
        ->and($record['opening_hours'])->toBeNull();
});

it('returns null when the store-info dl is missing or has no resolvable city', function () {
    $store = ['id' => '1', 'url' => 'https://rental819.com/store/1', 'name' => 'テスト店', 'prefecture' => '北海道'];

    // 情報 dl そのものが無い → null。
    expect((new Rental819Fetcher)->buildRecord($store, '<main><p>並木町144-19</p></main>'))->toBeNull();

    // dl はあるが住所から市区町村が取れない → null。
    $noCity = '<dl class="p-store-detail__store-info"><dt>住所</dt><dd>並木町144-19</dd></dl>';
    expect((new Rental819Fetcher)->buildRecord($store, $noCity))->toBeNull();
});

it('never returns image-related keys', function () use ($detailFull, $detailNoTel) {
    $banned = ['image', 'image_url', 'images', 'photo', 'photos', 'logo', 'thumbnail', 'thumb', 'img'];
    foreach ([$detailFull, $detailNoTel] as $i => $html) {
        $record = (new Rental819Fetcher)->buildRecord(
            ['id' => (string) $i, 'url' => "https://rental819.com/store/{$i}", 'name' => '店', 'prefecture' => '東京都'],
            $html
        );
        expect(array_intersect($banned, array_keys($record)))->toBe([]);
    }
});

it('exposes public identity (slug / company / official url)', function () {
    $f = new Rental819Fetcher;

    expect($f->slug())->toBe('rental819')
        ->and($f->company())->toBe('レンタル819')
        ->and($f->officialUrl())->toBe('https://rental819.com/');
});
