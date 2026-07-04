<?php

use App\Support\ShopNameNormalizer;

it('unifies full-width and half-width alphanumerics', function () {
    expect(ShopNameNormalizer::normalize('ＹＳＰ'))->toBe(ShopNameNormalizer::normalize('YSP'))
        ->and(ShopNameNormalizer::normalize('ＹＳＰ'))->toBe('ysp');
});

it('lowercases ascii', function () {
    expect(ShopNameNormalizer::normalize('YSP'))->toBe('ysp')
        ->and(ShopNameNormalizer::normalize('ysp'))->toBe('ysp');
});

it('unifies hiragana and katakana', function () {
    expect(ShopNameNormalizer::normalize('もとぱどっく'))
        ->toBe(ShopNameNormalizer::normalize('モトパドック'));
});

it('unifies half-width katakana with combining dakuten to full-width', function () {
    // ﾊﾞｲｸ (half-width + combining) === バイク (full-width)
    expect(ShopNameNormalizer::normalize('ﾊﾞｲｸ'))->toBe(ShopNameNormalizer::normalize('バイク'));
});

it('removes half-width and full-width spaces', function () {
    expect(ShopNameNormalizer::normalize('モト パドック'))->toBe(ShopNameNormalizer::normalize('モトパドック'))
        ->and(ShopNameNormalizer::normalize('モト　パドック'))->toBe(ShopNameNormalizer::normalize('モトパドック'));
});

it('strips corporate forms including full-width, half-width, and ligatures', function () {
    $base = ShopNameNormalizer::normalize('テスト整備');
    foreach (['株式会社テスト整備', 'テスト整備株式会社', '（株）テスト整備', '(株)テスト整備', '㈱テスト整備',
        '有限会社テスト整備', '（有）テスト整備', '(有)テスト整備', '㈲テスト整備'] as $variant) {
        expect(ShopNameNormalizer::normalize($variant))->toBe($base);
    }
});

it('returns empty string for whitespace-only input', function () {
    expect(ShopNameNormalizer::normalize('　  '))->toBe('');
});
