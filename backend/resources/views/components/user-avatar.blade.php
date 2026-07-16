@props([
    'user' => null,   // App\Models\User|null（あれば avatar_url / review_display_name を解決）
    'url' => null,     // 明示URL（$user より優先。プロフィールヘッダ等 $user 非経由の面で使用）
    'name' => null,    // イニシャル用の表示名（未指定なら user->review_display_name）
    'size' => 8,       // 一辺(Tailwind単位): 6 / 8 / 10 / 16 のいずれか
])

@php
    // ★公開面用。本名(name カラム)は絶対に使わない。表示名は review_display_name（=公開ハンドル）のみ。
    $resolvedUrl = $url ?? $user?->avatar_url;
    $resolvedName = $name ?? $user?->review_display_name ?? '名無しライダー';

    // 動的クラス名は build 時のパージに拾われないため、リテラルへ match で確定する。
    $dim = match ((int) $size) {
        16 => 'w-16 h-16',
        10 => 'w-10 h-10',
        6 => 'w-6 h-6',
        default => 'w-8 h-8',
    };
    $iconDim = match ((int) $size) {
        16 => 'w-8 h-8',
        10 => 'w-5 h-5',
        6 => 'w-3 h-3',
        default => 'w-4 h-4',
    };
    $textDim = match ((int) $size) {
        16 => 'text-2xl',
        10 => 'text-base',
        6 => 'text-[10px]',
        default => 'text-sm',
    };

    // イニシャル（先頭1文字）。空なら汎用アイコンにフォールバック。
    $initial = mb_substr(trim($resolvedName), 0, 1);

    // 表示名から決定的に淡い背景色を選ぶ（毎回同じ色＝視認の手掛かり）。
    $palette = ['bg-rose-100 text-rose-600', 'bg-amber-100 text-amber-600', 'bg-emerald-100 text-emerald-600', 'bg-sky-100 text-sky-600', 'bg-violet-100 text-violet-600', 'bg-pink-100 text-pink-600'];
    $tone = $palette[crc32($resolvedName) % count($palette)];
@endphp

<span {{ $attributes->merge(['class' => "$dim rounded-full overflow-hidden inline-flex items-center justify-center shrink-0 select-none"]) }}>
    @if($resolvedUrl)
        <img src="{{ $resolvedUrl }}" alt="{{ $resolvedName }}のアイコン" class="w-full h-full object-cover" loading="lazy" decoding="async">
    @elseif($initial !== '')
        <span class="w-full h-full flex items-center justify-center font-black {{ $textDim }} {{ $tone }}">{{ $initial }}</span>
    @else
        <span class="w-full h-full flex items-center justify-center bg-gray-200 text-gray-400"><i data-lucide="user" class="{{ $iconDim }}"></i></span>
    @endif
</span>
