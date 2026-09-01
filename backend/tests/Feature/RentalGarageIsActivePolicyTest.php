<?php

declare(strict_types=1);

use App\Models\RentalGarage;
use App\Services\RentalGarage\Scrapers\AbstractRentalGarageScraper;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * rental_garage:fetch の is_active 自動化ポリシー回帰テスト。
 *
 * 方針（全社共通・operator別分岐なし）: 既存レコード更新時、is_active は「非公開方向」だけ自動化する。
 *   - スクレイプが開店前(OPEN予定→is_active=false)を検出 → false に上書き
 *   - それ以外（通常営業=true）→ 既存の is_active を維持（更新データから除外）
 *   - 新規作成時は従来どおりスクレイプ値をそのまま使う
 * 狙い: 手動で非公開にした運用判断を、定期実行が黙って再公開しないため。
 */

uses(RefreshDatabase::class);

// テスト用スクレイパー: 設定した行を yield するだけ（ネットワークに触れない）。
final class FakeGarageScraper extends AbstractRentalGarageScraper
{
    /** @var array<int, array<string, mixed>> */
    public static array $rows = [];

    public function key(): string
    {
        return 'faketest';
    }

    public function label(): string
    {
        return 'テスト事業者';
    }

    public function fetch(?int $limit = null): iterable
    {
        foreach (self::$rows as $row) {
            yield $row;
        }
    }
}

beforeEach(function () {
    FakeGarageScraper::$rows = [];
    config(['rental_garages.scrapers' => ['faketest' => FakeGarageScraper::class]]);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function fakeGarageRow(array $overrides = []): array
{
    return array_merge([
        'name' => 'テスト事業者 サンプル店',
        'operator' => 'テスト事業者',
        'garage_type' => 'indoor',
        'prefecture' => '東京都',
        'city' => '新宿区',
        'address' => '東京都新宿区1-1-1',
        'source_url' => 'https://example.test/detail/1',
        'is_active' => true,
    ], $overrides);
}

it('既存レコード: OPEN予定を検出したら is_active=false に上書きする', function () {
    $url = 'https://example.test/detail/open';
    RentalGarage::create(fakeGarageRow(['source_url' => $url, 'is_active' => true, 'source' => 'official']));

    // スクレイプ結果が is_active=false（＝本文に OPEN予定 を検出）。
    // source_url は seen に入るので「掲載終了検知」の非公開化対象ではない
    // ＝ false になるのは更新ロジックの上書きによるものだと担保される。
    FakeGarageScraper::$rows = [fakeGarageRow(['source_url' => $url, 'is_active' => false])];

    $this->artisan('rental_garage:fetch', ['--operator' => 'faketest'])->assertExitCode(0);

    expect(RentalGarage::where('source_url', $url)->first()->is_active)->toBeFalse();
});

it('★既存が is_active=false の行は、通常営業でも false のまま（勝手に再公開しない）', function () {
    $url = 'https://example.test/detail/hidden';
    // 運用判断で手動非公開にした既存レコードを模す。
    RentalGarage::create(fakeGarageRow(['source_url' => $url, 'is_active' => false, 'source' => 'official']));

    // スクレイプは通常営業（is_active=true）として返す。従来はここで true に再公開されていた。
    FakeGarageScraper::$rows = [fakeGarageRow(['source_url' => $url, 'is_active' => true])];

    $this->artisan('rental_garage:fetch', ['--operator' => 'faketest'])->assertExitCode(0);

    expect(RentalGarage::where('source_url', $url)->first()->is_active)->toBeFalse();
});

it('新規作成時はスクレイプ値がそのまま入る（true も false も）', function () {
    $activeUrl = 'https://example.test/detail/new-active';
    $openUrl = 'https://example.test/detail/new-open';

    FakeGarageScraper::$rows = [
        fakeGarageRow(['source_url' => $activeUrl, 'name' => '新規A', 'is_active' => true]),
        fakeGarageRow(['source_url' => $openUrl, 'name' => '新規B(OPEN予定)', 'is_active' => false]),
    ];

    $this->artisan('rental_garage:fetch', ['--operator' => 'faketest'])->assertExitCode(0);

    expect(RentalGarage::where('source_url', $activeUrl)->first()->is_active)->toBeTrue()
        ->and(RentalGarage::where('source_url', $openUrl)->first()->is_active)->toBeFalse();
});
