<?php

declare(strict_types=1);

use App\Models\RentalGarage;

/*
 * 加瀬倉庫の物件分類（バイクヤード/レンタルボックス）と、区画サイズの表示に関する回帰テスト。
 *
 * ★ 区画サイズは施設の実サイズを書き換えず size_text をそのまま出す（誤認防止）。
 *   以前は下限がバイク不可のとき「1.6畳以上〜…」に置換していたが、公式より大きく見せて
 *   しまうため廃止した。バイク可条件は別の注記（下段・1.6畳以上）で補う。
 * ★ 下限がバイク不可(1.6畳未満)かの判定 kaseLowerBelowBikeMin() は「料金が全区画の帯である」
 *   断り書きの出し分けに今も使う（サイズの書き換えには使わない）。
 *
 * ※純粋なモデルロジックのため DB 永続化は不要（in-memory）。
 */
function makeGarage(string $operator, string $name, ?string $sizeText): RentalGarage
{
    $g = new RentalGarage;
    $g->operator = $operator;
    $g->name = $name;
    $g->size_text = $sizeText; // ミューテタで正規化される

    return $g;
}

it('加瀬レンタルボックスでも size_text をそのまま出す（1.6畳以上に書き換えない）', function () {
    $g = makeGarage('加瀬倉庫', 'レンタルボックス江戸川瑞江２', '1.4畳～8畳');
    expect($g->isKaseRentalBox())->toBeTrue();
    expect($g->isKaseBikeYard())->toBeFalse();
    // 下限はバイク不可（料金帯の断り書きは出す）だが、サイズ表示は書き換えない。
    expect($g->kaseLowerBelowBikeMin())->toBeTrue();
    expect($g->size_text)->toBe('1.4畳～8畳');
    expect($g->size_text)->not->toContain('1.6畳以上');
});

it('下限が1.6畳以上のレンタルボックスも size_text をそのまま返す', function () {
    $g = makeGarage('加瀬倉庫', 'レンタルボックス大田区中央２', '2.1畳～8畳');
    expect($g->kaseLowerBelowBikeMin())->toBeFalse();
    expect($g->size_text)->toBe('2.1畳～8畳');
});

it('バイクヤードはバイク専用扱いで1.6畳ルールの料金マスク対象にしない', function () {
    $g = makeGarage('加瀬倉庫', 'バイクヤード北区赤羽', '1.5畳');
    expect($g->isKaseBikeYard())->toBeTrue();
    expect($g->isKaseRentalBox())->toBeFalse();
    expect($g->kaseLowerBelowBikeMin())->toBeFalse();
    expect($g->size_text)->toBe('1.5畳');
});

it('他社(イナバ)には一切適用しない', function () {
    $g = makeGarage('イナバボックス', 'イナバボックス蓮田黒浜店', '0.8畳～14.0畳');
    expect($g->isKaseBikeYard())->toBeFalse();
    expect($g->isKaseRentalBox())->toBeFalse();
    expect($g->kaseLowerBelowBikeMin())->toBeFalse();
    expect($g->size_text)->toBe('0.8畳～14.0畳');
});
