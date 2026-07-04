<?php

use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

function mkShop(string $name, array $overrides = []): Shop
{
    static $seq = 0;
    $seq++;

    return Shop::create(array_merge([
        'name' => $name,
        'prefecture' => '東京都',
        'city' => '世田谷区',
        'address' => "addr-{$seq}",
        'shop_type' => 'dealer',
        'source' => 'scraper',
    ], $overrides));
}

// ---- routing ----

it('resolves /shops/search to the search page (not eaten by the wildcard route)', function () {
    $this->get('/shops/search?q=てすと')
        ->assertOk()
        ->assertSee('店名検索'); // パンくず（検索ページ固有）
});

// ---- normalized search + fallback ----

it('matches regardless of hiragana/katakana and width', function () {
    mkShop('モトパドックキムラ');

    // ひらがな・全角英字問わずヒット
    $this->get('/shops/search?q='.urlencode('もとぱどっく'))->assertOk()->assertSee('モトパドックキムラ');
});

it('treats YSP / ysp / ＹＳＰ as the same result set', function () {
    mkShop('YSP世田谷');

    foreach (['YSP', 'ysp', 'ＹＳＰ'] as $q) {
        $this->get('/shops/search?q='.urlencode($q))->assertOk()->assertSee('YSP世田谷');
    }
});

it('falls back to name when name_normalized is NULL (fresh scrape not yet backfilled)', function () {
    $shop = mkShop('レッドバロン府中');
    // スクレイパー投入直後を再現: name_normalized を NULL に落とす
    DB::table('shops')->where('id', $shop->id)->update(['name_normalized' => null]);

    $this->get('/shops/search?q='.urlencode('レッドバロン'))
        ->assertOk()
        ->assertSee('レッドバロン府中');
});

// ---- ranking: exact > prefix > partial ----

it('ranks exact before prefix before partial', function () {
    mkShop('ビッグモトパ');   // 部分一致
    mkShop('モトパドック');   // 前方一致
    mkShop('モトパ');         // 完全一致

    $this->get('/shops/search?q='.urlencode('モトパ'))
        ->assertOk()
        ->assertSeeInOrder(['>モトパ</h3>', 'ドック', 'ビッグ']);
});

// ---- filters ----

it('filters by prefecture and shop_type', function () {
    mkShop('カワサキプラザ東京', ['prefecture' => '東京都']);
    mkShop('カワサキプラザ大阪', ['prefecture' => '大阪府']);
    mkShop('カワサキ整備', ['prefecture' => '東京都', 'shop_type' => 'repair_only']);

    // pref フィルタ
    $this->get('/shops/search?q='.urlencode('カワサキ').'&pref='.urlencode('東京都'))
        ->assertOk()->assertSee('カワサキプラザ東京')->assertDontSee('カワサキプラザ大阪');

    // type フィルタ
    $this->get('/shops/search?q='.urlencode('カワサキ').'&type=repair_only')
        ->assertOk()->assertSee('カワサキ整備')->assertDontSee('カワサキプラザ東京');
});

// ---- short query ----

it('shows guidance (not an error) for a query shorter than 2 chars', function () {
    mkShop('あ亭');

    $this->get('/shops/search?q='.urlencode('あ'))
        ->assertOk()
        ->assertSee('2文字以上');
});

// ---- pagination ----

it('paginates at 20 per page', function () {
    for ($i = 1; $i <= 21; $i++) {
        mkShop("ホンダドリーム{$i}号店");
    }

    $res = $this->get('/shops/search?q='.urlencode('ホンダドリーム'))->assertOk();
    $res->assertSee('21件');       // 総件数
    $res->assertSee('?q=');        // 次ページリンク（クエリ保持）
});

// ---- noindex ----

it('emits a noindex robots meta', function () {
    $this->get('/shops/search?q='.urlencode('てすと'))
        ->assertOk()
        ->assertSee('noindex', false);
});

// ---- XSS ----

it('escapes a script tag in the query', function () {
    $res = $this->get('/shops/search?q='.urlencode('<script>alert(1)</script>'))->assertOk();
    $res->assertDontSee('<script>alert(1)</script>', false);       // 生タグは出さない
    $res->assertSee('&lt;script&gt;', false);                       // エスケープ済み
});

// ---- zero-hit → submission funnel ----

it('shows a submission CTA carrying the name (and pref) on zero hits', function () {
    $res = $this->get('/shops/search?q='.urlencode('絶対に存在しない店ABC').'&pref='.urlencode('東京都'))->assertOk();

    $res->assertSee('見つかりませんでした');
    $res->assertSee('name='.rawurlencode('絶対に存在しない店ABC'), false);
    $res->assertSee('pref='.rawurlencode('東京都'), false);
});

it('logs a zero_hit entry with the raw query', function () {
    Log::spy();

    $this->get('/shops/search?q='.urlencode('存在しない店XYZ'))->assertOk();

    Log::shouldHaveReceived('info')->withArgs(
        fn ($msg, $ctx = []) => $msg === 'shop_search_zero_hit' && ($ctx['q'] ?? null) === '存在しない店XYZ'
    );
});

it('does not log zero_hit when there are results', function () {
    Log::spy();
    mkShop('スズキワールド');

    $this->get('/shops/search?q='.urlencode('スズキワールド'))->assertOk();

    Log::shouldNotHaveReceived('info', ['shop_search_zero_hit']);
});

// ---- ?name= prefill on the submit form ----

it('prefills the submit form shop_name from ?name=', function () {
    $this->get('/shops/submit?name='.urlencode('プリフィル店'))
        ->assertOk()
        ->assertSee('value="プリフィル店"', false);
});
