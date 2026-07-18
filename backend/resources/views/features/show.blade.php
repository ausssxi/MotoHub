<x-layout>
    {{-- SEO メタ情報。件数はKPI（無ければpagination）。0台は noindex（category-landing と同house style）。 --}}
    @php $featureCount = (int) ($featureKpi['total_count'] ?? $pagination['total'] ?? 0); @endphp
    <x-slot:title>{{ $feature->title }}@if($featureCount > 0)【{{ number_format($featureCount) }}台】@endif | MotoHub</x-slot:title>
    <x-slot:metaDescription>{{ $feature->meta_description }}</x-slot:metaDescription>

    @if($pagination['total'] === 0)
        <x-slot:robotsMeta>noindex, follow</x-slot:robotsMeta>
    @endif

    <x-slot:styles>
        {{-- 構造化データ: BreadcrumbList + ItemList(Product/Offer)。ItemList は在庫が有る時のみ。 --}}
        @php
            $breadcrumbSchema = [
                '@context' => 'https://schema.org', '@type' => 'BreadcrumbList',
                'itemListElement' => [
                    ['@type' => 'ListItem', 'position' => 1, 'name' => 'HOME', 'item' => url('/')],
                    ['@type' => 'ListItem', 'position' => 2, 'name' => '特集一覧', 'item' => route('features.index')],
                    ['@type' => 'ListItem', 'position' => 3, 'name' => $feature->title, 'item' => url()->current()],
                ],
            ];
            $featureItemList = [
                '@context' => 'https://schema.org', '@type' => 'ItemList', 'name' => $feature->title,
                'itemListElement' => collect($items ?? [])->values()->map(function ($it, $i) {
                    $product = ['@type' => 'Product', 'name' => $it['name'] ?? '中古バイク', 'url' => route('bikes.show', $it['id'])];
                    if (! empty($it['images'][0])) { $product['image'] = $it['images'][0]; }
                    // total_price は「万円」整形文字列（例 15.0）。円に復元して Offer に載せる。
                    $raw = str_replace(',', '', (string) ($it['total_price'] ?? ''));
                    if (is_numeric($raw) && (float) $raw > 0) {
                        $product['offers'] = [
                            '@type' => 'Offer', 'price' => (int) round(((float) $raw) * 10000), 'priceCurrency' => 'JPY',
                            'availability' => 'https://schema.org/InStock', 'url' => route('bikes.show', $it['id']),
                        ];
                    }
                    return ['@type' => 'ListItem', 'position' => $i + 1, 'item' => $product];
                })->all(),
            ];
        @endphp
        <script type="application/ld+json">{!! json_encode($breadcrumbSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
        @if(! empty($featureItemList['itemListElement']))
        <script type="application/ld+json">{!! json_encode($featureItemList, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
        @endif
    </x-slot:styles>

    <x-slot:scripts>
        <script src="{{ asset('js/compare/manager.js') }}?v={{ asset_buster(public_path('js/compare/manager.js')) }}" defer></script>
        <script src="{{ asset('js/compare/ui.js') }}?v={{ asset_buster(public_path('js/compare/ui.js')) }}" defer></script>
    </x-slot:scripts>

    <x-slot:navigation>
        <x-navigation :showSearch="true" />
    </x-slot:navigation>

    <div class="bg-gray-50 min-h-screen">

        {{-- ヒーローセクション --}}
        <div class="bg-gradient-to-br from-gray-900 via-gray-800 to-blue-900 text-white pt-10 pb-12 px-4 relative overflow-hidden">
            {{-- 装飾用ぼかし円 --}}
            <div class="absolute inset-0 opacity-10 pointer-events-none">
                <div class="absolute -right-20 -top-20 w-96 h-96 bg-blue-500 rounded-full blur-3xl"></div>
                <div class="absolute -left-20 -bottom-20 w-96 h-96 bg-purple-500 rounded-full blur-3xl"></div>
            </div>

            <div class="max-w-7xl mx-auto relative z-10">
                {{-- パンくずリスト --}}
                <nav class="flex text-xs font-bold text-gray-400 mb-6" aria-label="Breadcrumb">
                    <ol class="flex items-center space-x-2">
                        <li><a href="/" class="hover:text-white transition-colors">HOME</a></li>
                        <li><span class="text-gray-600">＞</span></li>
                        <li><a href="{{ route('features.index') }}" class="hover:text-white transition-colors">特集一覧</a></li>
                        <li><span class="text-gray-600">＞</span></li>
                        <li><span class="text-white">{{ $feature->title }}</span></li>
                    </ol>
                </nav>

                {{-- H1タイトル --}}
                <h1 class="text-2xl sm:text-4xl font-black tracking-tight mb-4">
                    {{ $feature->title }}
                </h1>

                {{-- リード文 (content_header) --}}
                <div class="prose prose-invert prose-sm max-w-4xl text-gray-300 leading-relaxed">
                    {!! $feature->content_header !!}
                </div>

                {{-- 件数バッジ --}}
                <div class="mt-6 inline-flex items-center gap-2 bg-white/10 backdrop-blur-sm px-4 py-2 rounded-full border border-white/10">
                    <i data-lucide="search" class="w-4 h-4"></i>
                    <span class="font-black text-lg">{{ number_format($featureKpi['total_count'] ?? $pagination['total']) }}</span>
                    <span class="text-sm font-bold text-gray-300">台がヒット</span>
                </div>
            </div>
        </div>

        {{-- KPIブロック --}}
        @if($featureKpi['total_count'] ?? $pagination['total'] > 0)
        <div class="bg-white border-b border-gray-100">
            <div class="max-w-7xl mx-auto px-4 py-6">
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="bg-gray-50 rounded-2xl p-4 text-center">
                        <div class="text-xs font-bold text-gray-400 mb-1">掲載台数</div>
                        <div class="text-2xl font-black text-gray-900">{{ number_format($featureKpi['total_count'] ?? $pagination['total']) }}<span class="text-sm font-bold text-gray-400 ml-1">台</span></div>
                    </div>
                    <div class="bg-gray-50 rounded-2xl p-4 text-center">
                        <div class="text-xs font-bold text-gray-400 mb-1">平均価格</div>
                        <div class="text-2xl font-black text-blue-600">{{ $featureKpi['avg_price'] ?? '-' }}<span class="text-sm font-bold text-gray-400 ml-1">万円</span></div>
                    </div>
                    <div class="bg-gray-50 rounded-2xl p-4 text-center">
                        <div class="text-xs font-bold text-gray-400 mb-1">最安値</div>
                        <div class="text-2xl font-black text-green-600">{{ $featureKpi['min_price'] ?? '-' }}<span class="text-sm font-bold text-gray-400 ml-1">万円</span></div>
                    </div>
                    <div class="bg-gray-50 rounded-2xl p-4 text-center">
                        <div class="text-xs font-bold text-gray-400 mb-1">人気No.1</div>
                        <div class="text-lg font-black text-gray-900 truncate">{{ $featureKpi['top_model'] ?? '-' }}</div>
                    </div>
                </div>
            </div>
        </div>
        @endif

        {{-- データインサイトブロック（TOP3車種） --}}
        @if(!empty($featureKpi['top_models']))
        <div class="bg-white border-b border-gray-100">
            <div class="max-w-7xl mx-auto px-4 py-6">
                <h2 class="text-base font-black text-gray-900 mb-4 flex items-center gap-2">
                    <i data-lucide="trophy" class="w-5 h-5 text-yellow-500"></i>
                    この特集で人気の車種 TOP3
                </h2>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    @foreach($featureKpi['top_models'] as $i => $model)
                    <div class="flex items-center gap-4 bg-gray-50 rounded-2xl p-4">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0 font-black text-white {{ $i === 0 ? 'bg-yellow-500' : ($i === 1 ? 'bg-gray-400' : 'bg-amber-700') }}">
                            {{ $i + 1 }}
                        </div>
                        <div class="min-w-0">
                            <div class="font-black text-gray-900 text-sm truncate">{{ $model['name'] }}</div>
                            <div class="text-xs text-gray-500 font-bold">
                                {{ $model['count'] }}台掲載
                                @if($model['avg_price'])
                                    / 平均{{ $model['avg_price'] }}万円
                                @endif
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif

        {{-- 選び方ガイドブロック --}}
        @if($feature->guide_content)
        <div class="bg-blue-50 border-b border-blue-100">
            <div class="max-w-7xl mx-auto px-4 py-8">
                <div class="bg-white rounded-2xl border border-gray-100 p-6 sm:p-8 space-y-6">
                    <h2 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                        <i data-lucide="book-open" class="w-5 h-5 text-blue-500"></i>
                        選び方ガイド
                    </h2>
                    <div class="guide-content text-gray-700 leading-relaxed">
                        {!! $feature->guide_content !!}
                    </div>
                </div>
            </div>
        </div>
        <style>
        .guide-content h3 {
            font-size: 1.25rem;
            font-weight: 700;
            color: #111827;
            margin-top: 2rem;
            margin-bottom: 0.75rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #3b82f6;
        }
        .guide-content p {
            margin-bottom: 1rem;
        }
        .guide-content ul {
            list-style: disc;
            padding-left: 1.5rem;
            margin-bottom: 1rem;
        }
        .guide-content li {
            margin-bottom: 0.5rem;
        }
        .guide-content strong {
            color: #1e40af;
        }
        .guide-content a {
            color: #2563eb;
            text-decoration: underline;
        }
        </style>
        @endif

        {{-- メインコンテンツ --}}
        <div class="py-8">
            <div class="max-w-7xl mx-auto px-4">

                {{-- ソート & 件数 --}}
                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-baseline gap-2">
                        <span class="text-2xl font-black text-black">{{ number_format($featureKpi['total_count'] ?? $pagination['total']) }}</span>
                        <span class="text-sm font-bold text-gray-500">台の車両がヒットしました</span>
                    </div>

                    {{-- ソート切替 --}}
                    <div class="relative" x-data="{ open: false }">
                        <button @click="open = !open" class="flex items-center gap-2 bg-white border border-gray-200 rounded-xl px-4 py-2.5 text-sm font-bold text-gray-700 hover:border-gray-400 transition shadow-sm">
                            <i data-lucide="arrow-up-down" class="w-4 h-4 text-gray-400"></i>
                            <span>{{ $sortOptions[$sort] ?? '新着順' }}</span>
                            <i data-lucide="chevron-down" class="w-3.5 h-3.5 text-gray-400"></i>
                        </button>
                        <div x-show="open" @click.outside="open = false" x-transition
                             class="absolute right-0 mt-2 w-48 bg-white rounded-xl shadow-xl border border-gray-100 py-2 z-50">
                            @foreach($sortOptions as $key => $label)
                                <a href="?sort={{ $key }}"
                                   class="block px-4 py-2 text-sm font-bold hover:bg-gray-50 transition {{ $sort === $key ? 'text-blue-600 bg-blue-50' : 'text-gray-600' }}">
                                    {{ $label }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- 車両カードグリッド --}}
                <div id="results-grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    @forelse ($items as $listing)
                        @include('bikes.partials.bike_card', ['listing' => $listing, 'isFirstView' => $loop->index < 4])
                    @empty
                        <div class="col-span-full py-20 text-center bg-white rounded-3xl border border-gray-100 shadow-sm">
                            <i data-lucide="search-x" class="w-16 h-16 text-gray-200 mx-auto mb-4"></i>
                            <h3 class="text-lg font-black text-gray-800 mb-2">現在、この条件の車両は在庫切れです</h3>
                            <p class="text-gray-400 font-bold text-sm mb-6">条件を変更するか、時間をおいて再度お試しください。</p>
                            <a href="{{ route('bikes.search') }}" class="inline-flex items-center gap-2 bg-blue-600 text-white px-6 py-3 rounded-xl font-bold text-sm hover:bg-blue-700 transition-colors shadow-md">
                                <i data-lucide="search" class="w-4 h-4"></i> 全車両を検索する
                            </a>
                        </div>
                    @endforelse
                </div>

                {{-- ページネーション --}}
                @if($pagination['last_page'] > 1)
                <div class="mt-16 flex justify-center">
                    <div class="flex items-center gap-2">
                        @if($pagination['prev_url'])
                            <a href="{{ $pagination['prev_url'] }}" class="w-10 h-10 flex items-center justify-center rounded-lg bg-white border border-gray-200 hover:border-black transition"><i data-lucide="chevron-left" class="w-4 h-4"></i></a>
                        @endif
                        @if(isset($pagination['pages']))
                            @foreach($pagination['pages'] as $page)
                                @if($page['is_dot'])
                                    <span class="px-1 text-gray-300">...</span>
                                @else
                                    <a href="{{ $page['url'] }}" class="w-10 h-10 flex items-center justify-center rounded-lg font-black text-sm transition {{ $page['is_active'] ? 'bg-black text-white shadow-lg' : 'bg-white border border-gray-200 text-gray-400 hover:border-black' }}">
                                        {{ $page['label'] }}
                                    </a>
                                @endif
                            @endforeach
                        @endif
                        @if($pagination['next_url'])
                            <a href="{{ $pagination['next_url'] }}" class="w-10 h-10 flex items-center justify-center rounded-lg bg-white border border-gray-200 hover:border-black transition"><i data-lucide="chevron-right" class="w-4 h-4"></i></a>
                        @endif
                    </div>
                </div>
                @endif

                {{-- 関連する特集ページ (SEO内部リンク) --}}
                @if($relatedFeatures->isNotEmpty())
                <div class="mt-20 pt-10 border-t border-gray-200">
                    <h2 class="text-lg font-black text-gray-900 mb-6 flex items-center gap-2">
                        <i data-lucide="bookmark" class="w-5 h-5 text-blue-500"></i>
                        その他の特集ページ
                    </h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach($relatedFeatures as $related)
                            <a href="{{ $related->url }}" class="group bg-white rounded-2xl p-5 border border-gray-100 hover:border-blue-300 hover:shadow-lg transition-all duration-300">
                                <div class="flex items-center gap-3">
                                    @if($related->icon)
                                    <div class="w-10 h-10 {{ $related->color ?? 'bg-gray-400' }} rounded-xl flex items-center justify-center flex-shrink-0 shadow-sm">
                                        <i data-lucide="{{ $related->icon }}" class="w-5 h-5 text-white"></i>
                                    </div>
                                    @endif
                                    <div class="min-w-0">
                                        <h3 class="text-sm font-black text-gray-800 group-hover:text-blue-600 transition-colors leading-snug">{{ $related->title }}</h3>
                                        @if($related->meta_description)
                                        <p class="text-xs text-gray-400 font-bold mt-1 line-clamp-1">{{ $related->meta_description }}</p>
                                        @endif
                                    </div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
                @endif

            </div>
        </div>
    </div>
</x-layout>
