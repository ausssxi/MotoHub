<?php

declare(strict_types=1);

use App\Models\BikeParking;

/**
 * 掲載削除依頼で非公開にする運用（parking:delist）の回帰防止。
 *  - 非公開にすると詳細が 404 になり、delisted_at / delisted_reason が記録される。
 *  - --dry-run は書き込まない。
 *  - 検索モードは読み取り専用で一致行を見つける。
 *  - --relist で公開に戻せる。
 */
function makeParking(string $name, string $address): BikeParking
{
    return BikeParking::create([
        'name' => $name,
        'address' => $address,
        'latitude' => 36.0641,
        'longitude' => 136.2196,
        'prefecture' => '福井県',
        'city' => '福井市',
        'parking_type' => 'bike_only',
        'is_active' => true,
    ]);
}

it('delists a parking by id and records reason, making the page 404', function () {
    $parking = makeParking('福井駅南暫定自転車駐車場', '福井県福井市中央1');

    $this->get("/parking/{$parking->id}")->assertOk();

    $this->artisan('parking:delist', [
        '--id' => [(string) $parking->id],
        '--reason' => '福井市自転車利用推進課の依頼（原付一種のみの駐輪場）',
    ])->assertSuccessful();

    $parking->refresh();
    expect($parking->is_active)->toBeFalse();
    expect($parking->delisted_at)->not->toBeNull();
    expect($parking->delisted_reason)->toContain('福井市');
    expect($parking->isDelisted())->toBeTrue();

    $this->get("/parking/{$parking->id}")->assertNotFound();
});

it('does not write on dry-run', function () {
    $parking = makeParking('JR福井駅自転車駐車場', '福井県福井市中央2');

    $this->artisan('parking:delist', [
        '--id' => [(string) $parking->id],
        '--reason' => 'test',
        '--dry-run' => true,
    ])->assertSuccessful();

    $parking->refresh();
    expect($parking->is_active)->toBeTrue();
    expect($parking->delisted_at)->toBeNull();
});

it('requires a reason when delisting', function () {
    $parking = makeParking('テスト駐輪場', '福井県福井市中央3');

    $this->artisan('parking:delist', [
        '--id' => [(string) $parking->id],
    ])->assertFailed();

    $parking->refresh();
    expect($parking->is_active)->toBeTrue();
});

it('finds parkings by name and address in search mode without writing', function () {
    $a = makeParking('福井駅南暫定自転車駐車場', '福井県福井市中央1');
    $b = makeParking('JR福井駅自転車駐車場', '福井県福井市手寄1');
    makeParking('金沢駅自転車駐車場', '石川県金沢市木ノ新保町1');

    $this->artisan('parking:delist', ['--search' => ['福井駅']])
        ->assertSuccessful();

    // 検索は読み取り専用: 何も非公開にしていない
    expect($a->fresh()->is_active)->toBeTrue();
    expect($b->fresh()->is_active)->toBeTrue();
});

it('keeps a delisted parking hidden even when a save tries to reactivate it', function () {
    $parking = makeParking('福井駅南暫定自転車駐車場', '福井県福井市中央1');
    $this->artisan('parking:delist', [
        '--id' => [(string) $parking->id],
        '--reason' => '依頼',
    ])->assertSuccessful();

    // 取り込み等が is_active=true を渡しても、delisted_at がある限り公開に戻らない。
    $parking->refresh();
    $parking->update(['is_active' => true, 'capacity' => 99]);

    $parking->refresh();
    expect($parking->is_active)->toBeFalse();
    expect($parking->capacity)->toBe(99); // 他フィールドの更新（enrich）は通る
    expect($parking->delisted_at)->not->toBeNull();
});

it('relists a delisted parking and clears the record', function () {
    $parking = makeParking('福井駅南暫定自転車駐車場', '福井県福井市中央1');
    $this->artisan('parking:delist', [
        '--id' => [(string) $parking->id],
        '--reason' => '依頼',
    ])->assertSuccessful();

    expect($parking->fresh()->is_active)->toBeFalse();

    $this->artisan('parking:delist', [
        '--id' => [(string) $parking->id],
        '--relist' => true,
    ])->assertSuccessful();

    $parking->refresh();
    expect($parking->is_active)->toBeTrue();
    expect($parking->delisted_at)->toBeNull();
    expect($parking->delisted_reason)->toBeNull();
});
