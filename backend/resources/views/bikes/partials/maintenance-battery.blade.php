{{-- 車種ページ「バッテリーの目安」ブロック（面②・静的差込・キャッシュbump不要＝$model+DBの表示時計算）。
     verified な battery 適合が無い車種は何も出さない（mode=none）。一般フォールバックはしない
     （排気量から規格を断定できず thin/duplicate content になるだけのため。オイルとの決定的な違い）。
     ★型番は表示しない：区分（電圧/容量/タイプ）のみ。型番・互換品番・価格比較は適合表ページへ一本化（カニバリ回避）。
     ★型番は商品検索の keyword に「内部的に」だけ使う（表示ではない）。複数型式のときはカードを出さず検索リンク1本。 --}}
@php $battery = \App\Support\BatteryMaintenance::forModel($model); @endphp
@if($battery['mode'] === 'rich')
<div class="bg-white rounded-3xl shadow-sm p-5 sm:p-6 border border-gray-100">
    <h2 class="text-lg font-black text-gray-900 flex items-center gap-2 mb-1">
        <i data-lucide="battery-charging" class="w-5 h-5 text-emerald-500"></i>
        {{ $model->name }}のバッテリーの目安
    </h2>
    <p class="text-[11px] font-bold text-emerald-600 mb-4">この車種の規格（実データ）</p>

    {{-- 規格グリッド（型番は出さない＝区分レベルのみ）。値が型式で違うキーは「型式による」。 --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mb-3">
        @if($battery['voltage'])<div class="bg-gray-50 rounded-xl p-3"><p class="text-[10px] font-bold text-gray-400">電圧</p><p class="text-sm font-black text-gray-900">{{ $battery['voltage'] }}</p></div>@endif
        @if($battery['capacity'])<div class="bg-gray-50 rounded-xl p-3"><p class="text-[10px] font-bold text-gray-400">容量</p><p class="text-sm font-black text-gray-900">{{ $battery['capacity'] }}</p></div>@endif
        @if($battery['type'])<div class="bg-gray-50 rounded-xl p-3"><p class="text-[10px] font-bold text-gray-400">タイプ</p><p class="text-sm font-black text-gray-900">{{ $battery['type'] }}</p></div>@endif
        <div class="bg-gray-50 rounded-xl p-3"><p class="text-[10px] font-bold text-gray-400">交換時期の目安</p><p class="text-sm font-black text-gray-900">2〜3年</p></div>
    </div>
    <p class="text-[10px] text-gray-400 mb-4">
        @if(!empty($battery['sources']))出典:
            @foreach($battery['sources'] as $s)@if($s['url'])<a href="{{ $s['url'] }}" target="_blank" rel="nofollow noopener" class="underline hover:text-gray-600">{{ $s['name'] }}</a>@else{{ $s['name'] }}@endif@if(!$loop->last)、@endif @endforeach ・
        @endif
        確認: {{ optional($battery['verified_at'])->format('Y-m') }}（{{ $battery['frame_count'] }}型式に対応）
    </p>

    {{-- 型番・互換品番・価格比較は適合表ページへ一本化（勝負ワードの集約＝カニバリ回避） --}}
    <div class="mb-4">
        <a href="{{ $battery['fitment_url'] }}" class="inline-flex items-center gap-1 text-sm font-bold text-blue-600 hover:text-blue-700 hover:underline">
            {{ $model->name }}のバッテリー型番・互換品番・価格比較を見る
            <i data-lucide="chevron-right" class="w-4 h-4"></i>
        </a>
    </div>

    {{-- おすすめバッテリー：型番が単一のときだけ商品カード（keyword＝型番で商品マッチ精度を確保・キャッシュも型番共有で効く）。
         型式による（複数型番）ときはカードを出さず検索リンク1本に逃がす（外部API負荷回避・誤誘導防止）。 --}}
    @if($battery['product_keyword'])
    @php $batteryProducts = app(\App\Services\Parts\ProductSearchService::class)->searchProducts($battery['product_keyword'], 4); @endphp
    @if(!empty($batteryProducts))
    <div class="border-t border-gray-100 pt-4">
        <div class="flex items-center justify-between mb-2">
            <p class="text-xs font-black text-gray-700">おすすめバッテリー</p>
            <span class="text-[9px] font-black tracking-widest text-gray-300 uppercase">PR・広告</span>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
            @foreach(array_slice($batteryProducts, 0, 4) as $prod)
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
    {{-- 複数型式で型番が定まらない → 検索リンク1本（車種依存keyword膨大＋外部API負荷を回避し内部parts検索へ） --}}
    <div class="border-t border-gray-100 pt-4">
        <a href="{{ $battery['search_url'] }}" class="inline-flex items-center gap-1 text-xs font-bold text-blue-600 hover:underline">
            <i data-lucide="search" class="w-3.5 h-3.5"></i>{{ $model->name }}の対応バッテリーを探す
        </a>
    </div>
    @endif
</div>
@endif
