<?php

declare(strict_types=1);

use App\Http\Controllers\Poi\PoiAreaController;
use App\Models\Poi;
use App\Models\RentalGarage;
use App\Support\AddressFormatter;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * 周辺施設ラベルのフォールバック（name→brand正規化→種別名（町名）→null）と、
 * ラベルの作れない施設が周辺リストから落ちること、詳細ページ間の内部リンク繋ぎ替えを固定する。
 */

uses(RefreshDatabase::class);

/** @param array<string,mixed> $attrs */
function flPoi(array $attrs): Poi
{
    return (new Poi)->forceFill($attrs);
}

function flFacilityLabel(Poi $poi): ?string
{
    $c = new PoiAreaController;
    $m = new ReflectionMethod($c, 'facilityLabel');
    $m->setAccessible(true);

    return $m->invoke($c, $poi);
}

// --- facilityLabel() 単体（周辺リスト用ラベル） ---

it('facilityLabel: name があれば name をそのまま', function () {
    expect(flFacilityLabel(flPoi(['type' => 'gas_station', 'name' => 'エネオス安波給油所'])))
        ->toBe('エネオス安波給油所');
});

it('facilityLabel: name空・brand → 正規化ブランド名', function () {
    expect(flFacilityLabel(flPoi(['type' => 'gas_station', 'brand' => 'エネオス'])))->toBe('ENEOS')
        ->and(flFacilityLabel(flPoi(['type' => 'convenience_store', 'brand' => '7-ELEVEN'])))->toBe('セブン-イレブン');
});

it('facilityLabel: name空・brand空・address → 種別名（町名）※id=7419 実データ', function () {
    // id=7419: name=NULL brand=NULL address='東京都江東区新砂一丁目'
    $poi = flPoi(['type' => 'gas_station', 'prefecture' => '東京都', 'city' => '江東区', 'address' => '東京都江東区新砂一丁目']);
    expect(flFacilityLabel($poi))->toBe('ガソリンスタンド（新砂一丁目）');
});

it('facilityLabel: name/brand/address すべて空 → null', function () {
    expect(flFacilityLabel(flPoi(['type' => 'gas_station'])))->toBeNull();
});

it('facilityLabel: 住所が番地だけ（町名が取れない）→ null', function () {
    $poi = flPoi(['type' => 'gas_station', 'prefecture' => '新潟県', 'city' => '刈羽郡刈羽村', 'address' => '刈羽村962-1']);
    expect(flFacilityLabel($poi))->toBeNull();
});

// --- getDisplayNameAttribute()（モデルアクセサ: レンタルガレージ/道の駅の周辺リストで使用） ---

it('display_name: name→brand→種別名（町名）→種別名 の順', function () {
    expect(flPoi(['type' => 'car_wash', 'name' => 'コイン洗車 A'])->display_name)->toBe('コイン洗車 A')
        ->and(flPoi(['type' => 'car_wash', 'brand' => 'ENEOS'])->display_name)->toBe('ENEOS') // 生 brand（アクセサは正規化しない）
        ->and(flPoi(['type' => 'car_wash', 'prefecture' => '東京都', 'city' => '江東区', 'address' => '東京都江東区新砂一丁目'])->display_name)->toBe('洗車場（新砂一丁目）')
        ->and(flPoi(['type' => 'car_wash', 'prefecture' => '新潟県', 'city' => '刈羽郡刈羽村', 'address' => '刈羽村962-1'])->display_name)->toBe('洗車場')
        ->and(flPoi(['type' => 'car_wash'])->display_name)->toBe('洗車場');
});

// --- AddressFormatter::townPart() 共有ロジック ---

it('AddressFormatter::townPart: 都道府県・市区町村を除いて町名を返す', function () {
    expect(AddressFormatter::townPart('東京都', '江東区', '東京都江東区新砂一丁目'))->toBe('新砂一丁目')
        ->and(AddressFormatter::townPart('新潟県', '刈羽郡刈羽村', '刈羽村962-1'))->toBe('')
        ->and(AddressFormatter::townPart(null, null, null))->toBe('');
});

// --- 詳細ページ間リンク（修正3） ---

it('レンタルガレージ詳細の「近くの洗車場」は /senshajo/{id} へリンクし町名ラベルを出す', function () {
    $garage = RentalGarage::forceCreate([
        'name' => 'テストガレージ',
        'garage_type' => 'indoor',
        'prefecture' => '東京都',
        'city' => '江東区',
        'address' => '東京都江東区新砂1-1',
        'latitude' => 35.0000,
        'longitude' => 139.0000,
        'is_active' => true,
        'source' => 'official',
        'source_url' => 'https://example.test/g/1',
    ]);
    // 約111m の車washを1件（name/brand なし → 町名ラベルになる）。
    $wash = Poi::forceCreate([
        'osm_id' => 900001, 'type' => 'car_wash', 'name' => null, 'brand' => null,
        'latitude' => 35.0010, 'longitude' => 139.0000,
        'prefecture' => '東京都', 'city' => '江東区', 'address' => '東京都江東区新砂一丁目', 'municipality_code' => '13108',
    ]);

    $this->get(route('rental-garage.show', $garage->id))
        ->assertOk()
        ->assertSee(route('senshajo.short', $wash->id), false) // 地図ではなく詳細ページへ
        ->assertSee('洗車場（新砂一丁目）');                     // 種別名だけでなく町名つき
});

// --- ルート宣言順の回帰（過去2回壊した） ---

it('都道府県ページが 200（/senshajo/{id} との宣言順が壊れていない）', function () {
    Poi::forceCreate(['osm_id' => 910001, 'type' => 'car_wash', 'name' => '洗車A', 'latitude' => 35.6, 'longitude' => 140.1, 'prefecture' => '千葉県', 'city' => '千葉市中央区', 'municipality_code' => '12101']);
    Poi::forceCreate(['osm_id' => 910002, 'type' => 'gas_station', 'name' => 'GS-A', 'latitude' => 35.4, 'longitude' => 139.6, 'prefecture' => '神奈川県', 'city' => '横浜市西区', 'municipality_code' => '14103']);
    Poi::forceCreate(['osm_id' => 910003, 'type' => 'convenience_store', 'name' => 'CVS-A', 'latitude' => 35.7, 'longitude' => 139.7, 'prefecture' => '東京都', 'city' => '新宿区', 'municipality_code' => '13104']);

    $this->get('/senshajo/'.rawurlencode('千葉県'))->assertOk();
    $this->get('/gs/'.rawurlencode('神奈川県'))->assertOk();
    $this->get('/konbini/'.rawurlencode('東京都'))->assertOk();
});
