<?php

declare(strict_types=1);

use App\Models\BikeModel;
use App\Models\Manufacturer;
use App\Models\MyBike;
use App\Models\User;

// TOPページは ListingSearchService が Cache::store('redis') を使う。テスト環境(redis無し)で描画できるよう
// redis ストアを array に差し替える。各テスト冒頭で呼ぶ。
function fakeRedisStore(): void
{
    config(['cache.stores.redis' => ['driver' => 'array']]);
}

function homeBike(string $real = '本名タロウ', ?string $handle = 'rider_x', string $email = 'taro@example.com', bool $withToken = true): MyBike
{
    $user = User::factory()->create(['name' => $real, 'review_display_name' => $handle, 'email' => $email]);
    if ($withToken) {
        $user->ensurePublicToken();
    }
    $mfr = Manufacturer::where('slug', 'honda')->first() ?? Manufacturer::forceCreate(['name' => 'Honda', 'slug' => 'honda']);
    $model = BikeModel::where('slug', 'pcx')->first() ?? BikeModel::create(['manufacturer_id' => $mfr->id, 'name' => 'PCX', 'slug' => 'pcx']);

    return MyBike::create(['user_id' => $user->id, 'bike_model_id' => $model->id, 'name' => 'マイPCX', 'is_public' => true, 'initial_odometer' => 0, 'current_odometer' => 1000]);
}

it('home links the みんなの愛車 handle to the rider profile', function () {
    fakeRedisStore();
    $bike = homeBike();
    $token = $bike->user->public_token;

    $this->get('/')
        ->assertOk()
        ->assertSee('rider_x')
        ->assertSee(route('riders.profile', $token), false);
});

it('home never leaks the real name or email in the みんなの愛車 section', function () {
    fakeRedisStore();
    $bike = homeBike('漏れ太郎', 'rider_x', 'leak@example.com');

    $res = $this->get('/')->assertOk();
    expect($res->getContent())
        ->not->toContain('漏れ太郎')
        ->not->toContain('leak@example.com');
});

it('home shows the handle non-linked for users without a public token (graceful)', function () {
    fakeRedisStore();
    // トークン未発番（手動で is_public のみ立てる＝発番フローを通さない既存データ相当）
    $bike = homeBike('名無し本名', 'rider_notoken', 'nt@example.com', withToken: false);

    $res = $this->get('/')->assertOk();
    $html = $res->getContent();
    expect($html)->toContain('rider_notoken')                         // ハンドルは出る
        ->not->toContain('/riders/');                                 // ただしプロフィールリンクは無い
    expect($bike->user->fresh()->public_token)->toBeNull();
});

it('home has no nested anchors in the みんなの愛車 cards (no a-in-a)', function () {
    fakeRedisStore();
    homeBike();

    $html = $this->get('/')->assertOk()->getContent();

    // 「みんなの愛車」セクションを抽出し、a の入れ子が無いことを確認
    $doc = new DOMDocument;
    @$doc->loadHTML('<?xml encoding="UTF-8">'.$html);
    $xp = new DOMXPath($doc);
    // どの <a> も、子孫に <a> を持たない
    $nestedAnchors = $xp->query('//a//a');
    expect($nestedAnchors->length)->toBe(0);
});
