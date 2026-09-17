<?php

declare(strict_types=1);

use App\Models\RentalGarage;
use App\Models\RentalGarageType;
use Illuminate\Support\Facades\Http;

/*
 * kase:fetch-types コマンド。
 * ★ Http::fake で外部アクセスを遮断して検証する（実際のサイトへは行かない）。
 *  - dry-run で DB が1件も変わらないこと
 *  - 本実行で対象物件自身の type_code だけが入ること（周辺物件は混ざらない）
 *  - 二重実行しても重複しないこと（冪等）
 *  - 保存カラムは type_code のみ（isAvailable / availableCount / 画像は存在しない）
 */

function fakeKasePageHtml(): string
{
    return <<<'HTML'
<script>self.__next_f.push([1,"8:{\"object\":{\"id\":\"101025\",\"name\":\"戸塚シャドー\",\"thumbnail\":{\"id\":\"101025a\",\"url\":\"https://www.kase3535.com/image/101025/101025a.jpg\"},\"types\":[{\"id\":\"cntn\",\"name\":\"レンタルボックス\",\"isAvailable\":true,\"availableCount\":15},{\"id\":\"bike\",\"name\":\"バイクヤード\",\"isAvailable\":true,\"availableCount\":1}]},\"nearbyObjects\":[{\"id\":\"031307\",\"name\":\"近隣\",\"types\":[{\"id\":\"trnk\",\"name\":\"トランクルーム\",\"isAvailable\":true,\"availableCount\":3}]}]}"])</script>
HTML;
}

function makeKaseTypeGarage(): RentalGarage
{
    return RentalGarage::create([
        'name' => '加瀬倉庫 戸塚シャドー',
        'operator' => '加瀬倉庫',
        'garage_type' => 'container',
        'prefecture' => '神奈川県',
        'city' => '横浜市戸塚区',
        'address' => '神奈川県横浜市戸塚区影取町1-1',
        'source_url' => 'https://www.kase3535.com/kanagawa/yokohamashitotsukaku/101025/',
        'is_active' => true,
    ]);
}

it('dry-run では DB を1件も変更しない', function () {
    Http::fake(['www.kase3535.com/*' => Http::response(fakeKasePageHtml(), 200)]);
    makeKaseTypeGarage();

    $this->artisan('kase:fetch-types', ['--dry-run' => true])->assertOk();

    expect(RentalGarageType::count())->toBe(0);
});

it('本実行で対象物件自身の type_code だけを保存する（周辺物件は混ざらない）', function () {
    Http::fake(['www.kase3535.com/*' => Http::response(fakeKasePageHtml(), 200)]);
    $garage = makeKaseTypeGarage();

    $this->artisan('kase:fetch-types')->assertOk();

    $codes = RentalGarageType::where('rental_garage_id', $garage->id)->pluck('type_code')->sort()->values()->all();
    expect($codes)->toBe(['bike', 'cntn']); // trnk（近隣物件）は入らない
});

it('保存カラムは type_code のみ（空き状況・画像のキーは存在しない）', function () {
    Http::fake(['www.kase3535.com/*' => Http::response(fakeKasePageHtml(), 200)]);
    makeKaseTypeGarage();

    $this->artisan('kase:fetch-types')->assertOk();

    $keys = array_keys(RentalGarageType::firstOrFail()->getAttributes());
    sort($keys);
    expect($keys)->toBe(['created_at', 'id', 'rental_garage_id', 'type_code', 'updated_at']);
});

it('二重実行しても重複しない（冪等）', function () {
    Http::fake(['www.kase3535.com/*' => Http::response(fakeKasePageHtml(), 200)]);
    $garage = makeKaseTypeGarage();

    $this->artisan('kase:fetch-types')->assertOk();
    $first = RentalGarageType::where('rental_garage_id', $garage->id)->count();

    $this->artisan('kase:fetch-types')->assertOk();
    $second = RentalGarageType::where('rental_garage_id', $garage->id)->count();

    expect($first)->toBe(2);
    expect($second)->toBe(2);
});
