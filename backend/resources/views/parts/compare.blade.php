<x-layout>
    <x-slot:title>{{ $displayTitle }} の価格比較 | MotoHub</x-slot:title>

    <x-slot:metaDescription>
        {{ $displayTitle }}の最安値を楽天市場・Yahoo!ショッピング・Amazonで比較。
    </x-slot:metaDescription>

    <x-slot:robotsMeta>noindex, follow</x-slot:robotsMeta>

    <x-slot:navigation>
        <x-navigation :showSearch="true" />
    </x-slot:navigation>

    @php
        $rakutenBest  = $rakutenItems[0] ?? null;
        $yahooBest    = $yahooItems[0] ?? null;
        $rakutenRest  = array_slice($rakutenItems, 1);
        $yahooRest    = array_slice($yahooItems, 1);
        $hasRest      = count($rakutenRest) > 0 || count($yahooRest) > 0;
        $commonCaption     = $rakutenBest['caption'] ?? '';
        $commonCaptionFull = $rakutenBest['caption_full'] ?? '';
        $commonJan         = $rakutenBest['jan_code'] ?? $jan ?? '';
        $commonPartNumber  = $rakutenBest['part_number'] ?? $partNumber ?? '';
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
            {{-- 共通: 品番・JAN・商品説明 --}}
            @if($commonCaption || $commonJan || $commonPartNumber)
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 sm:p-5">
                @if($commonPartNumber || $commonJan)
                <div class="flex items-center gap-2 flex-wrap mb-2">
                    @if($commonPartNumber)
                    <span class="bg-gray-100 text-gray-600 text-[10px] font-mono font-bold px-2 py-0.5 rounded">品番: {{ $commonPartNumber }}</span>
                    @endif
                    @if($commonJan)
                    <span class="bg-gray-100 text-gray-600 text-[10px] font-mono font-bold px-2 py-0.5 rounded">JAN: {{ $commonJan }}</span>
                    @endif
                </div>
                @endif
                @if($commonCaption)
                <div x-data="{ open: false }" class="text-xs text-gray-600 leading-relaxed">
                    <p x-show="!open" class="line-clamp-3">{{ $commonCaption }}@if(mb_strlen($commonCaptionFull) > 200)...@endif</p>
                    @if(mb_strlen($commonCaptionFull) > 200)
                    <p x-show="open" x-cloak>{{ $commonCaptionFull }}</p>
                    <button @click="open = !open" class="text-blue-500 hover:underline text-[10px] font-bold mt-1" x-text="open ? '閉じる' : 'もっと見る'"></button>
                    @endif
                </div>
                @endif
            </div>
            @endif

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

            <div class="flex justify-end mb-2">
                <span class="text-[10px] font-black tracking-widest text-gray-500 uppercase">PR・広告</span>
            </div>

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
                                <span class="text-gray-300 text-3xl">&#128295;</span>
                            @endif
                        </div>
                        <p class="text-xs text-gray-800 font-bold text-center line-clamp-2 mb-1">{{ $rakutenBest['name'] }}</p>
                        <p class="text-[10px] text-gray-400 mb-2">{{ $rakutenBest['shop'] }}</p>
                        <p class="text-2xl font-black mb-1 {{ $best && $best['source'] === 'rakuten' && $best['price'] === $rakutenBest['price'] ? 'text-green-600' : 'text-red-600' }}">&yen;{{ number_format($rakutenBest['price']) }}</p>
                        <div class="flex items-center gap-1.5 mb-1">
                            @if($best && $best['source'] === 'rakuten' && $best['price'] === $rakutenBest['price'])
                                <span class="text-[10px] font-bold text-green-600">最安値</span>
                            @endif
                            @if(($rakutenBest['point_rate'] ?? 1) > 1)
                                <span class="inline-block px-1.5 py-0.5 bg-red-100 text-red-600 text-[10px] font-bold rounded">ポイント{{ $rakutenBest['point_rate'] }}倍</span>
                            @endif
                        </div>
                        @if($rakutenBest['review_count'] > 0)
                        <p class="text-[10px] text-gray-400 mb-3">
                            <span class="text-yellow-500">&#9733;</span>{{ number_format($rakutenBest['review_avg'], 1) }}({{ $rakutenBest['review_count'] }})
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
                                <span class="text-gray-300 text-3xl">&#128295;</span>
                            @endif
                        </div>
                        <p class="text-xs text-gray-800 font-bold text-center line-clamp-2 mb-1">{{ $yahooBest['name'] }}</p>
                        <p class="text-[10px] text-gray-400 mb-2">{{ $yahooBest['shop'] }}</p>
                        <p class="text-2xl font-black mb-1 {{ $best && $best['source'] === 'yahoo' && $best['price'] === $yahooBest['price'] ? 'text-green-600' : 'text-blue-600' }}">&yen;{{ number_format($yahooBest['price']) }}</p>
                        @if($best && $best['source'] === 'yahoo' && $best['price'] === $yahooBest['price'])
                            <span class="text-[10px] font-bold text-green-600 mb-1">最安値</span>
                        @endif
                        @if($yahooBest['review_count'] > 0)
                        <p class="text-[10px] text-gray-400 mb-3">
                            <span class="text-yellow-500">&#9733;</span>{{ number_format($yahooBest['review_avg'], 1) }}({{ $yahooBest['review_count'] }})
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
                                    <span class="text-gray-300 text-xl">&#128295;</span>
                                @endif
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-1.5 mb-0.5">
                                    <span class="shrink-0 bg-red-100 text-red-600 text-[9px] font-black px-1.5 py-0.5 rounded">楽天</span>
                                    <h3 class="text-xs font-bold text-gray-800 line-clamp-1">{{ $item['name'] }}</h3>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <p class="text-[10px] text-gray-400">{{ $item['shop'] }}</p>
                                    @if(($item['point_rate'] ?? 1) > 1)
                                    <span class="inline-block px-1 py-0.5 bg-red-100 text-red-600 text-[9px] font-bold rounded">P{{ $item['point_rate'] }}倍</span>
                                    @endif
                                </div>
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

                    @if(count($rakutenRest) > 0 && count($yahooRest) > 0)
                    <div class="border-t-2 border-gray-200"></div>
                    @endif

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
                                    <span class="text-gray-300 text-xl">&#128295;</span>
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

            {{-- ポイント実質価格シミュレーター --}}
            @if(!empty($rakutenItems) || !empty($yahooItems))
            <section class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <h3 class="px-4 sm:px-5 py-3 border-b border-gray-100 text-sm font-black text-gray-800 flex items-center gap-2">
                    &#128176; ポイント実質価格シミュレーター
                </h3>
                <div class="p-4 sm:p-5 space-y-5">
                    {{-- 価格入力 --}}
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-red-600 mb-1">&#128308; 楽天の表示価格</label>
                            <input type="text" id="sim-rakuten-price" inputmode="numeric" placeholder="例: 5000"
                                value="{{ $rakutenBest['price'] ?? '' }}"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-400 focus:border-red-400 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-blue-600 mb-1">&#128993; Yahooの表示価格</label>
                            <input type="text" id="sim-yahoo-price" inputmode="numeric" placeholder="例: 4800"
                                value="{{ $yahooBest['price'] ?? '' }}"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-400 focus:border-blue-400 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-amber-600 mb-1">&#128309; Amazonの表示価格</label>
                            <input type="text" id="sim-amazon-price" inputmode="numeric" placeholder="Amazonで確認して入力"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-400 focus:border-amber-400 outline-none">
                        </div>
                    </div>

                    {{-- 設定チェックボックス --}}
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        {{-- 楽天SPU --}}
                        <div class="bg-red-50 rounded-lg p-4 border border-red-100">
                            <h4 class="text-xs font-black text-red-700 mb-3">&#128308; 楽天市場 SPU設定</h4>
                            <div class="space-y-2">
                                <label class="flex items-center gap-2 text-xs text-gray-700 cursor-pointer">
                                    <input type="checkbox" class="sim-rakuten-spu rounded border-gray-300 text-red-500" value="2"> 楽天カード（+2倍）
                                </label>
                                <label class="flex items-center gap-2 text-xs text-gray-700 cursor-pointer">
                                    <input type="checkbox" class="sim-rakuten-spu rounded border-gray-300 text-red-500" value="4"> 楽天モバイル（+4倍）
                                </label>
                                <label class="flex items-center gap-2 text-xs text-gray-700 cursor-pointer">
                                    <input type="checkbox" class="sim-rakuten-spu rounded border-gray-300 text-red-500" value="2"> 楽天ひかり（+2倍）
                                </label>
                                <label class="flex items-center gap-2 text-xs text-gray-700 cursor-pointer">
                                    <input type="checkbox" class="sim-rakuten-spu rounded border-gray-300 text-red-500" value="2"> プレミアムカード（+2倍）
                                </label>
                                <label class="flex items-center gap-2 text-xs text-gray-700 cursor-pointer" id="sim-rakuten-5day-label">
                                    <input type="checkbox" id="sim-rakuten-5day" class="sim-rakuten-spu rounded border-gray-300 text-red-500" value="4"> 0と5のつく日（+4倍）<span id="sim-rakuten-5day-badge" class="hidden"></span>
                                </label>
                                <div class="flex items-center gap-2 text-xs text-gray-700 flex-wrap">
                                    <input type="checkbox" id="sim-rakuten-marathon" class="rounded border-gray-300 text-red-500 cursor-pointer">
                                    <span>お買い物マラソン</span>
                                    <select id="sim-rakuten-marathon-val" class="border border-gray-300 rounded px-1.5 py-0.5 text-xs bg-white" disabled>
                                        @foreach(range(1, 9) as $n)
                                        <option value="{{ $n }}">+{{ $n }}倍</option>
                                        @endforeach
                                    </select>
                                    <span class="text-[10px] text-gray-400">※開催中の場合はチェック</span>
                                </div>
                            </div>
                            <div class="mt-3 pt-3 border-t border-red-200">
                                <div class="flex items-center justify-between text-xs">
                                    <span class="text-gray-600">店舗ポイント</span>
                                    <span class="font-bold text-red-600" id="sim-rakuten-store-pt">{{ $rakutenBest['point_rate'] ?? 1 }}倍</span>
                                </div>
                                <div class="flex items-center justify-between text-xs mt-1">
                                    <span class="font-bold text-gray-800">合計SPU倍率</span>
                                    <span class="font-black text-red-600 text-sm" id="sim-rakuten-total">1倍</span>
                                </div>
                            </div>
                        </div>

                        {{-- Yahoo設定 --}}
                        <div class="bg-blue-50 rounded-lg p-4 border border-blue-100">
                            <h4 class="text-xs font-black text-blue-700 mb-3">&#128993; Yahoo!ショッピング設定</h4>
                            <div class="space-y-2">
                                <label class="flex items-center gap-2 text-xs text-gray-700 cursor-pointer">
                                    <input type="checkbox" class="sim-yahoo-opt rounded border-gray-300 text-blue-500" value="1"> PayPayカード（+1%）
                                </label>
                                <label class="flex items-center gap-2 text-xs text-gray-700 cursor-pointer">
                                    <input type="checkbox" class="sim-yahoo-opt rounded border-gray-300 text-blue-500" value="2"> ソフトバンクユーザー（+2%）
                                </label>
                                <label class="flex items-center gap-2 text-xs text-gray-700 cursor-pointer" id="sim-yahoo-5day-label">
                                    <input type="checkbox" id="sim-yahoo-5day" class="sim-yahoo-opt rounded border-gray-300 text-blue-500" value="4"> 5のつく日（+4%）<span id="sim-yahoo-5day-badge" class="hidden"></span>
                                </label>
                                <label class="flex items-center gap-2 text-xs text-gray-700 cursor-pointer">
                                    <input type="checkbox" class="sim-yahoo-opt rounded border-gray-300 text-blue-500" value="2"> LYPプレミアム（+2%）
                                </label>
                            </div>
                            <div class="mt-3 pt-3 border-t border-blue-200">
                                <div class="flex items-center justify-between text-xs">
                                    <span class="font-bold text-gray-800">合計還元率</span>
                                    <span class="font-black text-blue-600 text-sm" id="sim-yahoo-total">1%</span>
                                </div>
                            </div>
                        </div>

                        {{-- Amazon設定 --}}
                        <div class="bg-amber-50 rounded-lg p-4 border border-amber-100">
                            <h4 class="text-xs font-black text-amber-700 mb-3">&#128309; Amazon設定</h4>
                            <div class="space-y-2">
                                <label class="flex items-center gap-2 text-xs text-gray-700 cursor-pointer">
                                    <input type="checkbox" id="sim-amazon-prime" class="rounded border-gray-300 text-amber-500"> プライム会員（送料無料）
                                </label>
                                <label class="flex items-center gap-2 text-xs text-gray-700 cursor-pointer">
                                    <input type="checkbox" id="sim-amazon-card" class="rounded border-gray-300 text-amber-500" value="1"> Amazonカード（+1%）
                                </label>
                            </div>
                            <div class="mt-3 pt-3 border-t border-amber-200">
                                <div class="flex items-center justify-between text-xs">
                                    <span class="font-bold text-gray-800">合計還元率</span>
                                    <span class="font-black text-amber-600 text-sm" id="sim-amazon-total">0%</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- 結果テーブル --}}
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm border-collapse" id="sim-result-table">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th class="text-left px-3 py-2.5 font-bold text-gray-700 border-b border-gray-200">サイト</th>
                                    <th class="text-right px-3 py-2.5 font-bold text-gray-700 border-b border-gray-200">表示価格</th>
                                    <th class="text-right px-3 py-2.5 font-bold text-gray-700 border-b border-gray-200">ポイント</th>
                                    <th class="text-right px-3 py-2.5 font-bold text-gray-700 border-b border-gray-200">実質価格</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr id="sim-row-rakuten" class="border-b border-gray-100">
                                    <td class="px-3 py-2.5 font-bold text-red-600">楽天</td>
                                    <td class="px-3 py-2.5 text-right text-gray-600" id="sim-r-display">---</td>
                                    <td class="px-3 py-2.5 text-right text-red-500 font-bold" id="sim-r-point">---</td>
                                    <td class="px-3 py-2.5 text-right font-black" id="sim-r-effective">---</td>
                                </tr>
                                <tr id="sim-row-yahoo" class="border-b border-gray-100 bg-gray-50/50">
                                    <td class="px-3 py-2.5 font-bold text-blue-600">Yahoo!</td>
                                    <td class="px-3 py-2.5 text-right text-gray-600" id="sim-y-display">---</td>
                                    <td class="px-3 py-2.5 text-right text-blue-500 font-bold" id="sim-y-point">---</td>
                                    <td class="px-3 py-2.5 text-right font-black" id="sim-y-effective">---</td>
                                </tr>
                                <tr id="sim-row-amazon">
                                    <td class="px-3 py-2.5 font-bold text-amber-600">Amazon</td>
                                    <td class="px-3 py-2.5 text-right text-gray-600" id="sim-a-display">---</td>
                                    <td class="px-3 py-2.5 text-right text-amber-500 font-bold" id="sim-a-point">---</td>
                                    <td class="px-3 py-2.5 text-right font-black" id="sim-a-effective">---</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    {{-- 結果メッセージ --}}
                    <div id="sim-message" class="hidden text-center py-2">
                        <p class="text-sm font-bold text-green-600" id="sim-message-text"></p>
                    </div>
                </div>
            </section>
            @endif

            {{-- 比較結果なしの場合 --}}
            @if(empty($rakutenItems) && empty($yahooItems))
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-8 text-center">
                <div class="text-5xl mb-3">&#128269;</div>
                <p class="text-gray-600 font-bold mb-2">比較できる商品が見つかりませんでした</p>
                <p class="text-gray-400 text-sm mb-6">パーツ検索から商品を検索して価格を比較できます</p>
                <a href="{{ route('parts.index') }}"
                   class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-bold text-sm px-6 py-2.5 rounded-lg transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                    パーツ検索で探す
                </a>
            </div>

            <section>
                <h3 class="text-lg font-black text-gray-800 mb-4">人気カテゴリから探す</h3>
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                    @foreach(config('parts-categories', []) as $cat)
                    <a href="{{ route('parts.category', $cat['slug']) }}"
                       class="bg-white rounded-xl border border-gray-100 overflow-hidden hover:shadow-md hover:border-blue-200 transition-all group">
                        <div class="aspect-square bg-gray-50 overflow-hidden flex items-center justify-center">
                            @if($categoryImages[$cat['slug']] ?? null)
                                <img src="{{ str_replace('?_ex=128x128', '?_ex=200x200', $categoryImages[$cat['slug']]) }}" alt="{{ $cat['name'] }}" class="w-full h-full object-contain p-2 group-hover:scale-105 transition-transform" loading="lazy">
                            @else
                                <span class="text-gray-300 text-3xl">&#128295;</span>
                            @endif
                        </div>
                        <div class="p-2 text-center">
                            <span class="text-xs font-black text-gray-800 group-hover:text-blue-600 transition-colors">{{ $cat['name'] }}</span>
                            <p class="text-[10px] text-gray-400 line-clamp-1 mt-0.5">{{ Str::limit($cat['description'], 30) }}</p>
                        </div>
                    </a>
                    @endforeach
                </div>
            </section>
            @endif

            {{-- 内部リンク: カテゴリカード --}}
            <section>
                <h3 class="text-sm font-black text-gray-800 mb-3">パーツカテゴリから探す</h3>
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                    @foreach(config('parts-categories', []) as $cat)
                    <a href="{{ route('parts.category', $cat['slug']) }}"
                       class="bg-white rounded-xl border border-gray-100 overflow-hidden hover:shadow-md hover:border-blue-200 transition-all group">
                        <div class="aspect-square bg-gray-50 overflow-hidden flex items-center justify-center">
                            @if($categoryImages[$cat['slug']] ?? null)
                                <img src="{{ str_replace('?_ex=128x128', '?_ex=200x200', $categoryImages[$cat['slug']]) }}" alt="{{ $cat['name'] }}" class="w-full h-full object-contain p-2 group-hover:scale-105 transition-transform" loading="lazy">
                            @else
                                <span class="text-gray-300 text-3xl">&#128295;</span>
                            @endif
                        </div>
                        <div class="p-2 text-center">
                            <span class="text-xs font-black text-gray-800 group-hover:text-blue-600 transition-colors">{{ $cat['name'] }}</span>
                            <p class="text-[10px] text-gray-400 line-clamp-1 mt-0.5">{{ Str::limit($cat['description'], 30) }}</p>
                        </div>
                    </a>
                    @endforeach
                </div>
            </section>

            {{-- 内部リンク: ブランドチップ --}}
            <section>
                <h3 class="text-sm font-black text-gray-800 mb-3">人気ブランドから探す</h3>
                @php
                    $compareBrands = ['ヨシムラ', 'デイトナ', 'キタコ', 'モリワキ', 'コミネ', 'RSタイチ',
                        'ショウエイ', 'アライ', 'ブリヂストン', 'ダンロップ', 'DID', 'RK', 'カストロール', 'ワコーズ'];
                @endphp
                <div class="flex flex-wrap gap-2">
                    @foreach($compareBrands as $brand)
                    <a href="{{ route('parts.index', ['keyword' => $brand]) }}"
                       class="inline-flex items-center px-3 py-1.5 bg-gray-100 hover:bg-blue-50 text-gray-600 hover:text-blue-600 text-sm font-bold rounded-full border border-gray-200 hover:border-blue-200 transition-colors">
                        {{ $brand }}
                    </a>
                    @endforeach
                </div>
            </section>

            {{-- 内部リンク: 車種カード --}}
            <section>
                <h3 class="text-sm font-black text-gray-800 mb-3">車種からパーツを探す</h3>
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                    @foreach($bikeCards as $bike)
                    <a href="{{ route('parts.index', ['keyword' => $bike['name']]) }}"
                       class="bg-white rounded-xl border border-gray-100 overflow-hidden hover:shadow-md hover:border-blue-200 transition-all group">
                        <div class="aspect-[4/3] bg-gray-50 overflow-hidden flex items-center justify-center">
                            @if($bike['image_url'] ?? null)
                                <img src="{{ $bike['image_url'] }}" alt="{{ $bike['name'] }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform" loading="lazy">
                            @else
                                <span class="text-gray-300 text-3xl">&#128661;</span>
                            @endif
                        </div>
                        <div class="p-2 text-center">
                            <span class="text-xs font-black text-gray-800 group-hover:text-blue-600 transition-colors">{{ $bike['name'] }}</span>
                        </div>
                    </a>
                    @endforeach
                </div>
            </section>

            <div class="text-center pt-2">
                <a href="{{ route('parts.index') }}"
                   class="inline-flex items-center gap-2 text-sm font-bold text-blue-600 hover:text-blue-800 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                    パーツ検索トップ
                </a>
            </div>
        </div>
    </div>

    <x-slot:scripts>
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        // ===== ポイントシミュレーター =====
        const rPriceInput = document.getElementById('sim-rakuten-price');
        const yPriceInput = document.getElementById('sim-yahoo-price');
        const aPriceInput = document.getElementById('sim-amazon-price');
        if (!rPriceInput) return;

        const storePointRate = {{ $rakutenBest['point_rate'] ?? 1 }};
        const marathonCheck = document.getElementById('sim-rakuten-marathon');
        const marathonVal = document.getElementById('sim-rakuten-marathon-val');
        const primeCheck = document.getElementById('sim-amazon-prime');
        const amazonCardCheck = document.getElementById('sim-amazon-card');

        const badgeHtml = '<span class="inline-block bg-red-500 text-white text-xs px-2 py-0.5 rounded-full ml-2 animate-pulse">本日開催中！</span>';

        // ===== 本日開催中の自動判定 =====
        const today = new Date().getDate();
        const isYahoo5day = (today % 10 === 5); // 5, 15, 25日
        const isRakuten5day = (today % 5 === 0); // 5, 10, 15, 20, 25, 30日

        // Yahoo 5のつく日
        if (isYahoo5day) {
            const yBadge = document.getElementById('sim-yahoo-5day-badge');
            if (yBadge) { yBadge.innerHTML = badgeHtml; yBadge.classList.remove('hidden'); }
            const yCb = document.getElementById('sim-yahoo-5day');
            if (yCb) yCb.checked = true;
        }

        // 楽天 0と5のつく日
        if (isRakuten5day) {
            const rBadge = document.getElementById('sim-rakuten-5day-badge');
            if (rBadge) { rBadge.innerHTML = badgeHtml; rBadge.classList.remove('hidden'); }
            const rCb = document.getElementById('sim-rakuten-5day');
            if (rCb) rCb.checked = true;
        }

        // マラソン連動
        marathonCheck.addEventListener('change', () => {
            marathonVal.disabled = !marathonCheck.checked;
            calculate();
        });

        function parsePrice(str) {
            return parseInt(String(str).replace(/[^0-9]/g, ''), 10) || 0;
        }

        function fmt(n) {
            return n.toLocaleString();
        }

        function calculate() {
            const rPrice = parsePrice(rPriceInput.value);
            const yPrice = parsePrice(yPriceInput.value);
            const aPrice = parsePrice(aPriceInput.value);

            // 楽天: base 1倍 + SPUチェック分 + マラソン + 店舗ポイント
            let spuTotal = 1; // base
            document.querySelectorAll('.sim-rakuten-spu:checked').forEach(cb => {
                spuTotal += parseInt(cb.value) || 0;
            });
            if (marathonCheck.checked) {
                spuTotal += parseInt(marathonVal.value) || 0;
            }
            const rTotalRate = spuTotal + storePointRate;
            document.getElementById('sim-rakuten-total').textContent = rTotalRate + '倍';

            // Yahoo: base 1% + チェック分
            let yRate = 1;
            document.querySelectorAll('.sim-yahoo-opt:checked').forEach(cb => {
                yRate += parseInt(cb.value) || 0;
            });
            document.getElementById('sim-yahoo-total').textContent = yRate + '%';

            // Amazon: カード還元
            let aRate = amazonCardCheck.checked ? 1 : 0;
            document.getElementById('sim-amazon-total').textContent = aRate + '%';

            // 実質価格計算
            const rPoint = rPrice > 0 ? Math.floor(rPrice * rTotalRate / 100) : 0;
            const rEffective = rPrice > 0 ? rPrice - rPoint : 0;

            const yPoint = yPrice > 0 ? Math.floor(yPrice * yRate / 100) : 0;
            const yEffective = yPrice > 0 ? yPrice - yPoint : 0;

            const aPoint = aPrice > 0 ? Math.floor(aPrice * aRate / 100) : 0;
            const aEffective = aPrice > 0 ? aPrice - aPoint : 0;

            // 表示更新
            document.getElementById('sim-r-display').textContent = rPrice > 0 ? '\u00A5' + fmt(rPrice) : '---';
            document.getElementById('sim-r-point').textContent = rPrice > 0 ? '-' + fmt(rPoint) + 'pt' : '---';
            document.getElementById('sim-r-effective').textContent = rPrice > 0 ? '\u00A5' + fmt(rEffective) : '---';

            document.getElementById('sim-y-display').textContent = yPrice > 0 ? '\u00A5' + fmt(yPrice) : '---';
            document.getElementById('sim-y-point').textContent = yPrice > 0 ? '-' + fmt(yPoint) + 'pt' : '---';
            document.getElementById('sim-y-effective').textContent = yPrice > 0 ? '\u00A5' + fmt(yEffective) : '---';

            document.getElementById('sim-a-display').textContent = aPrice > 0 ? '\u00A5' + fmt(aPrice) : '---';
            document.getElementById('sim-a-point').textContent = aPrice > 0 ? '-' + fmt(aPoint) + 'pt' : '---';
            document.getElementById('sim-a-effective').textContent = aPrice > 0 ? '\u00A5' + fmt(aEffective) : '---';

            // Prime表示
            if (primeCheck.checked && aPrice > 0) {
                document.getElementById('sim-a-point').textContent += ' + 送料無料';
            }

            // 最安ハイライト
            const rows = [
                { el: document.getElementById('sim-row-rakuten'), eff: rPrice > 0 ? rEffective : Infinity, name: '楽天' },
                { el: document.getElementById('sim-row-yahoo'), eff: yPrice > 0 ? yEffective : Infinity, name: 'Yahoo!' },
                { el: document.getElementById('sim-row-amazon'), eff: aPrice > 0 ? aEffective : Infinity, name: 'Amazon' },
            ];

            rows.forEach(r => {
                r.el.classList.remove('bg-green-50');
                r.el.querySelectorAll('td').forEach(td => td.classList.remove('text-green-700'));
            });

            const validRows = rows.filter(r => r.eff < Infinity);
            const msgEl = document.getElementById('sim-message');
            const msgText = document.getElementById('sim-message-text');

            if (validRows.length >= 2) {
                validRows.sort((a, b) => a.eff - b.eff);
                const best = validRows[0];
                best.el.classList.add('bg-green-50');
                best.el.querySelector('td:last-child').innerHTML = '\u00A5' + fmt(best.eff) + ' <span class="text-[10px] text-green-600 font-bold ml-1">&#9733;最安</span>';
                msgText.textContent = 'ポイント込みで' + best.name + 'が最安です！';
                msgEl.classList.remove('hidden');
            } else {
                msgEl.classList.add('hidden');
            }
        }

        // イベントリスナー
        [rPriceInput, yPriceInput, aPriceInput].forEach(input => {
            input.addEventListener('input', calculate);
        });
        document.querySelectorAll('.sim-rakuten-spu, .sim-yahoo-opt').forEach(cb => {
            cb.addEventListener('change', calculate);
        });
        marathonVal.addEventListener('change', calculate);
        primeCheck.addEventListener('change', calculate);
        amazonCardCheck.addEventListener('change', calculate);

        // 初回計算
        calculate();
    });
    </script>
    </x-slot:scripts>
</x-layout>
