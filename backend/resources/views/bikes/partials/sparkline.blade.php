{{-- 依存ゼロの inline SVG スパークライン（サーバー描画）。
     引数: $points（数値配列・時系列昇順）, 任意 $w,$h,$color,$label。点2未満は非表示。 --}}
@php
    $points = array_values(array_map('intval', $points ?? []));
    $w = $w ?? 240; $h = $h ?? 48; $color = $color ?? '#dc2626';
    $n = count($points);
    $coords = [];
    if ($n >= 2) {
        $min = min($points); $max = max($points); $range = ($max - $min) ?: 1;
        foreach ($points as $i => $v) {
            $x = round($i / ($n - 1) * ($w - 6) + 3, 1);
            $y = round($h - 3 - ($v - $min) / $range * ($h - 6), 1);
            $coords[] = $x.','.$y;
        }
    }
@endphp
@if($n >= 2)
<svg viewBox="0 0 {{ $w }} {{ $h }}" width="{{ $w }}" height="{{ $h }}" role="img" aria-label="{{ $label ?? '推移' }}" class="overflow-visible">
    <polyline points="{{ implode(' ', $coords) }}" fill="none" stroke="{{ $color }}" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" />
    @php [$lx, $ly] = explode(',', end($coords)); @endphp
    <circle cx="{{ $lx }}" cy="{{ $ly }}" r="2.5" fill="{{ $color }}" />
</svg>
@endif
