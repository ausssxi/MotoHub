<?php

use App\Models\BikeParking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function searchParking(string $name, string $address, array $attrs = []): BikeParking
{
    return BikeParking::create(array_merge([
        'name' => $name, 'address' => $address,
        'latitude' => 35.54, 'longitude' => 139.44,
        'prefecture' => '東京都', 'city' => '町田市', 'parking_type' => 'bike_only',
    ], $attrs));
}

// ─────────── A: 駐車場名検索 ───────────

it('shows the parking-name search box and register CTA on /parking/area', function () {
    $this->get('/parking/area')->assertOk()
        ->assertSee('駐車場名で探す')
        ->assertSee(route('parking.name-search'), false)
        ->assertSee('駐車場を登録する')
        ->assertSee(route('parking.create'), false);
});

it('finds a parking by a partial name and links to its detail page', function () {
    $p = searchParking('町田森野第一駐車場', '東京都町田市森野1-2-3');

    $res = $this->get('/parking/search?q='.urlencode('町田森野'))->assertOk();
    $res->assertSee('町田森野第一駐車場')
        ->assertSee(route('parking.show', $p), false);
});

it('matches a space-separated compound query (地名＋部分)', function () {
    searchParking('町田森野第一駐車場', '東京都町田市森野1-2-3');
    searchParking('別の駐車場', '神奈川県横浜市1-1');

    $res = $this->get('/parking/search?q='.urlencode('町田 森野'))->assertOk();
    $res->assertSee('町田森野第一駐車場')
        ->assertDontSee('別の駐車場'); // 両トークンを満たす駐車場のみ
});

it('matches on address tokens too', function () {
    searchParking('第一パーキング', '東京都町田市森野2-2');

    $this->get('/parking/search?q='.urlencode('町田 森野'))->assertOk()
        ->assertSee('第一パーキング'); // name に無くても address が両トークンを満たす
});

it('shows a register CTA when the search has zero hits', function () {
    $this->get('/parking/search?q='.urlencode('存在しない駐車場名XYZ'))->assertOk()
        ->assertSee('見つかりませんでした')
        ->assertSee('駐車場を登録する')
        ->assertSee(route('parking.create'), false);
});

it('guides the user when the query is too short', function () {
    $this->get('/parking/search?q='.urlencode('あ'))->assertOk()
        ->assertSee('2文字以上');
});

it('excludes inactive parkings from search results', function () {
    searchParking('非公開駐車場', '東京都町田市森野9-9', ['is_active' => false]);

    $this->get('/parking/search?q='.urlencode('森野'))->assertOk()
        ->assertDontSee('非公開駐車場');
});

// ─────────── B: 登録導線＝マップと同じフロー（安全弁の連動） ───────────

it('routes the register CTA to the auth-gated parking.create (login required)', function () {
    // 未ログインは parking.create（auth）へ行くとログインへリダイレクト＝マップと同じ安全弁
    $this->get(route('parking.create'))->assertRedirect(route('login'));
});

it('connects a registered (user, recent) parking to the review new-guard', function () {
    $user = User::factory()->create();
    $p = searchParking('登録したての駐車場', '東京都町田市森野3-3', ['user_id' => $user->id]);

    // 登録した駐車場は user_id 有り＝新規ガード対象＝レビューが承認待ちになる（マップ登録と同一挙動）
    expect($p->isNewUserParking())->toBeTrue();
});
