@props([
    'station' => null,  // RoadsideStation（詳細ページ用）。渡すと facilityBadges() で解決。
    'badges' => null,   // ラベル配列（ツーリング側の事前計算済み badges 用）。station より優先。
    'limit' => null,    // 先頭から出す最大件数（ツーリング側は 4）。
])

@php
    // 単一定義（RoadsideStation::FACILITY_BADGES）で解決。ここに希少度順や表記を再定義しない。
    $labels = is_array($badges)
        ? $badges
        : ($station ? $station->facilityBadges($limit) : []);
    $gasLabel = \App\Models\RoadsideStation::GAS_BADGE_LABEL;
@endphp

@if(! empty($labels))
<span {{ $attributes->merge(['class' => 'flex flex-wrap gap-1.5']) }}>
    @foreach($labels as $label)
        @if($label === $gasLabel)
        {{-- ガソリンスタンド併設は1段強い色で目立たせる（bg-blue-600 は既存ビルドに存在） --}}
        <span class="inline-block px-2.5 py-1 rounded-md bg-blue-600 text-white text-[11px] font-bold">{{ $label }}</span>
        @else
        <span class="inline-block px-2 py-0.5 rounded-full bg-green-50 text-green-700 text-[11px] font-bold">{{ $label }}</span>
        @endif
    @endforeach
</span>
@endif
