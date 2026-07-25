<?php

declare(strict_types=1);

use App\Models\BikeParking;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * 駐車場の運営分類（地図ピンの色分け）。
 *
 * 判定は2系統:
 *   (A) management_company が市区町村名で終わる / NULL・空 → null（緑🅿️）
 *   (B) それ以外＝具体的な組織名 → config の固有ブランドキー or 'other'（共通色バッジ）
 * 詳しくは BikeParking::parkingBrand() / config/parking.php brands。
 */

// ─────────── 判定ロジック（BikeParking::parkingBrand） ───────────

it('classifies pure municipality names / null as green (null brand)', function () {
    expect(BikeParking::parkingBrand('仙台市'))->toBeNull()
        ->and(BikeParking::parkingBrand('所沢市'))->toBeNull()
        ->and(BikeParking::parkingBrand('東京都渋谷区'))->toBeNull() // 区で終わる＝自治体名
        ->and(BikeParking::parkingBrand('仙台市　'))->toBeNull()      // 末尾の全角空白は無視
        ->and(BikeParking::parkingBrand(null))->toBeNull()
        ->and(BikeParking::parkingBrand(''))->toBeNull()
        ->and(BikeParking::parkingBrand('   '))->toBeNull();
});

it('resolves the major brands (akippa / エコステーション21) absorbing 英字・カナ表記ゆれ', function () {
    // akippa: 英字・カナ両表記
    expect(BikeParking::parkingBrand('akippa株式会社'))->toBe('akippa')
        ->and(BikeParking::parkingBrand('アキッパ株式会社'))->toBe('akippa')
        // エコステーション21: 英字「Ecostation」/カナ「エコステーション」両吸収
        ->and(BikeParking::parkingBrand('Ecostation21'))->toBe('ecostation')
        ->and(BikeParking::parkingBrand('エコステーション21'))->toBe('ecostation');
});

it('classifies other concrete organizations as the common "other" badge (not green)', function () {
    // 市名の後に組織名が続く＝(B)。緑ではなく共通バッジ。
    expect(BikeParking::parkingBrand('岡山市北区役所維持管理課 自転車・駐車場係'))->toBe('other')
        ->and(BikeParking::parkingBrand('株式会社アース・カー'))->toBe('other')          // カーシェアは固有色にせず共通色
        ->and(BikeParking::parkingBrand('公益財団法人自転車駐車場整備センター'))->toBe('other')
        ->and(BikeParking::parkingBrand('広島県ビルメンテナンス協同組合'))->toBe('other');
});

// ─────────── 運営表示名（parkingOperatorLabel） ───────────

it('exposes brand display names and shortens long operator names', function () {
    expect(BikeParking::parkingOperatorLabel('akippa株式会社', 'akippa'))->toBe('akippa')
        ->and(BikeParking::parkingOperatorLabel('Ecostation21', 'ecostation'))->toBe('エコステーション21')
        // other は生の会社名を10文字＋… に短縮
        ->and(BikeParking::parkingOperatorLabel('岡山市北区役所維持管理課 自転車・駐車場係', 'other'))->toBe('岡山市北区役所維持管…')
        ->and(BikeParking::parkingOperatorLabel('仙台市', null))->toBeNull(); // 緑は表示名なし
});

// ─────────── マップAPI ペイロード ───────────

function brandParking(string $name, ?string $mgmt): BikeParking
{
    return BikeParking::create([
        'name' => $name, 'address' => 'addr-'.uniqid(),
        'latitude' => 35.68, 'longitude' => 139.76,
        'prefecture' => '東京都', 'parking_type' => 'bike_only',
        'is_active' => true, 'management_company' => $mgmt,
    ]);
}

it('tags each parking with brand + operator and hides the raw management_company', function () {
    brandParking('アキッパ駐車場A', 'akippa株式会社');
    brandParking('エコステ駐輪B', 'エコステーション21');
    brandParking('市役所前駐輪C', '岡山市北区役所維持管理課 自転車・駐車場係');
    brandParking('市営駐輪D', '仙台市');

    $rows = collect($this->getJson('/parking/api/search?ne_lat=36&ne_lng=140&sw_lat=35&sw_lng=139')->assertOk()->json());

    $ak = $rows->firstWhere('name', 'アキッパ駐車場A');
    $eco = $rows->firstWhere('name', 'エコステ駐輪B');
    $other = $rows->firstWhere('name', '市役所前駐輪C');
    $green = $rows->firstWhere('name', '市営駐輪D');

    expect($ak['brand'])->toBe('akippa')
        ->and($ak['operator'])->toBe('akippa')
        ->and($ak)->not->toHaveKey('management_company') // 生の会社名はクライアントに送らない
        ->and($eco['brand'])->toBe('ecostation')
        ->and($eco['operator'])->toBe('エコステーション21')
        ->and($other['brand'])->toBe('other')                        // 市役所系＝共通バッジ（緑じゃない）
        ->and($other['operator'])->toBe('岡山市北区役所維持管…')
        ->and($green['brand'])->toBeNull()                           // 市名だけ＝緑🅿️
        ->and($green['operator'])->toBeNull();
});
