{{--
    加瀬倉庫の区画種別アイコン（inline SVG）。

    受け取り: $code（type_code）。
    - 画像ファイル・アイコンフォントは使わない（inline SVG のみ）。
    - 色は currentColor で親の文字色に追従。サイズは 20px。
    - 未知の type_code は汎用アイコン（収納ボックス）で返す（落とさない）。
--}}
@php($code = $code ?? '')
@switch($code)
    @case('cntn')
    @case('bike')
        {{-- コンテナ（箱＋扉）。bike も同形で可。 --}}
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="shrink-0 text-gray-500" aria-hidden="true">
            <rect x="3" y="5" width="18" height="14" rx="1"/>
            <line x1="12" y1="5" x2="12" y2="19"/>
            <line x1="9" y1="12" x2="10" y2="12"/>
            <line x1="14" y1="12" x2="15" y2="12"/>
        </svg>
        @break

    @case('bike-in')
        {{-- 屋根のある区画（屋内）。 --}}
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="shrink-0 text-gray-500" aria-hidden="true">
            <path d="M3 11 L12 4 L21 11"/>
            <rect x="6" y="11" width="12" height="8"/>
        </svg>
        @break

    @case('bike-out')
        {{-- 白線で区切られた区画（屋外）。 --}}
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="shrink-0 text-gray-500" aria-hidden="true">
            <rect x="3" y="5" width="18" height="14" rx="1"/>
            <line x1="9" y1="5" x2="9" y2="19"/>
            <line x1="15" y1="5" x2="15" y2="19"/>
        </svg>
        @break

    @default
        {{-- 汎用（トランクルーム・未知コード）：収納ボックス。 --}}
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="shrink-0 text-gray-500" aria-hidden="true">
            <rect x="3" y="8" width="18" height="11" rx="1"/>
            <path d="M3 8 V6 a1 1 0 0 1 1 -1 h16 a1 1 0 0 1 1 1 v2"/>
            <line x1="10" y1="12" x2="14" y2="12"/>
        </svg>
@endswitch
