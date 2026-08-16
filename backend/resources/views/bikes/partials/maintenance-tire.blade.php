{{-- 車種ページ「タイヤサイズの目安」ブロック（面②・静的差込・キャッシュbump不要＝$model+表示時計算）。
     tire_size_front/rear（bike_models）を extractSize で正規化コアサイズにして表示＋商品keyword化。
     抽出不能・壊れデータ("?"等)は mode=none で非表示（偽サイズ・文字化けを出さない）。一般フォールバックはしない。
     ★スペック表(:867 付近)にも生サイズは出ているため、本ブロックは「クリーンな正規化サイズ＋交換目安＋商品＋注記」で付加価値を出す。
     ★用品アフィリは ProductSearchService（関連度順・サイズkeyword＝実API検証済で実タイヤがヒット）。前後別サイズは前後別keyword。 --}}
@php
    $tire = \App\Support\TireMaintenance::forModel($model);
    $ps = app(\App\Services\Parts\ProductSearchService::class);
    // 前後同一＝1グループ4枚、相違＝前後2列各2枚、片側のみ＝その側のみ。
    $groups = [];
    if ($tire['mode'] === 'rich') {
        if ($tire['same']) {
            $groups[] = ['label' => 'おすすめタイヤ', 'size' => $tire['front'], 'items' => $ps->searchProducts($tire['front_keyword'], 4)];
        } else {
            if ($tire['front_keyword']) $groups[] = ['label' => 'フロントタイヤ', 'size' => $tire['front'], 'items' => $ps->searchProducts($tire['front_keyword'], 2)];
            if ($tire['rear_keyword']) $groups[] = ['label' => 'リアタイヤ', 'size' => $tire['rear'], 'items' => $ps->searchProducts($tire['rear_keyword'], 2)];
        }
    }
    $hasProducts = collect($groups)->contains(fn ($g) => ! empty($g['items']));
    $perGroup = $tire['mode'] === 'rich' && $tire['same'] ? 4 : 2;
@endphp
@if($tire['mode'] === 'rich')
<div class="bg-white rounded-3xl shadow-sm p-5 sm:p-6 border border-gray-100">
    <h2 class="text-lg font-black text-gray-900 flex items-center gap-2 mb-1">
        <i data-lucide="circle-dashed" class="w-5 h-5 text-slate-500"></i>
        {{ $model->name }}のタイヤサイズ
    </h2>
    <p class="text-[11px] font-bold text-slate-500 mb-4">純正装着サイズ（メーカー公表スペック）</p>

    {{-- サイズ（正規化コア。文字化け"?"やLI/速度記号は出さず、商品検索にも使える形に統一） --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mb-3">
        @if($tire['same'])
            <div class="bg-gray-50 rounded-xl p-3"><p class="text-[10px] font-bold text-gray-400">前後共通</p><p class="text-sm font-black text-gray-900">{{ $tire['front'] }}</p></div>
        @else
            @if($tire['front'])<div class="bg-gray-50 rounded-xl p-3"><p class="text-[10px] font-bold text-gray-400">フロント</p><p class="text-sm font-black text-gray-900">{{ $tire['front'] }}</p></div>@endif
            @if($tire['rear'])<div class="bg-gray-50 rounded-xl p-3"><p class="text-[10px] font-bold text-gray-400">リア</p><p class="text-sm font-black text-gray-900">{{ $tire['rear'] }}</p></div>@endif
        @endif
        <div class="bg-gray-50 rounded-xl p-3"><p class="text-[10px] font-bold text-gray-400">交換の目安</p><p class="text-sm font-black text-gray-900">残溝1.6mm/3〜5年</p></div>
    </div>
    <p class="text-[10px] text-gray-400 mb-4 leading-relaxed">
        ※純正装着サイズの目安です。年式・グレードやインチアップ等でサイズが異なる場合があるため、交換時は必ず現車のタイヤ側面の表記でご確認ください。
    </p>

    {{-- おすすめタイヤ（サイズkeyword・関連度順で実タイヤがヒット。前後別サイズは前後別グループ）。PR/rel明記。 --}}
    @if($hasProducts)
    <div class="border-t border-gray-100 pt-4 space-y-4">
        <div class="flex items-center justify-between">
            <p class="text-xs font-black text-gray-700">おすすめタイヤ</p>
            <span class="text-[10px] font-black tracking-widest text-gray-500 uppercase">PR・広告</span>
        </div>
        @foreach($groups as $g)
            @if(!empty($g['items']))
            <div>
                @unless($tire['same'])<p class="text-[11px] font-bold text-gray-500 mb-1.5">{{ $g['label'] }}（{{ $g['size'] }}）</p>@endunless
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                    @foreach(array_slice($g['items'], 0, $perGroup) as $prod)
                    <a href="{{ $prod['url'] }}" target="_blank" rel="nofollow sponsored noopener" class="block rounded-xl border border-gray-100 p-2 hover:shadow-md transition-shadow">
                        @if(!empty($prod['image']))<img src="{{ $prod['image'] }}" alt="" loading="lazy" class="w-full h-16 object-contain mb-1">@endif
                        <p class="text-[10px] text-gray-600 line-clamp-2 leading-tight">{{ $prod['name'] }}</p>
                        @if(($prod['price'] ?? 0) > 0)<p class="text-[11px] font-black text-red-600 mt-0.5">¥{{ number_format($prod['price']) }}</p>@endif
                    </a>
                    @endforeach
                </div>
            </div>
            @endif
        @endforeach
    </div>
    @else
    {{-- 商品が取れないとき（keyword該当無し等）は検索リンク1本に逃がす --}}
    <div class="border-t border-gray-100 pt-4">
        <a href="{{ $tire['search_url'] }}" class="inline-flex items-center gap-1 text-xs font-bold text-blue-600 hover:underline">
            <i data-lucide="search" class="w-3.5 h-3.5"></i>{{ $model->name }}のタイヤを探す
        </a>
    </div>
    @endif
</div>
@endif
