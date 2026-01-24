<x-layout>
    <x-slot:title>
        {{ $listing->name }} | MotoHub
    </x-slot:title>

    <x-slot:navigation>
        <x-navigation :totalListingsCount="$totalListingsCount" :showSearch="true" />
    </x-slot:navigation>

    {{-- パンくずリスト --}}
    <div class="bg-white border-b border-gray-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3">
            <nav class="flex text-xs font-bold text-gray-400" aria-label="Breadcrumb">
                <ol class="flex items-center space-x-2">
                    <li><a href="/" class="hover:text-gray-600 transition-colors">HOME</a></li>
                    <li><span class="text-gray-300">/</span></li>
                    <li><a href="{{ route('bikes.search') }}" class="hover:text-gray-600 transition-colors">中古車検索</a></li>
                    <li><span class="text-gray-300">/</span></li>
                    <li><span class="text-gray-800">{{ $listing->name }}</span></li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="bg-gray-50 min-h-screen py-6 sm:py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <div class="lg:grid lg:grid-cols-12 lg:gap-8">
                {{-- メインカラム（左側：画像・詳細） --}}
                <div class="lg:col-span-8 space-y-8">
                    
                    {{-- 1. 画像ギャラリー --}}
                    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden">
                        {{-- メイン画像 --}}
                        <div class="aspect-[4/3] bg-gray-100 relative group">
                            @if(!empty($listing->images) && count($listing->images) > 0)
                                <img src="{{ $listing->images[0] }}" alt="{{ $listing->name }}" class="w-full h-full object-cover">
                            @else
                                <div class="flex items-center justify-center h-full text-gray-300">
                                    <i data-lucide="image-off" class="w-12 h-12"></i>
                                </div>
                            @endif
                            
                            {{-- 画像枚数バッジ --}}
                            <div class="absolute bottom-4 right-4 bg-black/70 text-white px-3 py-1 rounded-full text-xs font-bold backdrop-blur-sm">
                                <i data-lucide="camera" class="w-3 h-3 inline mr-1"></i>
                                {{ count($listing->images ?? []) }}枚
                            </div>
                        </div>

                        {{-- サムネイルリスト（横スクロール） --}}
                        @if(!empty($listing->images) && count($listing->images) > 1)
                        <div class="flex gap-2 p-4 overflow-x-auto scrollbar-hide">
                            @foreach($listing->images as $img)
                                <button class="shrink-0 w-20 h-20 rounded-lg overflow-hidden border-2 border-transparent hover:border-blue-600 transition-all">
                                    <img src="{{ $img }}" class="w-full h-full object-cover">
                                </button>
                            @endforeach
                        </div>
                        @endif
                    </div>

                    {{-- 2. 車両基本情報 --}}
                    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8">
                        <div class="flex flex-wrap items-start justify-between gap-4 mb-6">
                            <div>
                                <h1 class="text-2xl sm:text-3xl font-black text-gray-900 leading-tight mb-2">{{ $listing->name }}</h1>
                                <div class="flex items-center gap-3 text-sm font-bold text-gray-500">
                                    <span class="bg-gray-100 text-gray-600 px-2 py-1 rounded">{{ $listing->maker }}</span>
                                    <span>ID: {{ $listing->id }}</span>
                                </div>
                            </div>
                            
                            {{-- お気に入り・比較ボタン --}}
                            <div class="flex items-center gap-2">
                                <button class="compare-toggle-btn w-12 h-12 rounded-full border border-gray-200 flex items-center justify-center text-gray-400 hover:bg-gray-50 transition-colors" data-id="{{ $listing->id }}">
                                    <i data-lucide="layers" class="w-5 h-5"></i>
                                </button>
                                <button class="wishlist-btn w-12 h-12 rounded-full border border-gray-200 flex items-center justify-center text-gray-400 hover:bg-gray-50 transition-colors" data-id="{{ $listing->id }}">
                                    <i data-lucide="heart" class="w-5 h-5"></i>
                                </button>
                            </div>
                        </div>

                        {{-- スペックグリッド --}}
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-8">
                            {{-- 
                                修正箇所：
                                ListingResourceですでに「～年」「～km」「～cc」「あり/なし」の文字列になっているため、
                                Blade側では number_format や 単位の付与を行わず、そのまま表示します。
                            --}}
                            <div class="bg-gray-50 rounded-2xl p-4 text-center">
                                <div class="text-xs font-bold text-gray-400 mb-1">年式</div>
                                <div class="text-lg font-black text-gray-900">{{ $listing->model_year }}</div>
                            </div>
                            <div class="bg-gray-50 rounded-2xl p-4 text-center">
                                <div class="text-xs font-bold text-gray-400 mb-1">走行距離</div>
                                <div class="text-lg font-black text-gray-900">{{ $listing->mileage }}</div>
                            </div>
                            <div class="bg-gray-50 rounded-2xl p-4 text-center">
                                <div class="text-xs font-bold text-gray-400 mb-1">排気量</div>
                                <div class="text-lg font-black text-gray-900">{{ $listing->displacement }}</div>
                            </div>
                            <div class="bg-gray-50 rounded-2xl p-4 text-center">
                                <div class="text-xs font-bold text-gray-400 mb-1">修復歴</div>
                                <div class="text-lg font-black text-gray-900">{{ $listing->repair_history }}</div>
                            </div>
                        </div>

                        {{-- 説明文エリア --}}
                        <div class="prose prose-sm max-w-none text-gray-600">
                            <h3 class="text-lg font-black text-gray-900 mb-3">車両の状態・コメント</h3>
                            <div class="whitespace-pre-wrap leading-relaxed">
                                @if(!empty($listing->description))
                                    {{ $listing->description }}
                                @else
                                    <p>ご覧いただきありがとうございます。<br>
                                    {{ $listing->maker }} {{ $listing->name }} の掲載車両です。</p>

                                    <ul class="list-disc pl-5 my-4 space-y-1">
                                        <li>年式: {{ $listing->model_year }}</li>
                                        <li>走行距離: {{ $listing->mileage }}</li>
                                        {{-- Resourceで「〇〇.〇」の形式になっているので単位は「万円」にします --}}
                                        <li>支払総額: {{ $listing->total_price }} 万円</li>
                                    </ul>

                                    <p>本車両は「{{ $listing->shop_name }}」にて販売中です。<br>
                                    車両の状態や見積もりの詳細については、ページ内の「在庫確認・見積もり」ボタンから販売店へ直接お問い合わせください。</p>
                                    
                                    <p class="text-xs text-gray-400 mt-4">
                                        ※このコメントは車両データから自動生成されています。詳細は販売店にご確認ください。
                                    </p>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- 3. 販売店情報 --}}
                    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8">
                        <h3 class="text-lg font-black text-gray-900 mb-6 flex items-center gap-2">
                            <i data-lucide="store" class="w-5 h-5 text-gray-400"></i>
                            販売店情報
                        </h3>
                        <div class="flex items-start gap-6">
                            <div class="w-16 h-16 bg-gray-100 rounded-full shrink-0 overflow-hidden flex items-center justify-center">
                                <i data-lucide="map-pin" class="w-8 h-8 text-gray-300"></i>
                            </div>
                            <div>
                                <div class="font-black text-xl text-gray-900 mb-2">{{ $listing->shop_name }}</div>
                                <div class="text-sm font-bold text-gray-500 space-y-1">
                                    <p>{{ $listing->shop_address ?? '住所情報なし' }}</p>
                                    <p>TEL: {{ $listing->shop_tel ?? '-' }}</p>
                                    <p>営業時間: {{ $listing->shop_hours ?? '-' }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- 4. 最近見た車両 --}}
                    <div id="history-widget-container" class="pt-8">
                        <div id="history-widget" class="hidden"></div>
                    </div>

                </div>

                {{-- サイドバー（右側：価格・CV・追従） --}}
                <div class="lg:col-span-4 mt-8 lg:mt-0">
                    <div class="sticky top-6 space-y-4">
                        <div class="bg-white rounded-3xl shadow-xl shadow-blue-900/5 border border-blue-100 p-6 sm:p-8">
                            <div class="text-center mb-6">
                                <div class="text-sm font-bold text-gray-400 mb-1">支払総額</div>
                                <div class="text-4xl font-black text-red-500 tracking-tight">
                                    {{-- Resource側で1/10000されているため、単位は「万円」が適切 --}}
                                    {{ $listing->total_price }}
                                    <span class="text-sm text-gray-500 font-bold ml-1">万円</span>
                                </div>
                                @if($listing->price && $listing->price !== '-')
                                <div class="text-xs font-bold text-gray-400 mt-2">
                                    (車両本体価格: {{ $listing->price }}万円)
                                </div>
                                @endif
                            </div>

                            <div class="space-y-3">
                                <a href="{{ $listing->url }}" target="_blank" class="block w-full bg-red-600 hover:bg-red-500 text-white font-black text-center py-4 rounded-xl shadow-lg shadow-red-500/30 transition-all hover:-translate-y-1">
                                    在庫確認・見積もり
                                    <span class="block text-[10px] font-medium opacity-80 mt-0.5">（外部サイトへ移動します）</span>
                                </a>
                                <button class="block w-full bg-white border-2 border-gray-100 hover:border-blue-600 text-gray-700 hover:text-blue-600 font-bold text-center py-3 rounded-xl transition-all">
                                    電話で問い合わせる
                                </button>
                            </div>
                            
                            <div class="mt-6 pt-6 border-t border-gray-100 text-center">
                                <div class="text-[10px] font-bold text-gray-400">
                                    ※ 見積もり依頼は無料です。<br>
                                    MotoHubを見たとお伝えください。
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- スマホ用固定フッターCV --}}
    <div class="fixed bottom-0 left-0 w-full bg-white/90 backdrop-blur-md border-t border-gray-200 p-4 lg:hidden z-50 safe-area-bottom">
        <div class="flex gap-3">
            <div class="flex-1">
                <div class="text-[10px] font-bold text-gray-400">支払総額</div>
                <div class="text-xl font-black text-red-500">{{ $listing->total_price }}<span class="text-xs text-gray-500 ml-0.5">万円</span></div>
            </div>
            <a href="{{ $listing->url }}" target="_blank" class="flex-1 bg-red-600 text-white font-black flex items-center justify-center rounded-lg shadow-lg">
                見積もり依頼
            </a>
        </div>
    </div>

    {{-- 履歴保存用スクリプト --}}
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            if (window.HistoryManager) {
                const listingId = parseInt('{{ $listing->id }}');
                if (!isNaN(listingId)) {
                    HistoryManager.push(listingId);
                    HistoryManager.render('history-widget');
                }
            }
        });
    </script>
</x-layout>