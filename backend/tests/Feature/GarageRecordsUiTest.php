<?php

declare(strict_types=1);

use App\Models\BikeModel;
use App\Models\MaintenanceLog;
use App\Models\Manufacturer;
use App\Models\MyBike;
use App\Models\User;
use App\Services\MyBike\MyBikeService;

function uiBike(User $user, float $current = 20000): MyBike
{
    $mfr = Manufacturer::where('slug', 'honda')->first() ?? Manufacturer::forceCreate(['name' => 'Honda', 'slug' => 'honda']);
    $model = BikeModel::where('slug', 'pcx')->first() ?? BikeModel::create(['manufacturer_id' => $mfr->id, 'name' => 'PCX', 'slug' => 'pcx']);

    return MyBike::create(['user_id' => $user->id, 'bike_model_id' => $model->id, 'name' => 'マイPCX', 'initial_odometer' => 0, 'current_odometer' => $current]);
}

/** title の整備を1件入れて、その項目の reminder guideline を返す（null=リマインダー無し）。 */
function guidelineFor(string $title): ?int
{
    $user = User::factory()->create();
    $bike = uiBike($user, 20000);
    app(MyBikeService::class)->recordMaintenance($bike, ['maintained_at' => now()->format('Y-m-d'), 'title' => $title, 'odometer' => 5000]);
    $d = app(MyBikeService::class)->buildDashboard(MyBike::with(['maintenanceLogs', 'customRecords', 'fuelLogs'])->findOrFail($bike->id));

    return collect($d['reminders'])->firstWhere('title', $title)['guideline'] ?? null;
}

it('preset names align with the reminder guideline keywords (no mis-fire)', function () {
    // 認識されて interval が付くもの
    expect(guidelineFor('エンジンオイル交換'))->toBe(3000);
    expect(guidelineFor('オイルフィルター交換'))->toBe(6000);
    expect(guidelineFor('タイヤ交換（前）'))->toBe(15000);
    expect(guidelineFor('タイヤ交換（後）'))->toBe(15000);
    expect(guidelineFor('ブレーキパッド交換'))->toBe(10000);

    // ★誤マッチ防止：フォークオイルは「オイル」を含むが 3000 を付けない（安全側＝interval無し）
    expect(guidelineFor('フォークオイル交換'))->toBeNull();

    // 無キーワード＝記録のみ・リマインダー無し（graceful）
    expect(guidelineFor('チェーン給油'))->toBeNull();
    expect(guidelineFor('ブレーキフルード交換'))->toBeNull();
    expect(guidelineFor('車検'))->toBeNull();
});

it('every configured maintenance preset is storable (no validation surprises)', function () {
    $user = User::factory()->create();
    $bike = uiBike($user);

    foreach (config('garage.maintenance_presets') as $preset) {
        if ($preset === 'その他') {
            continue;
        }
        $this->actingAs($user)->post("/garage/{$bike->id}/maintenance", [
            'maintained_at' => now()->format('Y-m-d'), 'title' => $preset, 'cost' => 1000, 'vendor' => 'テスト店',
        ])->assertRedirect();
    }

    expect($bike->maintenanceLogs()->count())->toBe(count(config('garage.maintenance_presets')) - 1);
    // vendor が保存されている（Request整合の確認）
    expect($bike->maintenanceLogs()->whereNotNull('vendor')->count())->toBeGreaterThan(0);
});

it('custom store accepts the エンジン category (config-driven validation)', function () {
    $user = User::factory()->create();
    $bike = uiBike($user);

    $this->actingAs($user)->post("/garage/{$bike->id}/custom", [
        'maintained_at' => now()->format('Y-m-d'), 'part_name' => 'ハイカム', 'category' => 'エンジン', 'is_installed' => 1,
    ])->assertRedirect()->assertSessionHasNoErrors();

    $rec = MaintenanceLog::where('my_bike_id', $bike->id)->custom()->firstOrFail();
    expect($rec->category)->toBe('エンジン')->and($rec->is_installed)->toBeTrue();
});

it('custom store rejects an unknown category', function () {
    $user = User::factory()->create();
    $bike = uiBike($user);

    $this->actingAs($user)->post("/garage/{$bike->id}/custom", [
        'maintained_at' => now()->format('Y-m-d'), 'part_name' => 'x', 'category' => '宇宙',
    ])->assertSessionHasErrors('category');
});

