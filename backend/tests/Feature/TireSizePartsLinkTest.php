<?php

declare(strict_types=1);

use App\Models\BikeModel;
use App\Models\Manufacturer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    // TireSize::pageableIndex/pageData は array キャッシュに焼く。テスト間で残ると
    // 別サイズのシードが索引に反映されず 404 になるため、毎回クリアする。
    Cache::flush();
    // /parts の index が外部APIを叩かないように（未設定なら元々叩かないが念のため）。
    Http::fake();
});

/**
 * 前輪サイズが同一の車種を count 台つくり、そのサイズをページ化対象（5件以上）にする。
 */
function seedTireModels(string $front, int $count = 6, string $rear = '180/55ZR17'): void
{
    $mfr = Manufacturer::where('slug', 'honda')->first();
    if (! $mfr) {
        $mfr = new Manufacturer(['slug' => 'honda']);
        $mfr->name = 'ホンダ';
        $mfr->save();
    }

    for ($i = 0; $i < $count; $i++) {
        $m = new BikeModel([
            'manufacturer_id' => $mfr->id,
            'name' => "テスト車種{$i}",
            'slug' => 'test-'.md5($front.'-'.$i),
        ]);
        $m->tire_size_front = $front;
        $m->tire_size_rear = $rear;
        $m->save();
    }
}

it('shows the tire product search CTA above the model list', function () {
    seedTireModels('120/70ZR17');

    $res = $this->get('/bikes/tire-size/120-70zr17')->assertOk();

    $res->assertSee('このサイズのタイヤを探す');
    $res->assertSee('楽天市場とYahoo!ショッピング');
});

it('uses the display-form size (not the slug) as the keyword and url-encodes it', function () {
    seedTireModels('120/70ZR17');

    $res = $this->get('/bikes/tire-size/120-70zr17')->assertOk();

    // 期待 URL（route() が RFC3986 でエンコード）。assertSee はニードルを e() でエスケープするため
    // href 内の &amp; とも一致する。
    $expected = route('parts.index', ['keyword' => '120/70ZR17 タイヤ', 'from' => 'tire-size']);
    $res->assertSee($expected);

    // スラッシュは %2F、スペースは %20 にエンコードされる。
    $res->assertSee('keyword=120%2F70ZR17%20', false);
    // 計測パラメータ from。
    $res->assertSee('from=tire-size', false);
    // slug 表記（120-70zr17）がキーワードに使われていないこと。
    $res->assertDontSee('keyword=120-70zr17', false);
});

it('encodes period-containing sizes without breaking (e.g. 2.75-10)', function () {
    seedTireModels('2.75-10');

    $res = $this->get('/bikes/tire-size/2-75-10')->assertOk();

    // 表示・ボタン文言はピリオドを含む表示形。
    $res->assertSee('2.75-10 のタイヤ');
    // ピリオドは RFC3986 の非予約文字なのでそのまま。キーワードは表示形（2.75-10）で slug（2-75-10）ではない。
    $res->assertSee('keyword=2.75-10%20', false);
    $res->assertDontSee('keyword=2-75-10', false);
});

it('keeps /bikes/tire-size and the detail page returning 200', function () {
    seedTireModels('120/70ZR17');

    $this->get('/bikes/tire-size')->assertOk();
    $this->get('/bikes/tire-size/120-70zr17')->assertOk();
});

it('opens /parts with a keyword and keeps the plain /parts working', function () {
    // キーワード付き（tire-size 由来）と、キーワード無しの既存挙動。どちらも 200。
    $this->get('/parts?keyword='.rawurlencode('120/70ZR17 タイヤ').'&from=tire-size')->assertOk();
    $this->get('/parts')->assertOk();
});

it('does not leak the from parameter into the canonical url', function () {
    // canonical は url()->current()（クエリ非依存）なので from は入らない。
    $res = $this->get('/parts?keyword=test&from=tire-size')->assertOk();

    $res->assertDontSee('rel="canonical" href="'.url('/parts').'?', false);
    $res->assertSee('<link rel="canonical" href="'.url('/parts').'">', false);
});
