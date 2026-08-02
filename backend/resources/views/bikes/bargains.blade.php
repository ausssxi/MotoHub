<x-layout>
    <x-slot:title>お買い得バイク {{ $data['totalCount'] }}台 | 相場より20%以上安い中古バイク | MotoHub</x-slot:title>
    <x-slot:metaDescription>相場より20%以上安いお買い得中古バイクを{{ $data['totalCount'] }}台掲載。最大{{ $data['maxDiscount'] }}%OFF、平均{{ $data['avgDiscount'] }}%OFFの掘り出し物をチェック。</x-slot:metaDescription>
    <x-slot:ogImage>{{ route('bikes.bargains_ogp') }}</x-slot:ogImage>
    <x-slot:navigation><x-navigation :showSearch="true" /></x-slot:navigation>

    <div class="max-w-4xl mx-auto px-4 py-6 sm:py-10">
        {{-- パンくず --}}
        <nav class="flex text-xs font-bold text-gray-400 mb-6">
            <ol class="flex items-center space-x-2">
                <li><a href="{{ route('bikes.index') }}" class="hover:text-black transition">HOME</a></li>
                <li><span class="text-gray-300">/</span></li>
                <li class="text-gray-600">お買い得バイク</li>
            </ol>
        </nav>

        <h1 class="text-2xl sm:text-3xl font-black text-gray-900 mb-2 flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-7 h-7 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            お買い得バイク
        </h1>
        <p class="text-sm text-gray-500 mb-2">相場より20%以上安い掘り出し物を毎日更新</p>
        <a href="{{ route('market') }}" class="inline-flex items-center gap-1 mb-6 text-xs font-black text-blue-600 hover:underline">
            <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>中古バイク相場トップへ
        </a>

        {{-- 統計ヘッダー --}}
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 mb-6">
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div class="text-center">
                    <div class="text-2xl font-black text-gray-900">{{ number_format($data['totalCount']) }}</div>
                    <div class="text-xs text-gray-500 font-bold mt-1">掲載台数</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-black text-red-600">{{ $data['maxDiscount'] }}%</div>
                    <div class="text-xs text-gray-500 font-bold mt-1">最大割引</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-black text-gray-900">{{ $data['avgDiscount'] }}%</div>
                    <div class="text-xs text-gray-500 font-bold mt-1">平均割引</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-black text-gray-900">{{ number_format($data['minPrice'] / 10000, 1) }}<span class="text-sm">万〜</span></div>
                    <div class="text-xs text-gray-500 font-bold mt-1">最安価格</div>
                </div>
            </div>
        </div>

        {{-- Xシェアボタン --}}
        @php
            $shareText = 'お買い得バイク' . number_format($data['totalCount']) . '台掲載中！最大' . $data['maxDiscount'] . '%OFF！相場より安い掘り出し物をチェック #MotoHub #中古バイク #バイク好きと繋がりたい #バイク乗りと繋がりたい #バイクのある生活 #お買い得';
        @endphp
        <a href="https://twitter.com/intent/tweet?text={{ urlencode($shareText) }}&url={{ urlencode(route('bikes.bargains')) }}"
           target="_blank" rel="noopener noreferrer"
           class="inline-flex items-center gap-1.5 mb-6 px-4 py-2 bg-gray-900 text-white text-xs font-bold rounded-full hover:bg-gray-700 transition">
            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
            シェア
        </a>

        @if($data['totalCount'] > 0)
        {{-- メーカー別 横棒グラフ --}}
        @if($data['makerCounts']->isNotEmpty())
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 mb-6">
            <h2 class="text-base font-black text-gray-900 mb-4 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                メーカー別
            </h2>
            @php $maxMaker = $data['makerCounts']->max('cnt') ?: 1; @endphp
            <div class="space-y-2">
                @foreach($data['makerCounts']->take(10) as $maker)
                <div class="flex items-center gap-3">
                    <span class="text-xs font-bold text-gray-600 w-28 text-right flex-shrink-0">{{ $maker['name'] }}</span>
                    <div class="flex-1 bg-gray-100 rounded-full h-6 overflow-hidden">
                        <div class="bg-blue-400 h-full rounded-full flex items-center justify-end pr-2 transition-all"
                             style="width: {{ max(($maker['cnt'] / $maxMaker) * 100, 5) }}%">
                            <span class="text-[10px] font-black text-white">{{ number_format($maker['cnt']) }}</span>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- 割引率帯別分布 --}}
        @if($data['discountBands']->isNotEmpty())
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 mb-6">
            <h2 class="text-base font-black text-gray-900 mb-4 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                割引率帯別
            </h2>
            @php $maxBand = $data['discountBands']->max('cnt') ?: 1; @endphp
            <div class="space-y-2">
                @foreach($data['discountBands'] as $band)
                <div class="flex items-center gap-3">
                    <span class="text-xs font-bold text-gray-600 w-24 text-right flex-shrink-0">{{ $band['label'] }}</span>
                    <div class="flex-1 bg-gray-100 rounded-full h-6 overflow-hidden">
                        <div class="bg-red-400 h-full rounded-full flex items-center justify-end pr-2 transition-all"
                             style="width: {{ max(($band['cnt'] / $maxBand) * 100, 5) }}%">
                            <span class="text-[10px] font-black text-white">{{ number_format($band['cnt']) }}</span>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- お買い得車両一覧 --}}
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 mb-6">
            <h2 class="text-base font-black text-gray-900 mb-4 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-yellow-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
                お買い得車両一覧（割引率順）
            </h2>
            <div class="space-y-3">
                @foreach($data['bargains'] as $item)
                <a href="{{ route('bikes.show', $item['id']) }}"
                   class="flex items-center gap-3 p-3 rounded-xl hover:bg-gray-50 transition border border-gray-50">
                    {{-- 画像 --}}
                    @if(!empty($item['image']))
                        <img src="{{ $item['image'] }}" alt="{{ $item['name'] }}"
                             class="w-20 h-16 sm:w-24 sm:h-18 object-cover rounded-lg shrink-0" loading="lazy" onerror="handleImageError(this)">
                    @else
                        <div class="w-20 h-16 sm:w-24 sm:h-18 bg-gray-100 rounded-lg shrink-0 flex items-center justify-center">
                            <span class="text-gray-300 text-[10px]">No Image</span>
                        </div>
                    @endif

                    {{-- 情報 --}}
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-0.5">
                            <span class="inline-block bg-red-600 text-white text-[10px] font-black px-2 py-0.5 rounded-full">{{ $item['percent_off'] }}%OFF</span>
                            @if(!empty($item['manufacturer']))
                                <span class="text-[10px] font-bold text-gray-400">{{ $item['manufacturer'] }}</span>
                            @endif
                        </div>
                        <div class="text-sm font-black text-gray-900 truncate">{{ $item['name'] }}</div>
                        <div class="flex items-center gap-3 mt-0.5">
                            <span class="text-sm font-black text-red-600">{{ number_format($item['price']) }}円</span>
                            <span class="text-[10px] text-gray-400 line-through">相場 {{ number_format($item['avg_price']) }}円</span>
                        </div>
                        <div class="text-[10px] text-gray-400 mt-0.5">
                            {{ $item['model_year'] ? $item['model_year'] . '年' : '' }}
                            {{ $item['mileage'] ? number_format($item['mileage']) . 'km' : '' }}
                        </div>
                    </div>
                </a>
                @endforeach
            </div>
        </div>

        @else
        <div class="text-center py-16 text-gray-400">
            <p class="text-lg font-bold">現在お買い得車両はありません</p>
            <p class="text-sm mt-2">定期的にチェックしてください</p>
        </div>
        @endif

        <p class="text-[10px] text-gray-400 mt-6 text-center">※同モデル3台以上の掲載がある車種について、相場平均より20%以上安い車両を「お買い得」として掲載しています。</p>
    </div>

    {{-- パンくずJSON-LD --}}
    <script type="application/ld+json">
    {
        "@@context": "https://schema.org",
        "@@type": "BreadcrumbList",
        "itemListElement": [
            {"@@type": "ListItem", "position": 1, "name": "HOME", "item": "{{ url('/') }}"},
            {"@@type": "ListItem", "position": 2, "name": "お買い得バイク"}
        ]
    }
    </script>
</x-layout>