it('the garage page renders presets, the custom form, installed parts, delete + copy controls', function () {
    $user = User::factory()->create();
    $bike = uiBike($user);
    app(MyBikeService::class)->recordMaintenance($bike, ['maintained_at' => now()->format('Y-m-d'), 'title' => 'エンジンオイル交換', 'odometer' => 19000, 'cost' => 3000, 'vendor' => 'ナップス']);
    app(MyBikeService::class)->recordCustom($bike, ['maintained_at' => now()->format('Y-m-d'), 'part_name' => 'ヨシムラ マフラー', 'category' => '排気', 'is_installed' => 1]);
    $rec = $bike->maintenanceLogs()->first();

    $this->actingAs($user)->get("/garage/{$bike->id}")
        ->assertOk()
        ->assertSee('エンジンオイル交換')          // プリセット chip
        ->assertSee('カスタムを記録')              // カスタムフォーム
        ->assertSee('今ついてる装備')              // installed_parts セクション
        ->assertSee('ヨシムラ マフラー')           // 装着中パーツ
        ->assertSee(route('mybikes.records.destroy', [$bike->id, $rec->id]), false) // 削除UI
        ->assertSee('前回と同じで記録', false);     // 前回複製ボタン
});

it('the records x-data attribute survives HTML parsing with real data (no raw JSON breaks the attribute, so Alpine can parse it)', function () {
    // ★回帰防止: x-data 属性内に @json（生の " を含む）を埋めると、最初の " で属性が途切れ、
    //   以降の JS が本文テキストへ漏れて Alpine が全滅する（assertSee では検出不能）。
    //   records が非空＝壊れる条件で、DOMで属性が末尾まで無傷か＋本文へ漏れていないかを検証する。
    $user = User::factory()->create();
    $bike = uiBike($user);
    app(MyBikeService::class)->recordFuel($bike, ['filled_at' => now()->format('Y-m-d'), 'odometer' => 10000, 'quantity' => 5, 'is_full_tank' => 1]);
    app(MyBikeService::class)->recordMaintenance($bike, ['maintained_at' => now()->format('Y-m-d'), 'title' => 'オイル交換', 'odometer' => 10001]);

    $html = $this->actingAs($user)->get("/garage/{$bike->id}")->assertOk()->getContent();

    $doc = new DOMDocument;
    @$doc->loadHTML('<?xml encoding="UTF-8">'.$html);
    $xp = new DOMXPath($doc);

    $node = null;
    foreach ($xp->query('//div[@x-data]') as $n) {
        if (str_contains($n->getAttribute('x-data'), 'records:')) {
            $node = $n;
            break;
        }
    }
    expect($node)->not->toBeNull();

    // 属性が JSON の " で途切れていれば、末尾側の定義（checkOdo/editCustom/submitVoice）は属性に残らない。
    $xd = $node->getAttribute('x-data');
    expect($xd)->toContain('checkOdo(v)')
        ->toContain('editCustom(b)')
        ->toContain('submitVoice');

    // 生の JS が本文テキストへ漏れていない（漏れると画面に素のコードが見える＝本不具合の症状）。
    $body = $xp->query('//body')->item(0);
    expect($body->textContent)->not->toContain('maxBefore')
        ->not->toContain('this.odoWarn');
});

it('each tab toggles via x-show, and the maintenance block is NOT hidden by the .hidden class (Alpine x-show cannot override it)', function () {
    $user = User::factory()->create();
    $bike = uiBike($user);

    $html = $this->actingAs($user)->get("/garage/{$bike->id}")->assertOk()->getContent();

    // 3タブとも x-show で出し分けられている
    expect($html)->toContain("x-show=\"tab === 'fuel'\"")
        ->toContain("x-show=\"tab === 'maintenance'\"")
        ->toContain("x-show=\"tab === 'ledger'\"");

    // ★回帰防止: 整備・カスタムブロックを Tailwind の .hidden で隠してはならない。
    //  x-show は inline の display を切り替えるだけで .hidden(display:none) を上書きできず、
    //  「表示」に切り替えても class が残って永久に隠れる（本バグの根本原因）。
    //  fuel と同様に inline style で隠し、Alpine が表示時に display を外せるようにする。
    expect($html)->not->toContain("x-show=\"tab === 'maintenance'\" class=\"hidden")
        ->toContain("x-show=\"tab === 'maintenance'\" style=\"display: none;\"");
});

it('maintenance store persists vendor (場所)', function () {
    $user = User::factory()->create();
    $bike = uiBike($user);

    $this->actingAs($user)->post("/garage/{$bike->id}/maintenance", [
        'maintained_at' => now()->format('Y-m-d'), 'title' => 'タイヤ交換（後）', 'vendor' => '2りんかん', 'cost' => 18000,
    ])->assertRedirect();

    expect($bike->maintenanceLogs()->first()->vendor)->toBe('2りんかん');
});
