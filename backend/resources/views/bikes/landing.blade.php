<x-layout>
    {{-- SEO用タイトル・説明文の設定 --}}
    <x-slot:title>{{ $pageInfo['title'] }} | MotoHub</x-slot:title>
    <x-slot:metaDescription>{{ $pageInfo['description'] }}</x-slot:metaDescription>

    <x-slot:styles>
        <link rel="stylesheet" href="{{ asset('css/bike-search.css') }}">
    </x-slot:styles>

    <x-slot:scripts>
        <script src="{{ asset('js/search/sidebar.js') }}"></script>
        <script src="{{ asset('js/compare/manager.js') }}"></script>
        <script src="{{ asset('js/compare/ui.js') }}"></script>
    </x-slot:scripts>

    <x-slot:navigation>
        <x-navigation :showSearch="true" />
    </x-slot:navigation>

    <div class="bg-gray-50 min-h-screen">
        
        {{-- SEOヘッダーセクション --}}
        <div class="bg-white border-b border-gray-200 pt-8 pb-10 px-4">
            <div class="max-w-7xl mx-auto">
                {{-- パンくずリスト --}}
                <nav class="flex text-xs font-bold text-gray-400 mb-6" aria-label="Breadcrumb">
                    <ol class="flex items-center space-x-2">
                        <li><a href="/" class="hover:text-gray-600 transition-colors">HOME</a></li>
                        <li><span class="text-gray-300">＞</span></li>
                        <li><a href="{{ route('bikes.prefectures') }}" class="hover:text-gray-600 transition-colors">地域から探す</a></li>
                        <li><span class="text-gray-300">＞</span></li>
                        <li><a href="{{ route('bikes.search', ['prefecture' => $prefecture]) }}" class="hover:text-gray-600 transition-colors">{{ $prefecture }}</a></li>
                        <li><span class="text-gray-300">＞</span></li>
                        <li><span class="text-gray-800">{{ $pageInfo['target_name'] }}</span></li>
                    </ol>
                </nav>

                {{-- H1見出し --}}
                <h1 class="text-2xl sm:text-3xl font-black text-gray-900 mb-3 tracking-tight">
                    {!! $pageInfo['h1_html'] !!}
                </h1>
                <p class="text-sm text-gray-500 font-medium max-w-3xl leading-relaxed">
                    {{ $pageInfo['description'] }}
                </p>
            </div>
        </div>

        <div class="py-8">
            <div class="max-w-7xl mx-auto px-4 flex flex-col lg:flex-row gap-8">
                
                {{-- サイドバー（簡易版：エリア内の絞り込み） --}}
                <aside class="w-full lg:w-64 flex-shrink-0">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 sticky top-24">
                        <h3 class="text-xs font-black text-gray-400 uppercase tracking-widest mb-4">エリアを変更する</h3>
                        <div class="grid grid-cols-2 gap-2">
                            @foreach(['東京', '神奈川', '埼玉', '千葉', '大阪', '愛知'] as $pref)
                                <a href="{{ route('bikes.landing', ['prefecture' => $pref, 'slug' => request()->slug]) }}" 
                                   class="text-xs font-bold text-center py-2 rounded-lg bg-gray-50 hover:bg-blue-50 text-gray-600 hover:text-blue-600 transition-colors {{ $prefecture == $pref ? 'bg-blue-600 text-white hover:bg-blue-700 hover:text-white' : '' }}">
                                    {{ $pref }}
                                </a>
                            @endforeach
                        </div>
                        <div class="mt-4 pt-4 border-t border-gray-100 text-center">
                            <a href="{{ route('bikes.prefectures') }}" class="text-xs font-bold text-blue-600 hover:underline">すべての地域を見る</a>
                        </div>
                    </div>
                </aside>

                {{-- メインコンテンツ（検索結果一覧） --}}
                <div class="flex-1">
                    {{-- 結果件数 --}}
                    <div class="mb-6 flex items-baseline gap-2">
                        <span class="text-2xl font-black text-black">{{ number_format($pagination['total']) }}</span>
                        <span class="text-sm font-bold text-gray-500">台が見つかりました</span>
                    </div>

                    {{-- 車両グリッド --}}
                    <div id="results-grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                        @forelse ($items as $listing)
                            <div class="bg-white rounded-2xl overflow-hidden shadow-sm hover:shadow-xl transition-all duration-300 flex flex-col group border border-gray-100 relative cursor-pointer">
                                <a href="{{ route('bikes.show', $listing['id']) }}" class="absolute inset-0 z-20"></a>
                                
                                <div class="aspect-[4/3] relative overflow-hidden bg-gray-50">
                                    @if(!empty($listing['images']) && isset($listing['images'][0]))
                                        <img src="{{ $listing['images'][0] }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" 
                                             onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1558981403-c5f9899a28bc?q=80&w=600&auto=format&fit=crop'; this.classList.add('grayscale', 'opacity-50');">
                                    @else
                                        {{-- ★修正: 画像がない場合もダミー画像を表示 --}}
                                        <img src="https://images.unsplash.com/photo-1558981403-c5f9899a28bc?q=80&w=600&auto=format&fit=crop" 
                                             class="w-full h-full object-cover grayscale opacity-50 group-hover:scale-105 transition-transform duration-500" 
                                             alt="No Image">
                                        <div class="absolute inset-0 flex items-center justify-center">
                                            <i data-lucide="image-off" class="w-8 h-8 text-white/50"></i>
                                        </div>
                                    @endif
                                    <div class="absolute bottom-2 right-2 bg-black/60 backdrop-blur-sm text-white px-2 py-0.5 rounded text-[10px] font-black">
                                        {{ $listing['total_price'] }}万円
                                    </div>
                                </div>

                                <div class="p-4 flex-grow flex flex-col">
                                    <div class="flex items-center gap-2 mb-2 flex-wrap">
                                        <span class="text-[9px] font-black text-blue-600 bg-blue-50 px-2 py-0.5 rounded uppercase">{{ $listing['maker'] }}</span>
                                        <span class="text-[9px] font-black text-orange-600 bg-orange-50 px-2 py-0.5 rounded uppercase">{{ $listing['category'] }}</span>
                                        <span class="text-[9px] font-black text-green-600 bg-green-50 px-2 py-0.5 rounded uppercase">{{ $listing['condition'] }}</span>
                                        <span class="text-[9px] font-black text-purple-600 bg-purple-50 px-2 py-0.5 rounded uppercase">{{ $listing['prefecture'] }}</span>
                                    </div>

                                    <h3 class="text-sm font-black text-gray-800 mb-2 line-clamp-2 leading-tight group-hover:text-blue-600 transition-colors">{{ $listing['name'] }}</h3>
                                    
                                    <div class="mt-auto pt-3 border-t border-gray-50 flex justify-between items-end">
                                        <div class="flex items-center gap-1 text-[10px] text-gray-500 font-bold">
                                            <i data-lucide="store" class="w-3 h-3"></i>
                                            <span class="truncate max-w-[10em]">{{ $listing['shop_name'] }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="col-span-full py-20 text-center bg-white rounded-3xl border border-gray-100">
                                <i data-lucide="search-x" class="w-12 h-12 text-gray-200 mx-auto mb-4"></i>
                                <p class="text-gray-400 font-bold text-sm">条件に一致するバイクは見つかりませんでした。</p>
                                <a href="{{ route('bikes.search', ['prefecture' => $prefecture]) }}" class="inline-block mt-4 text-blue-600 font-bold text-xs hover:underline">
                                    {{ $prefecture }}の全車両を見る
                                </a>
                            </div>
                        @endforelse
                    </div>

                    {{-- ページネーション --}}
                    @if($pagination['last_page'] > 1)
                    <div class="mt-16 flex justify-center">
                        <div class="flex items-center gap-2">
                            {{-- 前へ --}}
                            @if($pagination['prev_url'])
                                <a href="{{ $pagination['prev_url'] }}" class="w-10 h-10 flex items-center justify-center rounded-lg bg-white border border-gray-200 hover:border-black transition-all"><i data-lucide="chevron-left" class="w-4 h-4"></i></a>
                            @endif

                            {{-- 数字のページネーション --}}
                            @if(isset($pagination['pages']))
                                @foreach($pagination['pages'] as $page)
                                    @if($page['is_dot'])
                                        <span class="px-1 text-gray-300">...</span>
                                    @else
                                        <a href="{{ $page['url'] }}" 
                                           class="w-10 h-10 flex items-center justify-center rounded-lg font-black text-sm transition-all {{ $page['is_active'] ? 'bg-black text-white shadow-lg' : 'bg-white border border-gray-200 text-gray-400 hover:border-black' }}">
                                            {{ $page['label'] }}
                                        </a>
                                    @endif
                                @endforeach
                            @endif

                            {{-- 次へ --}}
                            @if($pagination['next_url'])
                                <a href="{{ $pagination['next_url'] }}" class="w-10 h-10 flex items-center justify-center rounded-lg bg-white border border-gray-200 hover:border-black transition-all"><i data-lucide="chevron-right" class="w-4 h-4"></i></a>
                            @endif
                        </div>
                    </div>
                    @endif

                </div>
            </div>
        </div>
    </div>
</x-layout>