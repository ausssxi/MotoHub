{{-- 車種ページ「プラグの目安」ブロック（面②・静的差込・キャッシュbump不要＝$model+DBの表示時計算）。
     verified な plug 適合が無い車種は何も出さない（mode=none）。一般フォールバックはしない
     （排気量から熱価・型番は断定できず thin/duplicate になるだけ。バッテリーと同方針）。
     ★型番は表示しない：区分（熱価/必要本数）のみ。型番・互換品番・価格比較は適合表ページへ一本化（カニバリ回避）。
     ★型番は商品検索の keyword に「内部的に」だけ使う（表示ではない）。複数型式のときはカードを出さず検索リンク1本。 --}}
@php $plug = \App\Support\PlugMaintenance::forModel($model); @endphp
@if($plug['mode'] === 'rich')
@php
    $heatDisp = $plug['heat'] ? ($plug['heat'] === '型式による' ? '型式による' : $plug['heat'].'番') : null;
    $plugsDisp = $plug['plugs'] ? ($plug['plugs'] === '型式による' ? '型式による' : $plug['plugs'].'本') : null;
@endphp
<div class="bg-white rounded-3xl shadow-sm p-5 sm:p-6 border border-gray-100">
    <h2 class="text-lg font-black text-gray-900 flex items-center gap-2 mb-1">
        <i data-lucide="zap" class="w-5 h-5 text-amber-500"></i>
        {{ $model->name }}のスパークプラグの目安
    </h2>
    <p class="text-[11px] font-bold text-amber-600 mb-4">この車種の規格（実データ）</p>
    {{-- 規格グリッド（型番は出さない＝区分レベルのみ）。値が型式で違うキーは「型式による」。 --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mb-3">
        @if($heatDisp)<div class="bg-gray-50 rounded-xl p-3"><p class="text-[10px] font-bold text-gray-400">熱価</p><p class="text-sm font-black text-gray-900">{{ $heatDisp }}</p></div>@endif
        @if($plugsDisp)<div class="bg-gray-50 rounded-xl p-3"><p class="text-[10px] font-bold text-gray-400">必要本数</p><p class="text-sm font-black text-gray-900">{{ $plugsDisp }}</p></div>@endif
        <div class="bg-gray-50 rounded-xl p-3"><p class="text-[10px] font-bold text-gray-400">交換時期の目安</p><p class="text-sm font-black text-gray-900">3,000〜5,000km</p></div>
    </div>
    <p class="text-[10px] text-gray-400 mb-4">
        @if(!empty($plug['sources']))出典:
            @foreach($plug['sources'] as $s)@if($s['url'])<a href="{{ $s['url'] }}" target="_blank" rel="nofollow noopener" class="underline hover:text-gray-600">{{ $s['name'] }}</a>@else{{ $s['name'] }}@endif@if(!$loop->last)、@endif @endforeach ・
        @endif
        確認: {{ optional($plug['verified_at'])->format('Y-m') }}（{{ $plug['frame_count'] }}型式に対応）
    </p>
    {{-- 型番・互換品番・価格比較は適合表ページへ一本化（勝負ワードの集約＝カニバリ回避） --}}
    <div class="mb-4">
        <a href="{{ $plug['fitment_url'] }}" class="inline-flex items-center gap-1 text-sm font-bold text-blue-600 hover:text-blue-700 hover:underline">
            {{ $model->name }}のプラグ型番・互換品番・価格比較を見る
            <i data-lucide="chevron-right" class="w-4 h-4"></i>
        </a>
    </div>
    {{-- おすすめプラグ：型番が単一のときだけ商品カード（keyword＝型番で商品マッチ精度を確保）。
         型式による（複数型番）ときはカードを出さず検索リンク1本に逃がす（外部API負荷回避・誤誘導防止）。 --}}
    @if($plug['product_keyword'])
    @php $plugProducts = app(\App\Services\Parts\ProductSearchService::class)->searchProducts($plug['product_keyword'], 4); @endphp
    @if(!empty($plugProducts))
    <div class="border-t border-gray-100 pt-4">
        <div class="flex items-center justify-between mb-2">
            <p class="text-xs font-black text-gray-700">おすすめプラグ</p>
            <span class="text-[10px] font-black tracking-widest text-gray-500 uppercase">PR・広告</span>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
            @foreach(array_slice($plugProducts, 0, 4) as $prod)
            <a href="{{ $prod['url'] }}" target="_blank" rel="nofollow sponsored noopener" class="block rounded-xl border border-gray-100 p-2 hover:shadow-md transition-shadow">
                @if(!empty($prod['image']))<img src="{{ $prod['image'] }}" alt="" loading="lazy" class="w-full h-16 object-contain mb-1">@endif
                <p class="text-[10px] text-gray-600 line-clamp-2 leading-tight">{{ $prod['name'] }}</p>
                @if(($prod['price'] ?? 0) > 0)<p class="text-[11px] font-black text-red-600 mt-0.5">¥{{ number_format($prod['price']) }}</p>@endif
            </a>
            @endforeach
        </div>
    </div>
    @endif
    @else
    {{-- 複数型式で型番が定まらない → 検索リンク1本 --}}
    <div class="border-t border-gray-100 pt-4">
        <a href="{{ $plug['search_url'] }}" class="inline-flex items-center gap-1 text-xs font-bold text-blue-600 hover:underline">
            <i data-lucide="search" class="w-3.5 h-3.5"></i>{{ $model->name }}の対応プラグを探す
        </a>
    </div>
    @endif
</div>
@endif
