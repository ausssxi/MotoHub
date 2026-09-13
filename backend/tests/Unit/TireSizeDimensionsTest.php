<?php

declare(strict_types=1);

use App\Support\TireSize;

// ─────────── dimensions(): メトリック ───────────

it('computes metric dimensions for 120/70ZR17', function () {
    $d = TireSize::dimensions('120/70ZR17');

    expect($d)->not->toBeNull();
    expect($d['type'])->toBe('metric');
    expect($d['width_mm'])->toBe(120.0);
    expect($d['aspect'])->toBe(0.7);
    expect($d['rim_inch'])->toBe(17);
    expect($d['outer_mm'])->toBe(599.8);
});

it('parses the metric variants R / -/ B separators', function () {
    expect(TireSize::dimensions('110/70R17')['rim_inch'])->toBe(17);
    expect(TireSize::dimensions('100/90-19')['rim_inch'])->toBe(19);
    expect(TireSize::dimensions('130/80B17')['rim_inch'])->toBe(17);
    // 扁平率も正しく取れる
    expect(TireSize::dimensions('100/90-19')['aspect'])->toBe(0.9);
});

it('accepts 3-digit aspect ratios used by off-road tires', function () {
    $a = TireSize::dimensions('80/100-21');
    expect($a['width_mm'])->toBe(80.0);
    expect($a['aspect'])->toBe(1.0);
    expect($a['rim_inch'])->toBe(21);
    expect(TireSize::dimensionText($a))->toBe('幅80mm・扁平100%・外径約693mm');

    $b = TireSize::dimensions('70/100-19');
    expect($b['rim_inch'])->toBe(19);
    expect(TireSize::dimensionText($b))->toBe('幅70mm・扁平100%・外径約623mm');
});

it('still requires a separator so slash-missing data stays null', function () {
    expect(TireSize::dimensions('100/9019'))->toBeNull();
});

it('accepts the MC (motorcycle) suffix in all its forms', function () {
    foreach (['130/70ZR16MC', '130/70ZR16 MC', '130/70ZR16M/C', '130/70zr16mc'] as $raw) {
        $d = TireSize::dimensions($raw);
        expect($d)->not->toBeNull();
        expect($d['width_mm'])->toBe(130.0);
        expect($d['aspect'])->toBe(0.7);
        expect($d['rim_inch'])->toBe(16);
        expect(TireSize::dimensionText($d))->toBe('幅130mm・扁平70%・外径約588mm');
    }
});

it('parses alpha-numeric (Harley) notations via the conversion table', function () {
    // ハイフン有無どちらも同じ。末尾 B（構造記号）は読み飛ばす。換算値をテキストに添える。
    foreach (['MT90B16', 'MT90-B16'] as $raw) {
        $d = TireSize::dimensions($raw);
        expect($d['type'])->toBe('alpha');
        expect($d['width_mm'])->toBe(130.0);
        expect($d['aspect'])->toBe(0.9);
        expect($d['rim_inch'])->toBe(16);
        expect($d['outer_mm'])->toBe(640.4);
        expect(TireSize::dimensionText($d))->toBe('幅130mm・扁平90%・外径約640mm（≒130/90-16 相当）');
    }

    $mh = TireSize::dimensions('MH90-21');
    expect($mh['width_mm'])->toBe(80.0);
    expect($mh['aspect'])->toBe(0.9);
    expect($mh['rim_inch'])->toBe(21);
    expect($mh['outer_mm'])->toBe(677.4);
});

it('reads code and aspect from the table, not from the trailing number', function () {
    // ★ MV85 は 150/80（末尾85を扁平率にしない）。
    $d = TireSize::dimensions('MV85B16');
    expect($d['width_mm'])->toBe(150.0);
    expect($d['aspect'])->toBe(0.8);
    expect(TireSize::dimensionText($d))->toContain('扁平80%');

    // MP85 は 110/90（コード末尾85でも扁平90）。
    expect(TireSize::dimensions('MP85B16')['aspect'])->toBe(0.9);
});

it('returns null for alpha-numeric codes not in the table', function () {
    expect(TireSize::dimensions('MX90B16'))->toBeNull();
});

it('rejects out-of-range aspect / width / rim (no guessing)', function () {
    expect(TireSize::dimensions('120/200-17'))->toBeNull(); // 扁平200 は範囲外
    expect(TireSize::dimensions('350/70-17'))->toBeNull();  // 断面幅350mm は範囲外
    expect(TireSize::dimensions('120/70-25'))->toBeNull();  // リム25 は範囲外
    expect(TireSize::dimensions('120/70-5'))->toBeNull();   // リム5 は範囲外
});

// ─────────── dimensions(): インチ ───────────

it('computes inch (bias) dimensions for 2.75-21', function () {
    $d = TireSize::dimensions('2.75-21');

    expect($d['type'])->toBe('inch');
    expect($d['width_mm'])->toBe(69.85);
    expect($d['aspect'])->toBe(1.0);
    expect($d['rim_inch'])->toBe(21);
    expect($d['outer_mm'])->toBe(673.1);
});

// ─────────── dimensions(): 解釈不能は null（例外を投げない） ───────────

it('returns null for unparseable or dirty notations without throwing', function () {
    expect(TireSize::dimensions('MX90B16'))->toBeNull();                // 表に無いアルファコード
    expect(TireSize::dimensions('100/9019'))->toBeNull();               // スラッシュ抜け（区切り無し連結）
    expect(TireSize::dimensions('120/70ZR17120/70R17'))->toBeNull();    // 2つ連結
    expect(TireSize::dimensions(''))->toBeNull();
    expect(TireSize::dimensions(null))->toBeNull();
});

// ─────────── svg() ───────────

it('produces an svg string for a valid size', function () {
    $svg = TireSize::svg(TireSize::dimensions('120/70ZR17'));

    expect($svg)->toContain('<svg');
    expect($svg)->toContain('viewBox="0 0 92 92"');
    expect($svg)->toContain('aria-hidden="true"');
    expect($svg)->toContain('stroke-dasharray'); // トレッドは破線円1本
    // 線を並べていない（要素が膨れない）: circle は4本のみ。
    expect(substr_count($svg, '<circle'))->toBe(4);
});

it('does not break when called for a null (undrawable) size', function () {
    expect(TireSize::svg(TireSize::dimensions('MX90B16')))->toBeNull(); // 表に無い＝図なし
    expect(TireSize::svg(null))->toBeNull();
});

// ─────────── dimensionText() ───────────

it('formats metric text with aspect ratio', function () {
    expect(TireSize::dimensionText(TireSize::dimensions('120/70ZR17')))
        ->toBe('幅120mm・扁平70%・外径約600mm');
});

it('formats inch text without aspect ratio', function () {
    expect(TireSize::dimensionText(TireSize::dimensions('2.75-21')))
        ->toBe('幅約70mm・外径約673mm');
});

// ─────────── 共通縮尺（PX_PER_MM） ───────────

it('uses one shared scale so bigger tires render bigger', function () {
    $big = TireSize::dimensions('2.75-21');   // 21インチ・外径 673.1mm
    $small = TireSize::dimensions('90/90-10'); // 10インチ・外径 416mm

    $bigR = $big['outer_mm'] / 2 * TireSize::PX_PER_MM;
    $smallR = $small['outer_mm'] / 2 * TireSize::PX_PER_MM;

    expect($bigR)->toBeGreaterThan($smallR);
});
