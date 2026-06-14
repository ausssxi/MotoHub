<?php

declare(strict_types=1);

use App\Models\BikeModel;
use App\Models\FuelLog;
use App\Models\Manufacturer;
use App\Models\MyBike;
use App\Models\User;
use Illuminate\Support\Facades\DB;

function adoptModel(): BikeModel
{
    $mfr = Manufacturer::where('slug', 'honda')->first() ?? Manufacturer::forceCreate(['name' => 'Honda', 'slug' => 'honda']);

    return BikeModel::where('slug', 'pcx')->first() ?? BikeModel::create(['manufacturer_id' => $mfr->id, 'name' => 'PCX', 'slug' => 'pcx']);
}

it('empty garage shows the fuel-hook onboarding (and not when bikes exist)', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/garage')
        ->assertOk()
        ->assertSee('給油を記録するだけ。平均燃費が見える。');

    MyBike::create(['user_id' => $user->id, 'bike_model_id' => adoptModel()->id, 'name' => 'マイPCX', 'current_odometer' => 100]);
    $this->actingAs($user)->get('/garage')
        ->assertOk()
        ->assertDontSee('給油を記録するだけ。平均燃費が見える。');
});

it('garage ?bike_model_id= preselects the model and opens the register modal', function () {
    $model = adoptModel();
    $this->actingAs(User::factory()->create())
        ->get('/garage?bike_model_id='.$model->id)
        ->assertOk()
        ->assertSee('hiddenInput.value = '.$model->id, false) // prefill: hidden id
        ->assertSee('Honda PCX', false)                       // prefill: 表示名（メーカー＋車種）
        ->assertSee('modal.showModal()', false);              // 自動オープン
});

it('a guest hitting the prefill garage URL is redirected to login keeping bike_model_id (intended)', function () {
    $model = adoptModel();
    $this->get('/garage?bike_model_id='.$model->id)
        ->assertRedirect(route('login'));
    expect(session('url.intended'))->toContain('bike_model_id='.$model->id);
});

it('garage bike with zero logs shows the first-run fuel prompt', function () {
    $user = User::factory()->create();
    $bike = MyBike::create(['user_id' => $user->id, 'bike_model_id' => adoptModel()->id, 'name' => 'マイPCX', 'current_odometer' => 100]);

    $this->actingAs($user)->get("/garage/{$bike->id}")
        ->assertOk()
        ->assertSee('最初の給油を記録してみよう');
});

it('garage bike with one fuel log shows the reward-preview copy (not yet computable)', function () {
    $user = User::factory()->create();
    $bike = MyBike::create(['user_id' => $user->id, 'bike_model_id' => adoptModel()->id, 'name' => 'マイPCX', 'current_odometer' => 6000]);
    FuelLog::create(['my_bike_id' => $bike->id, 'filled_at' => now(), 'odometer' => 6000, 'quantity' => 5, 'cost' => 900, 'efficiency' => null]);

    $this->actingAs($user)->get("/garage/{$bike->id}")
        ->assertOk()
        ->assertSee('もう1回')                         // 報酬予告
        ->assertDontSee('最初の給油を記録してみよう');  // 0件バナーは出ない
});

it('listing review success block contains the garage adoption CTA with model prefill', function () {
    $mfr = Manufacturer::where('slug', 'honda')->first() ?? Manufacturer::forceCreate(['name' => 'Honda', 'slug' => 'honda']);
    $model = BikeModel::create(['manufacturer_id' => $mfr->id, 'name' => 'PCX', 'slug' => 'pcx', 'displacement' => 125]);
    $siteId = DB::table('sites')->insertGetId(['name' => 'TestSite', 'created_at' => now(), 'updated_at' => now()]);
    $shopId = DB::table('shops')->insertGetId(['name' => '店', 'address' => '東京都1-2-3', 'prefecture' => '東京都', 'created_at' => now(), 'updated_at' => now()]);
    $listingId = DB::table('listings')->insertGetId([
        'site_id' => $siteId, 'shop_id' => $shopId, 'bike_model_id' => $model->id, 'manufacturer_id' => $mfr->id,
        'total_price' => 500000, 'is_sold_out' => false, 'condition' => '中古', 'source_url' => 'https://e.test/x',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->get("/bikes/{$listingId}")
        ->assertOk()
        ->assertSee('href="'.route('mybikes.index', ['bike_model_id' => $model->id]).'"', false);
});
