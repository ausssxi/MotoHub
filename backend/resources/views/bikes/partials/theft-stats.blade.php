{{-- 県別ページ盗難ブロック（面②）。$theftStats は App\Support\TheftStats::forPrefecture() の結果。
     データ未投入/未知県/最新年欠損なら null＝ブロックごと非表示（数字は創作しない）。完全サーバー描画。 --}}
@if(!empty($theftStats))
@php
    $t = $theftStats;
    $src = \App\Support\TheftStats::sourceMeta();
    $aff = config('theft.affiliate');
    $ctaUrl = $aff['url'] ?? '';
    $rate = $t['clearance_rate'] !== null ? $t['clearance_rate'].'%' : '-';
@endphp
<section class="mt-10 bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8">
    <div class="flex items-center gap-2 mb-1">
        <span class="bg-red-50 text-red-600 p-2 rounded-lg shrink-0"><i data-lucide="shield-alert" class="w-5 h-5"></i></span>
        <h2 class="text-lg font-black text-gray-900">{{ $t['prefecture'] }}のバイク盗難データ（{{ $t['year'] }}年）</h2>
    </div>
    <p class="text-sm text-gray-600 leading-relaxed mb-4">
        {{ $t['year'] }}年の{{ $t['prefecture'] }}のオートバイ盗の認知件数は
        <span class="font-black text-red-600">{{ number_format($t['recognized']) }}件</span>
        （全国{{ $t['total'] }}都道府県中 <span class="font-black text-gray-900">第{{ $t['rank'] }}位</span>）、
        検挙率は <span class="font-black text-gray-900">{{ $rate }}</span> です。
    </p>

    @if(count($t['series']) >= 2)
    <div class="bg-gray-50 rounded-2xl p-4 mb-4">
        <p class="text-[11px] font-bold text-gray-400 mb-2">認知件数の推移（{{ $t['series'][0]['year'] }}〜{{ $t['year'] }}年）</p>
        @include('bikes.partials.sparkline', ['points' => array_column($t['series'], 'recognized'), 'w' => 260, 'h' => 44, 'color' => '#dc2626', 'label' => $t['prefecture'].'のオートバイ盗認知件数の推移'])
    </div>
    @endif

    <div class="rounded-2xl border border-gray-100 p-4">
        <p class="text-xs font-bold text-gray-700 mb-1">盗難対策の基本</p>
        <p class="text-[11px] text-gray-500 leading-relaxed">地球ロック（動かせない構造物と施錠）、複数ロックの併用、屋内・防犯カメラのある駐輪、車体カバーが有効とされています。加えて任意の盗難保険での備えも選択肢です。</p>
        @if(!empty($ctaUrl))
        <div class="mt-3">
            <span class="text-[10px] font-black tracking-widest text-gray-300 uppercase">PR</span>
            <a href="{{ $ctaUrl }}" target="_blank" rel="nofollow sponsored noopener"
               class="mt-1 flex items-center justify-center gap-1.5 bg-gray-900 hover:bg-black text-white text-xs font-bold w-full py-2.5 rounded-lg transition-colors">
                <i data-lucide="shield-check" class="w-4 h-4"></i>{{ $aff['headline'] }}
            </a>
        </div>
        @endif
    </div>

    <p class="text-[10px] text-gray-400 mt-3">
        出典: <a href="{{ $src['url'] }}" target="_blank" rel="nofollow noopener" class="underline hover:text-gray-600">{{ $src['label'] }}</a>（最終確認: {{ $src['checked_at'] }}）・
        <a href="{{ route('theft') }}" class="underline hover:text-gray-600 font-bold">全国のバイク盗難ランキングを見る</a>
    </p>
</section>
@endif
