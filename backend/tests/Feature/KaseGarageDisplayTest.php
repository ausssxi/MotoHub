<?php

declare(strict_types=1);

use App\Models\RentalGarage;

/**
 * 加瀬倉庫の物件分類（バイクヤード/レンタルボックス）と、バイク不可サイズの表示置換の回帰テスト。
 * ※純粋なモデルロジックのため DB 永続化は不要（in-memory）。
 */
function makeGarage(string $operator, string $name, ?string $sizeText): RentalGarage
{
    $g = new RentalGarage();
    $g->operator = $operator;
    $g->name = $name;
    $g->size_text = $sizeText; // ミューテタで正規化される

    return $g;
}

it('加瀬レンタルボックスで下限がバイク不可なら 1.6畳以上〜上限 に置換する', function () {
    $g = makeGarage('加瀬倉庫', 'レンタルボックス江戸川瑞江２', '0.7畳～8畳');
    expect($g->isKaseRentalBox())->toBeTrue();
    expect($g->isKaseBikeYard())->toBeFalse();
    expect($g->kaseLowerBelowBikeMin())->toBeTrue();
    expect($g->displaySizeText())->toBe('1.6畳以上〜8畳');
});

it('下限が1.6畳以上のレンタルボックスは size_text をそのまま返す', function () {
    $g = makeGarage('加瀬倉庫', 'レンタルボックス大田区中央２', '2.1畳～8畳');
    expect($g->kaseLowerBelowBikeMin())->toBeFalse();
    expect($g->displaySizeText())->toBe('2.1畳～8畳');
});

it('バイクヤードはバイク専用扱いで1.6畳ルールを適用しない（1.5畳でも非マスク）', function () {
    $g = makeGarage('加瀬倉庫', 'バイクヤード北区赤羽', '1.5畳');
    expect($g->isKaseBikeYard())->toBeTrue();
    expect($g->isKaseRentalBox())->toBeFalse();
    expect($g->kaseLowerBelowBikeMin())->toBeFalse();
    expect($g->displaySizeText())->toBe('1.5畳');
});

it('他社(イナバ)には一切適用しない', function () {
    $g = makeGarage('イナバボックス', 'イナバボックス蓮田黒浜店', '0.8畳～14.0畳');
    expect($g->isKaseBikeYard())->toBeFalse();
    expect($g->isKaseRentalBox())->toBeFalse();
    expect($g->kaseLowerBelowBikeMin())->toBeFalse();
    expect($g->displaySizeText())->toBe('0.8畳～14.0畳');
});

it('上限の桁は 8.0→8 / 10.1→10.1 に整形される', function () {
    expect(makeGarage('加瀬倉庫', 'レンタルボックスA', '1畳～8畳')->displaySizeText())->toBe('1.6畳以上〜8畳');
    expect(makeGarage('加瀬倉庫', 'レンタルボックスB', '0.7畳～10.1畳')->displaySizeText())->toBe('1.6畳以上〜10.1畳');
});
