<?php

use App\Models\RentalBikeShop;

/**
 * レンタルバイク店舗の導線（ナビ／フッター）とサイトマップ登録（第3弾）。
 *
 * ★新規ページは作らない。既存の仕組み（ナビ「その他」・フッター・sitemap:generate）に足すだけ。
 * ★外部アクセスなし。既存ナビ・フッターの他項目が壊れないことも検証する。
 */

/** テスト用の店舗を1件作る（RentalBikePagesTest とグローバル関数名が衝突しないよう別名）。 */
function seedRentalBikeShopForSitemap(string $prefecture, string $name): RentalBikeShop
{
    return RentalBikeShop::create([
        'company' => 'レンタルバイクセンター',
        'company_slug' => 'bikecenter',
        'external_id' => null,
        'name' => $name,
        'address' => $prefecture.'テスト1-1',
        'prefecture' => $prefecture,
        'city' => 'テスト市',
        'tel' => null,
        'opening_hours' => null,
        'official_url' => 'https://example.test/shop',
        'is_active' => true,
        'fetched_at' => now(),
        'dedup_key' => RentalBikeShop::makeDedupKey('bikecenter', null, $name, $prefecture.'テスト1-1'),
    ]);
}

it('adds rental-bike (and rental-garage) links to the nav "その他" dropdown', function () {
    $res = $this->get('/');

    $res->assertOk()
        // ★ナビから /rental-bikes に到達できる。
        ->assertSee('rental-bikes', false)
        ->assertSee('レンタルバイク')
        // レンタルガレージもツール節へ（従来フッターのみ）。
        ->assertSee('レンタルガレージ');
});

it('adds a rental-bike link to the footer next to rental-garage', function () {
    $res = $this->get('/');

    $res->assertOk()
        ->assertSee('レンタルバイクを探す')
        // ★既存フッター項目が壊れていない。
        ->assertSee('エリアからレンタルガレージを探す')
        ->assertSee('エリアからバイクショップを探す');
});

it('keeps existing nav items intact', function () {
    $res = $this->get('/');

    $res->assertOk()
        ->assertSee('ツーリングガイド・スポット')
        ->assertSee('バイク免許')
        ->assertSee('AR駐車場ファインダー')
        ->assertSee('AIで探す');
});

it('reaches the rental-bikes index from its nav url', function () {
    seedRentalBikeShopForSitemap('東京都', 'テスト店舗');

    $this->get('/rental-bikes')->assertOk()->assertSee('レンタルバイク店舗一覧');
});

it('registers rental-bike urls in the sitemap generator source', function () {
    $src = file_get_contents(app_path('Console/Commands/GenerateSitemap.php'));

    expect($src)
        ->toContain("route('rental-bike.index')")
        ->toContain("route('rental-bike.prefecture', \$pref)")
        ->toContain("route('rental-bike.show', \$shop->id)")
        ->toContain('sitemap-rental-bike.xml');
});

it('writes 29 rental-bike urls to the sitemap (1 index + 4 prefectures + 24 shops)', function () {
    // sitemap:generate は public/ に sitemap-*.xml を書き出す（gitignore 済みの生成物）。
    // public/ が書込不可の環境ではスキップ（本番で生成される）。
    if (! is_writable(public_path())) {
        $this->markTestSkipped('public/ が書込不可の環境。サイトマップのファイル生成は本番で検証する。');
    }

    // 既存の sitemap-*.xml（他所有の場合 fopen 'w' が失敗する）を先に除去してから再生成する。
    // ディレクトリが書込可なら所有者に関わらず unlink できる。テスト後も finally で消すため残さない。
    foreach (glob(public_path('sitemap*.xml')) ?: [] as $stale) {
        @unlink($stale);
    }

    // 4都道府県 × 6件 = 24件。→ index 1 + 都道府県 4 + 詳細 24 = 29 URL。
    foreach (['東京都', '神奈川県', '千葉県', '埼玉県'] as $pref) {
        for ($i = 1; $i <= 6; $i++) {
            seedRentalBikeShopForSitemap($pref, "{$pref}店舗{$i}");
        }
    }

    $file = public_path('sitemap-rental-bike.xml');
    try {
        $this->artisan('sitemap:generate')->assertExitCode(0);

        expect(file_exists($file))->toBeTrue();
        $xml = file_get_contents($file);
        expect(substr_count($xml, '<url>'))->toBe(29);
        expect($xml)->toContain('/rental-bikes');
    } finally {
        // テストで生成した sitemap を後始末（public/ を汚さない）。
        foreach (glob(public_path('sitemap*.xml')) ?: [] as $f) {
            @unlink($f);
        }
    }
});
