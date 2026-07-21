{{-- /theft 用の年次推移チャート（依存ゼロの inline SVG・レスポンシブ）。
     引数: $series = [{year, recognized, cleared}, ...]（年昇順）。2点未満は呼び出し側で出さない前提。
     エリア塗り＋折れ線＋各点マーカー/数値ラベル＋全年の横軸＋横グリッド。トーンは抑えめの赤アクセント。 --}}
@php
    $rows = array_values($series ?? []);
    $n = count($rows);
    // 描画領域（viewBox 座標）。width:100% でレスポンシブ。
    $W = 640; $H = 210;
    $padL = 48; $padR = 16; $padT = 22; $padB = 30;
    $x0 = $padL; $x1 = $W - $padR; $y0 = $padT; $y1 = $H - $padB;

    $vals = array_map(fn ($r) => (int) $r['recognized'], $rows);
    $max = $vals ? max($vals) : 0;
    $niceMax = $max > 0 ? (int) (ceil($max / 1000) * 1000) : 1; // 上端は1000単位で切り上げ
    $mid = (int) round($niceMax / 2);

    $sx = fn ($i) => $n <= 1 ? ($x0 + $x1) / 2 : round($x0 + $i / ($n - 1) * ($x1 - $x0), 1);
    $sy = fn ($v) => round($y1 - ($v / $niceMax) * ($y1 - $y0), 1);

    $pts = [];
    foreach ($rows as $i => $r) {
        $pts[] = ['x' => $sx($i), 'y' => $sy((int) $r['recognized']), 'v' => (int) $r['recognized'], 'year' => (int) $r['year']];
    }
    $poly = implode(' ', array_map(fn ($p) => $p['x'].','.$p['y'], $pts));
    $area = 'M '.$sx(0).','.$y1.' L '.implode(' L ', array_map(fn ($p) => $p['x'].','.$p['y'], $pts)).' L '.$sx($n - 1).','.$y1.' Z';
    $grid = [['v' => $niceMax], ['v' => $mid], ['v' => 0]];
@endphp
<svg viewBox="0 0 {{ $W }} {{ $H }}" width="100%" role="img" aria-label="オートバイ盗（全国）の認知件数の推移" style="height:auto;display:block;">
    <defs>
        <linearGradient id="theftArea" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stop-color="#dc2626" stop-opacity="0.18" />
            <stop offset="100%" stop-color="#dc2626" stop-opacity="0" />
        </linearGradient>
    </defs>

    {{-- 横グリッド＋左目盛り --}}
    @foreach($grid as $g)
    @php $gy = $sy($g['v']); @endphp
    <line x1="{{ $x0 }}" y1="{{ $gy }}" x2="{{ $x1 }}" y2="{{ $gy }}" stroke="#eef0f2" stroke-width="1" />
    <text x="{{ $x0 - 6 }}" y="{{ $gy + 3 }}" text-anchor="end" font-size="9" fill="#9ca3af">{{ number_format($g['v']) }}</text>
    @endforeach

    {{-- エリア塗り＋折れ線 --}}
    <path d="{{ $area }}" fill="url(#theftArea)" />
    <polyline points="{{ $poly }}" fill="none" stroke="#dc2626" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round" />

    {{-- 各点：マーカー＋数値ラベル＋年ラベル --}}
    @foreach($pts as $p)
    <circle cx="{{ $p['x'] }}" cy="{{ $p['y'] }}" r="3.5" fill="#fff" stroke="#dc2626" stroke-width="2" />
    <text x="{{ $p['x'] }}" y="{{ $p['y'] - 8 }}" text-anchor="middle" font-size="10" font-weight="700" fill="#b91c1c">{{ number_format($p['v']) }}</text>
    <text x="{{ $p['x'] }}" y="{{ $y1 + 16 }}" text-anchor="middle" font-size="10" fill="#6b7280">{{ $p['year'] }}</text>
    @endforeach
</svg>
