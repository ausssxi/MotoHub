<x-layout>
    <x-slot:title>{{ $displayTitle }} の価格比較 | MotoHub</x-slot:title>

    <x-slot:metaDescription>
        {{ $displayTitle }}の最安値を楽天市場・Yahoo!ショッピング・Amazonで比較。
    </x-slot:metaDescription>

    <x-slot:navigation>
        <x-navigation :showSearch="true" />
    </x-slot:navigation>

    @php
        $rakutenBest  = $rakutenItems[0] ?? null;
        $yahooBest    = $yahooItems[0] ?? null;
        $rakutenRest  = array_slice($rakutenItems, 1);
        $yahooRest    = array_slice($yahooItems, 1);
        $hasRest      = count($rakutenRest) > 0 || count($yahooRest) > 0;
    @endphp

    <div class="bg-gray-50 min-h-screen">
        {{-- ヘッダー --}}
        <section class="bg-gradient-to-r from-gray-900 to-gray-800 text-white py-8">
            <div class="max-w-5xl mx-auto px-4">
                <a href="{{ url()->previous() }}" class="inline-flex items-center gap-1 text-gray-400 hover:text-white text-xs mb-3 transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                    検索結果に戻る
                </a>
                <h1 class="text-lg sm:text-xl font-black leading-tight line-clamp-2">{{ $displayTitle }}</h1>
                <div class="flex items-center gap-3 mt-2 flex-wrap">
                    <p class="text-gray-400 text-xs">楽天・Yahoo!・Amazonで価格を比較</p>
                    @if($jan)
                        <span class="bg-gray-700 text-gray-300 text-[10px] font-mono px-2 py-0.5 rounded">JAN: {{ $jan }}</span>
                    @endif
                    @if($partNumber)
                        <span class="bg-gray-700 text-gray-300 text-[10px] font-mono px-2 py-0.5 rounded">品番: {{ $partNumber }}</span>
                    @endif
                </div>
            </div>
        </section>

        <div class="max-w-5xl mx-auto px-4 py-6 space-y-6">
            {{-- 最安価格サマリー --}}
            @if($best)
            <div class="bg-white rounded-xl shadow-sm border border-green-200 p-4 sm:p-5">
                <div class="flex items-center gap-3 flex-wrap">
                    <span class="bg-green-100 text-green-700 text-[10px] font-black px-2.5 py-1 rounded-full uppercase tracking-wide">最安値</span>
                    <span class="text-2xl sm:text-3xl font-black text-green-600">&yen;{{ number_format($best['price']) }}</span>
                    <span class="text-sm text-gray-500">
                        @if($best['source'] === 'rakuten')
                            <span class="inline-block bg-red-100 text-red-600 text-[10px] font-bold px-2 py-0.5 rounded">楽天</span>
                        @else
                            <span class="inline-block bg-blue-100 text-blue-600 text-[10px] font-bold px-2 py-0.5 rounded">Yahoo!</span>
                        @endif
                        {{ $best['shop'] }}
                    </span>
                </div>
            </div>
            @endif

            {{-- 3列比較 --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                {{-- 楽天 最安1件 --}}
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden flex flex-col">
                    <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-center gap-2">
                        <span class="bg-[#bf0000] text-white text-[10px] font-black px-2.5 py-1 rounded">楽天市場</span>
                    </div>
                    @if($rakutenBest)
                    <div class="p-4 flex flex-col items-center flex-grow">
                        <div class="w-24 h-24 bg-gray-100 rounded-lg overflow-hidden flex items-center justify-center mb-3">
                            @if($rakutenBest['image'])
                                <img src="{{ str_replace('?_ex=128x128', '?_ex=200x200', $rakutenBest['image']) }}" alt="" class="w-full h-full object-contain p-1" loading="lazy">
                            @else
                                <span class="text-gray-300 text-3xl">🔧</span>
                            @endif
                        </div>
                        <p class="text-xs text-gray-800 font-bold text-center line-clamp-2 mb-1">{{ $rakutenBest['name'] }}</p>
                        <p class="text-[10px] text-gray-400 mb-2">{{ $rakutenBest['shop'] }}</p>
                        <p class="text-2xl font-black mb-1 {{ $best && $best['source'] === 'rakuten' && $best['price'] === $rakutenBest['price'] ? 'text-green-600' : 'text-red-600' }}">&yen;{{ number_format($rakutenBest['price']) }}</p>
                        @if($best && $best['source'] === 'rakuten' && $best['price'] === $rakutenBest['price'])
                            <span class="text-[10px] font-bold text-green-600 mb-2">最安値</span>
                        @endif
                        @if($rakutenBest['review_count'] > 0)
                        <p class="text-[10px] text-gray-400 mb-3">
                            <span class="text-yellow-500">★</span>{{ number_format($rakutenBest['review_avg'], 1) }}({{ $rakutenBest['review_count'] }})
                        </p>
                        @endif
                        <a href="{{ $rakutenBest['url'] }}" target="_blank" rel="noopener noreferrer"
                            class="mt-auto w-full block text-center bg-red-500 hover:bg-red-600 text-white text-xs font-bold py-2.5 rounded-lg transition-colors">
                            楽天で購入
                        </a>
                    </div>
                    @else
                    <div class="p-6 flex-grow flex items-center justify-center">
                        <p class="text-gray-400 text-sm text-center">見つかりませんでした</p>
                    </div>
                    @endif
                </div>

                {{-- Yahoo! 最安1件 --}}
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden flex flex-col">
                    <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-center gap-2">
                        <span class="bg-blue-500 text-white text-[10px] font-black px-2.5 py-1 rounded">Yahoo!</span>
                    </div>
                    @if($yahooBest)
                    <div class="p-4 flex flex-col items-center flex-grow">
                        <div class="w-24 h-24 bg-gray-100 rounded-lg overflow-hidden flex items-center justify-center mb-3">
                            @if($yahooBest['image'])
                                <img src="{{ $yahooBest['image'] }}" alt="" class="w-full h-full object-contain p-1" loading="lazy">
                            @else
                                <span class="text-gray-300 text-3xl">🔧</span>
                            @endif
                        </div>
                        <p class="text-xs text-gray-800 font-bold text-center line-clamp-2 mb-1">{{ $yahooBest['name'] }}</p>
                        <p class="text-[10px] text-gray-400 mb-2">{{ $yahooBest['shop'] }}</p>
                        <p class="text-2xl font-black mb-1 {{ $best && $best['source'] === 'yahoo' && $best['price'] === $yahooBest['price'] ? 'text-green-600' : 'text-blue-600' }}">&yen;{{ number_format($yahooBest['price']) }}</p>
                        @if($best && $best['source'] === 'yahoo' && $best['price'] === $yahooBest['price'])
                            <span class="text-[10px] font-bold text-green-600 mb-2">最安値</span>
                        @endif
                        @if($yahooBest['review_count'] > 0)
                        <p class="text-[10px] text-gray-400 mb-3">
                            <span class="text-yellow-500">★</span>{{ number_format($yahooBest['review_avg'], 1) }}({{ $yahooBest['review_count'] }})
                        </p>
                        @else
                        <div class="mb-3"></div>
                        @endif
                        <a href="{{ $yahooBest['url'] }}" target="_blank" rel="noopener noreferrer"
                            class="mt-auto w-full block text-center bg-blue-500 hover:bg-blue-600 text-white text-xs font-bold py-2.5 rounded-lg transition-colors"
                            style="background-color:#3b82f6;color:#fff">
                            Yahoo!で購入
                        </a>
                    </div>
                    @else
                    <div class="p-6 flex-grow flex items-center justify-center">
                        <p class="text-gray-400 text-sm text-center">見つかりませんでした</p>
                    </div>
                    @endif
                </div>

                {{-- Amazon --}}
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden flex flex-col">
                    <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-center gap-2">
                        <span class="bg-amber-500 text-white text-[10px] font-black px-2.5 py-1 rounded">Amazon</span>
                    </div>
                    <div class="p-4 flex flex-col items-center flex-grow">
                        <div class="w-24 h-24 bg-gray-50 rounded-lg flex items-center justify-center mb-3">
                            <svg class="w-12 h-12 text-amber-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349m-16.5 11.65V9.35m0 0a3.001 3.001 0 003.75-.615A2.993 2.993 0 009.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 002.25 1.016c.896 0 1.7-.393 2.25-1.016A3.001 3.001 0 0021 9.349m-18 0A2.25 2.25 0 005.25 7.5h13.5A2.25 2.25 0 0021 9.349"/></svg>
                        </div>
                        <p class="text-xs text-gray-500 text-center mb-1">Amazon</p>
                        <p class="text-[10px] text-gray-400 mb-2">価格はAmazonで確認</p>
                        <p class="text-2xl font-black text-gray-300 mb-1">---</p>
                        <div class="mb-3"></div>
                        <a href="{{ $amazonUrl }}" target="_blank" rel="noopener noreferrer"
                            class="mt-auto w-full block text-center bg-amber-500 hover:bg-amber-600 text-white font-bold text-xs py-2.5 rounded-lg transition-colors"
                            style="background-color:#f59e0b;color:#fff">
                            Amazonで探す
                        </a>
                    </div>
                </div>
            </div>

            {{-- 他のショップも見る（折りたたみ） --}}
            @if($hasRest)
            <details class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <summary class="px-4 sm:px-5 py-3 cursor-pointer hover:bg-gray-50 transition-colors flex items-center gap-2 text-sm font-bold text-gray-700">
                    <svg class="w-4 h-4 text-gray-400 transition-transform details-arrow" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                    他のショップも見る（{{ count($rakutenRest) + count($yahooRest) }}件）
                </summary>
                <div class="border-t border-gray-100">
                    {{-- 楽天 2番目以降 --}}
                    @if(count($rakutenRest) > 0)
                    <div class="px-4 sm:px-5 py-2.5 bg-gray-50 border-b border-gray-100 flex items-center gap-2">
                        <span class="bg-[#bf0000] text-white text-[10px] font-black px-2.5 py-0.5 rounded">楽天市場</span>
                        <span class="text-xs font-bold text-gray-600">{{ count($rakutenRest) }}件</span>
                    </div>
                    <div class="divide-y divide-gray-100">
                        @foreach($rakutenRest as $item)
                        <div class="flex items-center gap-3 p-3 sm:p-4 hover:bg-gray-50 transition-colors">
                            <div class="shrink-0 w-14 h-14 bg-gray-100 rounded-lg overflow-hidden flex items-center justify-center">
                                @if($item['image'])
                                    <img src="{{ str_replace('?_ex=128x128', '?_ex=200x200', $item['image']) }}" alt="" class="w-full h-full object-contain p-1" loading="lazy">
                                @else
                                    <span class="text-gray-300 text-xl">🔧</span>
                                @endif
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-1.5 mb-0.5">
                                    <span class="shrink-0 bg-red-100 text-red-600 text-[9px] font-black px-1.5 py-0.5 rounded">楽天</span>
                                    <h3 class="text-xs font-bold text-gray-800 line-clamp-1">{{ $item['name'] }}</h3>
                                </div>
                                <p class="text-[10px] text-gray-400">{{ $item['shop'] }}</p>
                            </div>
                            <div class="shrink-0 text-right flex flex-col items-end justify-center gap-1">
                                <span class="text-sm font-black text-red-600">&yen;{{ number_format($item['price']) }}</span>
                                <a href="{{ $item['url'] }}" target="_blank" rel="noopener noreferrer"
                                    class="text-[10px] text-red-500 hover:text-red-600 font-bold whitespace-nowrap">購入 &rarr;</a>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    @endif

                    {{-- 区切り線 --}}
                    @if(count($rakutenRest) > 0 && count($yahooRest) > 0)
                    <div class="border-t-2 border-gray-200"></div>
                    @endif

                    {{-- Yahoo 2番目以降 --}}
                    @if(count($yahooRest) > 0)
                    <div class="px-4 sm:px-5 py-2.5 bg-gray-50 border-b border-gray-100 flex items-center gap-2">
                        <span class="bg-blue-500 text-white text-[10px] font-black px-2.5 py-0.5 rounded">Yahoo!ショッピング</span>
                        <span class="text-xs font-bold text-gray-600">{{ count($yahooRest) }}件</span>
                    </div>
                    <div class="divide-y divide-gray-100">
                        @foreach($yahooRest as $item)
                        <div class="flex items-center gap-3 p-3 sm:p-4 hover:bg-gray-50 transition-colors">
                            <div class="shrink-0 w-14 h-14 bg-gray-100 rounded-lg overflow-hidden flex items-center justify-center">
                                @if($item['image'])
                                    <img src="{{ $item['image'] }}" alt="" class="w-full h-full object-contain p-1" loading="lazy">
                                @else
                                    <span class="text-gray-300 text-xl">🔧</span>
                                @endif
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-1.5 mb-0.5">
                                    <span class="shrink-0 bg-blue-100 text-blue-600 text-[9px] font-black px-1.5 py-0.5 rounded">Yahoo!</span>
                                    <h3 class="text-xs font-bold text-gray-800 line-clamp-1">{{ $item['name'] }}</h3>
                                </div>
                                <p class="text-[10px] text-gray-400">{{ $item['shop'] }}</p>
                            </div>
                            <div class="shrink-0 text-right flex flex-col items-end justify-center gap-1">
                                <span class="text-sm font-black text-blue-600">&yen;{{ number_format($item['price']) }}</span>
                                <a href="{{ $item['url'] }}" target="_blank" rel="noopener noreferrer"
                                    class="text-[10px] text-blue-500 hover:text-blue-600 font-bold whitespace-nowrap">購入 &rarr;</a>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    @endif
                </div>
            </details>
            <style>
                details[open] .details-arrow { transform: rotate(180deg); }
            </style>
            @endif
        </div>
    </div>
</x-layout>
