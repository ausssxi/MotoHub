<?php

declare(strict_types=1);

use App\Models\RentalGarage;
use App\Models\RentalGarageClick;
use App\Support\BotDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * レンタルガレージ 送客中継ルート（/go/rental-garage/{id}）の計測テスト。
 * IPは保存しない・botは記録するが集計除外、を担保する。
 */

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $overrides
 */
function makeClickGarage(array $overrides = []): RentalGarage
{
    return RentalGarage::create(array_merge([
        'name' => 'テスト事業者 サンプル店',
        'operator' => 'テスト事業者',
        'garage_type' => 'indoor',
        'prefecture' => '東京都',
        'city' => '新宿区',
        'address' => '東京都新宿区1-1-1',
        'source_url' => 'https://example.test/detail/'.bin2hex(random_bytes(4)),
        'website_url' => 'https://operator.example/garage',
        'is_active' => true,
    ], $overrides));
}

it('クリックを記録して302で外部URLへ飛ばす（人間UA）', function () {
    $garage = makeClickGarage();

    $res = $this->withHeaders([
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0) AppleWebKit/537.36 Chrome/120 Safari/537.36',
        'Referer' => 'https://'.parse_url(config('app.url'), PHP_URL_HOST).'/rental-garages/'.$garage->id.'?utm=x',
    ])->get('/go/rental-garage/'.$garage->id);

    $res->assertRedirect('https://operator.example/garage');
    expect($res->getStatusCode())->toBe(302);

    $click = RentalGarageClick::sole();
    expect($click->rental_garage_id)->toBe($garage->id)
        ->and($click->is_bot)->toBeFalse()
        ->and($click->referrer_path)->toBe('/rental-garages/'.$garage->id) // クエリは落とす
        ->and($click->clicked_at)->not->toBeNull();

    // IP列が存在しないこと（個人情報を持たない）
    expect(array_key_exists('ip', $click->getAttributes()))->toBeFalse();
    expect(\Illuminate\Support\Facades\Schema::hasColumn('rental_garage_clicks', 'ip'))->toBeFalse();
});

it('bot UA は is_bot=true で記録し、集計からは除外される', function () {
    $garage = makeClickGarage();

    $this->withHeaders(['User-Agent' => 'Googlebot/2.1 (+http://www.google.com/bot.html)'])
        ->get('/go/rental-garage/'.$garage->id)
        ->assertRedirect('https://operator.example/garage');

    expect(RentalGarageClick::sole()->is_bot)->toBeTrue();

    // 既定（bot除外）では 0 件、--include-bots で 1 件になる
    $this->artisan('rental-garage:clicks')
        ->expectsOutputToContain('送客クリック合計')
        ->assertExitCode(0);

    expect(RentalGarageClick::where('is_bot', false)->count())->toBe(0)
        ->and(RentalGarageClick::count())->toBe(1);
});

it('集計コマンドが日別/ガレージ別/事業者別を出力する（bot除外）', function () {
    $a = makeClickGarage(['name' => 'ガレージA', 'operator' => 'イナバ']);
    $b = makeClickGarage(['name' => 'ガレージB', 'operator' => '加瀬']);

    // 人間クリック: A×2, B×1、bot×1（除外対象）
    foreach ([$a, $a, $b] as $g) {
        RentalGarageClick::create(['rental_garage_id' => $g->id, 'clicked_at' => now(), 'is_bot' => false]);
    }
    RentalGarageClick::create(['rental_garage_id' => $a->id, 'clicked_at' => now(), 'is_bot' => true]);

    $this->artisan('rental-garage:clicks')
        ->expectsOutputToContain('送客クリック合計')
        ->expectsOutputToContain('■ 日別')
        ->expectsOutputToContain('■ ガレージ別')
        ->expectsOutputToContain('■ 事業者別')
        ->expectsOutputToContain('イナバ')
        ->expectsOutputToContain('加瀬')
        ->assertExitCode(0);

    // bot除外で human=3。--by の絞り込みも動く。
    expect(RentalGarageClick::where('is_bot', false)->count())->toBe(3);
    $this->artisan('rental-garage:clicks', ['--by' => 'operator'])->assertExitCode(0);
    $this->artisan('rental-garage:clicks', ['--by' => 'bogus'])->assertExitCode(1);
});

it('website_url が無ければ記録せず詳細ページへ戻す', function () {
    $garage = makeClickGarage(['website_url' => null]);

    $this->get('/go/rental-garage/'.$garage->id)
        ->assertRedirect(route('rental-garage.show', $garage->id));

    expect(RentalGarageClick::count())->toBe(0);
});

it('is_active=false は404（is_active の扱いは詳細ページと同じ）', function () {
    $garage = makeClickGarage(['is_active' => false]);

    $this->get('/go/rental-garage/'.$garage->id)->assertNotFound();
    expect(RentalGarageClick::count())->toBe(0);
});

it('外部サイトからの Referer はパスを保存しない', function () {
    $garage = makeClickGarage();

    $this->withHeaders(['Referer' => 'https://external.example/some/page'])
        ->get('/go/rental-garage/'.$garage->id);

    expect(RentalGarageClick::sole()->referrer_path)->toBeNull();
});

it('BotDetector は空UA・クローラーを検出し、通常ブラウザは通す', function () {
    expect(BotDetector::isBot(''))->toBeTrue()
        ->and(BotDetector::isBot(null))->toBeTrue()
        ->and(BotDetector::isBot('python-requests/2.31'))->toBeTrue()
        ->and(BotDetector::isBot('facebookexternalhit/1.1'))->toBeTrue()
        ->and(BotDetector::isBot('Mozilla/5.0 (iPhone) AppleWebKit Safari'))->toBeFalse();
});
