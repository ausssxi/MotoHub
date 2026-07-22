<?php

declare(strict_types=1);

use App\Support\AkippaLink;

it('generates a per-parking A8 deeplink when A8MAT is set and source_url is on akippa.com', function () {
    config(['parking.affiliate.akippa.a8mat' => 'ABC123', 'parking.affiliate.akippa.url' => 'https://generic.example/akippa']);

    $src = 'https://www.akippa.com/parking/hash1?utm_source=jmpsa';
    $cta = AkippaLink::ctaFor('akippa株式会社', $src);

    expect($cta['deeplink'])->toBeTrue()
        ->and($cta['url'])->toBe('https://px.a8.net/svt/ejp?a8mat=ABC123&a8ejpredirect='.rawurlencode($src));
});

it('falls back to the generic link for an akippa parking when A8MAT is unset', function () {
    config(['parking.affiliate.akippa.a8mat' => '', 'parking.affiliate.akippa.url' => 'https://generic.example/akippa']);

    $cta = AkippaLink::ctaFor('akippa株式会社', 'https://www.akippa.com/parking/x');

    expect($cta['deeplink'])->toBeFalse()
        ->and($cta['url'])->toBe('https://generic.example/akippa');
});

it('does NOT deeplink when the redirect target is not on https://www.akippa.com/ (strict domain check)', function () {
    config(['parking.affiliate.akippa.a8mat' => 'ABC123', 'parking.affiliate.akippa.url' => 'https://generic.example/akippa']);

    // http:// / 別ドメイン / スプーフ / null は全て非ディープリンク（汎用へフォールバック）
    expect(AkippaLink::ctaFor('akippa株式会社', 'http://www.akippa.com/parking/x')['deeplink'])->toBeFalse()
        ->and(AkippaLink::ctaFor('akippa株式会社', 'https://evil.example/www.akippa.com/')['deeplink'])->toBeFalse()
        ->and(AkippaLink::ctaFor('akippa株式会社', null)['deeplink'])->toBeFalse();
});

it('returns null (no CTA) for non-akippa parkings and when no link is configured', function () {
    config(['parking.affiliate.akippa.a8mat' => 'ABC123', 'parking.affiliate.akippa.url' => 'https://generic.example/akippa']);
    expect(AkippaLink::ctaFor('パラカ株式会社', 'https://www.akippa.com/parking/x'))->toBeNull()
        ->and(AkippaLink::ctaFor(null, null))->toBeNull();

    // akippa物件でも url も a8mat も無ければ非表示（偽リンクを置かない）
    config(['parking.affiliate.akippa.a8mat' => '', 'parking.affiliate.akippa.url' => '']);
    expect(AkippaLink::ctaFor('akippa株式会社', 'https://www.akippa.com/parking/x'))->toBeNull();
});
