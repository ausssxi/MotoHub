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

it('cleans display text: drops the ▼ and the orphan 」 left after url removal', function () {
    $out = AkippaLink::cleanDisplayText(AKIPPA_NOTES);

    expect($out)
        ->not->toContain('https://www.akippa.com') // URLは消える
        ->not->toContain('▼')                       // 誘導三角は消える
        ->not->toContain('」」')                     // 余り括弧の二重化なし
        ->toContain('ご予約ください')                // 本文は残る
        ->and(mb_substr($out, -1))->not->toBe('」'); // 末尾の余り閉じ括弧は落ちる
});

it('cleans display text: rewrites dangling "リンク先をご確認ください" into an akippa page notice', function () {
    $notes = 'バイク駐車可。詳細はリンク先をご確認ください。 https://www.akippa.com/parking/abc123';
    $out = AkippaLink::cleanDisplayText($notes);

    expect($out)
        ->toContain('詳細は akippa の予約ページでご確認ください。')
        ->not->toContain('リンク先をご確認')
        ->not->toContain('https://www.akippa.com');
});

it('cleans display text: rewrites "備考のURLのリンク先" variant too', function () {
    $notes = '予約制。詳細は備考のURLのリンク先をご確認ください https://www.akippa.com/parking/xyz';
    expect(AkippaLink::cleanDisplayText($notes))
        ->toContain('詳細は akippa の予約ページでご確認ください。')
        ->not->toContain('備考のURL');
});

it('cleans display text: leaves text untouched when there is no akippa url', function () {
    // akippa URLが無い＝撤去していない → リンク誘導文も▼も触らない（別サイトのリンクを指す文言かもしれない）。
    $notes = '▼詳細はリンク先をご確認ください';
    expect(AkippaLink::cleanDisplayText($notes))->toBe('▼詳細はリンク先をご確認ください');
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
