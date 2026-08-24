<?php

declare(strict_types=1);

use App\Support\AkippaLink;

// id=8306 相当：予約URLは notes に文章として埋め込み（末尾 」・utm付き）。
const AKIPPA_NOTES = '▼ご利用の際は駐車場予約サービス「akippa」のサイトよりご予約ください。https://www.akippa.com/parking/f5eb4aa1?utm_source=jmpsa&utm_medium=referral&utm_campaign=jmpsa」';
const AKIPPA_URL = 'https://www.akippa.com/parking/f5eb4aa1?utm_source=jmpsa&utm_medium=referral&utm_campaign=jmpsa';

it('extracts the akippa url from notes (utm preserved, trailing 」 excluded)', function () {
    expect(AkippaLink::extractAkippaUrl(AKIPPA_NOTES))->toBe(AKIPPA_URL);
});

it('falls back to description when notes has no akippa url, else null', function () {
    expect(AkippaLink::extractAkippaUrl('ただの備考', 'ご予約は https://www.akippa.com/parking/xyz?a=1 から'))
        ->toBe('https://www.akippa.com/parking/xyz?a=1')
        ->and(AkippaLink::extractAkippaUrl('備考', 'akippaリンク無し'))->toBeNull()
        ->and(AkippaLink::extractAkippaUrl(null, null))->toBeNull();
});

it('strips the akippa url from text but keeps the surrounding wording', function () {
    $stripped = AkippaLink::stripAkippaUrl(AKIPPA_NOTES);
    expect($stripped)->not->toContain('https://www.akippa.com')  // 生URL消滅
        ->toContain('ご予約ください');                           // 文言は残る
});

it('generates a per-parking A8 deeplink (affiliate) from notes when A8MAT is set', function () {
    config(['parking.affiliate.akippa.a8mat' => 'ABC123']);

    $cta = AkippaLink::ctaFor('akippa株式会社', AKIPPA_NOTES, null);

    expect($cta['deeplink'])->toBeTrue()
        ->and($cta['affiliate'])->toBeTrue()
        ->and($cta['url'])->toBe('https://px.a8.net/svt/ejp?a8mat=ABC123&a8ejpredirect='.rawurlencode(AKIPPA_URL));
});

it('returns a raw non-affiliate direct link to the parking page when A8MAT is unset (akippa deaffiliated)', function () {
    config(['parking.affiliate.akippa.a8mat' => '']);

    $cta = AkippaLink::ctaFor('akippa株式会社', AKIPPA_NOTES, null);

    // 素の個別ページURL（A8トラッキング無し）。affiliate=false。
    expect($cta['affiliate'])->toBeFalse()
        ->and($cta['url'])->toBe(AKIPPA_URL)
        ->and($cta['url'])->not->toContain('px.a8.net');
});

it('returns null for an akippa parking with no url in notes/description (no top-page fallback)', function () {
    // A8MAT 設定有無にかかわらず、個別URLが無ければ null（akippaトップへは送客しない）。
    config(['parking.affiliate.akippa.a8mat' => 'ABC123']);
    expect(AkippaLink::ctaFor('akippa株式会社', 'URL無しの備考', 'これも無し'))->toBeNull();

    config(['parking.affiliate.akippa.a8mat' => '']);
    expect(AkippaLink::ctaFor('akippa株式会社', 'URL無しの備考', 'これも無し'))->toBeNull();
});

it('returns null for non-akippa parkings even with an akippa url present', function () {
    config(['parking.affiliate.akippa.a8mat' => 'ABC123']);
    expect(AkippaLink::ctaFor('パラカ株式会社', AKIPPA_NOTES, null))->toBeNull();
});
