<?php

declare(strict_types=1);

use App\Models\TouringSpot;
use App\Support\SeasonalSpots;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::flush());

/** @param array<string,mixed> $attrs */
function autumnSpot(array $attrs = []): TouringSpot
{
    static $i = 0;
    $i++;
    $s = (new TouringSpot)->forceFill(array_merge([
        'prefecture' => '群馬県',
        'slug' => 'spot-'.$i,
        'name' => 'スポット'.$i,
        'lat' => 36.4,
        'lng' => 139.0,
    ], $attrs));
    $s->save();

    return $s;
}

// ─────────── スコア判定 ───────────

it('scores by confidence: season=3, name/description=2, content-only=1, none=excluded', function () {
    autumnSpot(['name' => '見頃スポット', 'recommended_season' => '4月〜11月（紅葉は10月下旬〜11月上旬）']);
    autumnSpot(['name' => '概要スポット', 'description' => '秋は紅葉がきれいです']);
    autumnSpot(['name' => '本文スポット', 'content' => '道中どこかで紅葉に触れる本文']);
    autumnSpot(['name' => '無関係スポット', 'description' => '海が見える']);

    $r = SeasonalSpots::forKeyword('紅葉');

    expect($r['total'])->toBe(3); // 無関係は対象外
    expect(collect($r['score3'])->pluck('name'))->toContain('見頃スポット');
    expect($r['score3'][0]['season'])->toContain('紅葉は10月下旬');
    expect(collect($r['score2_areas'])->flatMap(fn ($a) => collect($a['items'])->pluck('name')))->toContain('概要スポット');
    expect(collect($r['score1'])->pluck('name'))->toContain('本文スポット');
    // 本文のみ(score1)が score3/2 に紛れないこと。
    expect(collect($r['score3'])->pluck('name'))->not->toContain('本文スポット');
});

it('does not load the heavy content column for scoring (single query)', function () {
    autumnSpot(['description' => '紅葉スポット']);
    autumnSpot(['content' => '本文に紅葉']);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $r = SeasonalSpots::forKeyword('紅葉');
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($r['total'])->toBe(2);
    expect($count)->toBe(1); // 1クエリで全件取得（N+1なし）
    // content 本文はロードしていない（select に含めない）ことを確認。
    expect(array_key_exists('content', $r['score1'][0] ?? []))->toBeFalse();
});

// ─────────── エリア分類 ───────────

it('classifies prefectures into 8 regions and drops empty ones in order', function () {
    autumnSpot(['prefecture' => '群馬県', 'description' => '紅葉A']); // 関東
    autumnSpot(['prefecture' => '富山県', 'description' => '紅葉B']); // 中部
    autumnSpot(['prefecture' => '大分県', 'description' => '紅葉C']); // 九州

    $areas = collect(SeasonalSpots::forKeyword('紅葉')['score2_areas'])->pluck('region')->all();

    expect($areas)->toBe(['関東', '中部', '九州']); // 規定順・0件エリアは無い
});

// ─────────── ページ ───────────

it('renders the autumn feature page with score3 above score1 and dynamic count', function () {
    autumnSpot(['name' => '見頃の名所', 'recommended_season' => '紅葉は11月上旬']);
    autumnSpot(['name' => 'エリアの名所', 'prefecture' => '群馬県', 'description' => '秋の紅葉が見事']);
    autumnSpot(['name' => '本文だけの場所', 'content' => 'ふと紅葉に気づく']);

    $res = $this->get('/touring/autumn')->assertOk();
    $html = $res->getContent();

    // 件数は固定文言でなく実データ（3箇所）。
    $res->assertSee('3箇所');
    // スコア3が score1 より前に出る。
    expect(mb_strpos($html, '見頃の名所'))->toBeLessThan(mb_strpos($html, '本文だけの場所'));
    // score1 はテキストリンク（「ほかにも…」見出し配下）。
    $res->assertSee('ほかにも紅葉が楽しめるスポット');
    // リンク先が touring.spot.show（404にならない実在ルート）。
    $spot = TouringSpot::where('name', '見頃の名所')->first();
    $res->assertSee(route('touring.spot.show', ['prefectureSlug' => 'gunma', 'spot' => $spot->slug]), false);
});

it('caches the page data under a versioned key', function () {
    autumnSpot(['description' => '紅葉']);

    $this->get('/touring/autumn')->assertOk();

    expect(Cache::has('touring:autumn:v1'))->toBeTrue();
    $this->get('/touring/autumn')->assertOk(); // 2回目もキャッシュから200
});

it('keeps /touring returning 200 with the feature banner', function () {
    $res = $this->get('/touring')->assertOk();
    $res->assertSee(route('touring.autumn'), false);
    $res->assertSee('紅葉ツーリングスポット');
});
