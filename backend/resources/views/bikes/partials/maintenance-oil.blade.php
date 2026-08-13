{{-- 車種ページ「オイル交換の目安」ブロック（面②・静的差込・キャッシュbump不要＝$model+config/DBの表示時計算）。
     verified な oil 適合があれば車種別リッチ、無ければ排気量帯の一般目安（幅・注記・出典）。
     用品アフィリは ProductSearchService（粘度/排気量帯keyword＝数種収束でキャッシュ有）。フィルタは検索リンク1本。
     ★数字は創作しない：rich は verified DB値、general は config の一般レンジ＋注記＋出典＋最終確認日。 --}}
@php $oil = \App\Support\OilMaintenance::forModel($model); @endphp
<div class="bg-white rounded-3xl shadow-sm p-5 sm:p-6 border border-gray-100">
    <h2 class="text-lg font-black text-gray-900 flex items-center gap-2 mb-1">
        <i data-lucide="droplet" class="w-5 h-5 text-amber-500"></i>
        {{ $model->name }}のオイル交換の目安
    </h2>

    @if($oil['mode'] === 'rich')
    @php $r = $oil['rich']; @endphp
    <p class="text-[11px] font-bold text-emerald-600 mb-4">この車種の推奨（実データ）</p>
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mb-3">
        @if($r['viscosity'])<div class="bg-gray-50 rounded-xl p-3"><p class="text-[10px] font-bold text-gray-400">粘度</p><p class="text-sm font-black text-gray-900">{{ $r['viscosity'] }}</p></div>@endif
        @if($r['capacity_change'])<div class="bg-gray-50 rounded-xl p-3"><p class="text-[10px] font-bold text-gray-400">オイル量（交換時）</p><p class="text-sm font-black text-gray-900">{{ $r['capacity_change'] }}</p></div>@endif
        @if($r['capacity_filter'])<div class="bg-gray-50 rounded-xl p-3"><p class="text-[10px] font-bold text-gray-400">フィルター交換時</p><p class="text-sm font-black text-gray-900">{{ $r['capacity_filter'] }}</p></div>@endif
        @if($r['jaso'])<div class="bg-gray-50 rounded-xl p-3"><p class="text-[10px] font-bold text-gray-400">JASO規格</p><p class="text-sm font-black text-gray-900">{{ $r['jaso'] }}</p></div>@endif
        @if($r['interval'])<div class="bg-gray-50 rounded-xl p-3"><p class="text-[10px] font-bold text-gray-400">交換時期の目安</p><p class="text-sm font-black text-gray-900">{{ $r['interval'] }}</p></div>@endif
    </div>
    <p class="text-[10px] text-gray-400 mb-4">
        @if(!empty($r['sources']))出典:
            @foreach($r['sources'] as $s)@if($s['url'])<a href="{{ $s['url'] }}" target="_blank" rel="nofollow noopener" class="underline hover:text-gray-600">{{ $s['name'] }}</a>@else{{ $s['name'] }}@endif@if(!$loop->last)、@endif @endforeach ・
        @endif
        確認: {{ optional($r['verified_at'])->format('Y-m') ?? $oil['checked_at'] }}
    </p>

    @else
    @php $g = $oil['general']; @endphp
    <p class="text-[11px] font-bold text-gray-400 mb-3">※一般的な目安（{{ $g['label'] ?? '' }}）</p>
    <div class="grid grid-cols-2 gap-3 mb-3">
        <div class="bg-gray-50 rounded-xl p-3"><p class="text-[10px] font-bold text-gray-400">オイル量（目安）</p><p class="text-sm font-black text-gray-900">{{ $g['capacity'] ?? '-' }}</p></div>
        <div class="bg-gray-50 rounded-xl p-3"><p class="text-[10px] font-bold text-gray-400">交換時期（目安）</p><p class="text-sm font-black text-gray-900">{{ $g['interval'] ?? '-' }}</p></div>
    </div>
    <p class="text-[10px] text-gray-400 mb-4 leading-relaxed">{{ config('fitments.oil_general.note') }}（出典: {{ config('fitments.oil_general.source_label') }}／最終確認: {{ $oil['checked_at'] }}）</p>
    @endif

    {{-- おすすめオイル（用品アフィリ・粘度/排気量帯keyword＝キャッシュ効く。PR/rel明記） --}}
    @php $oilProducts = app(\App\Services\Parts\ProductSearchService::class)->searchProducts($oil['oil_keyword'], 4); @endphp
    @if(!empty($oilProducts))
    <div class="border-t border-gray-100 pt-4">
        <div class="flex items-center justify-between mb-2">
            <p class="text-xs font-black text-gray-700">おすすめエンジンオイル</p>
            <span class="text-[9px] font-black tracking-widest text-gray-300 uppercase">PR・広告</span>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
            @foreach(array_slice($oilProducts, 0, 4) as $prod)
            <a href="{{ $prod['url'] }}" target="_blank" rel="nofollow sponsored noopener" class="block rounded-xl border border-gray-100 p-2 hover:shadow-md transition-shadow">
                @if(!empty($prod['image']))<img src="{{ $prod['image'] }}" alt="" loading="lazy" class="w-full h-16 object-contain mb-1">@endif
                <p class="text-[10px] text-gray-600 line-clamp-2 leading-tight">{{ $prod['name'] }}</p>
                @if(($prod['price'] ?? 0) > 0)<p class="text-[11px] font-black text-red-600 mt-0.5">¥{{ number_format($prod['price']) }}</p>@endif
            </a>
            @endforeach
        </div>
    </div>
    @endif

    {{-- オイルフィルターは検索リンク1本（車種依存でkeyword膨大＋5222ページの外部API負荷を回避し内部parts検索へ） --}}
    <div class="mt-3">
        <a href="{{ $oil['filter_search_url'] }}" class="inline-flex items-center gap-1 text-xs font-bold text-blue-600 hover:underline">
            <i data-lucide="search" class="w-3.5 h-3.5"></i>{{ $model->name }}のオイルフィルターを探す
        </a>
    </div>
</div>
