<x-layout>
    <x-slot:title>バイク用{{ $category['name'] }}の価格比較 | 楽天・Yahoo・Amazon最安値 - MotoHub</x-slot:title>
    <x-slot:metaDescription>{{ $category['description'] }}</x-slot:metaDescription>
    <x-slot:canonical>{{ route('parts.category', $category['slug']) }}</x-slot:canonical>

    <x-slot:styles>
        @php
            $breadcrumbLd = [
                '@context' => 'https://schema.org',
                '@type' => 'BreadcrumbList',
                'itemListElement' => [
                    ['@type' => 'ListItem', 'position' => 1, 'name' => 'ホーム', 'item' => url('/')],
                    ['@type' => 'ListItem', 'position' => 2, 'name' => 'パーツ検索', 'item' => route('parts.index')],
                    ['@type' => 'ListItem', 'position' => 3, 'name' => $category['name']],
                ],
            ];

            $faqEntries = [
                [
                    'q' => 'バイク用' . $category['name'] . 'の相場はいくら？',
                    'a' => 'バイク用' . $category['name'] . 'の価格帯は' . number_format($category['price_range']['min']) . '円〜' . number_format($category['price_range']['max']) . '円で、平均は約' . number_format($category['price_range']['average']) . '円です。MotoHubでは楽天・Yahoo・Amazonの価格を一括比較して最安値を見つけることができます。',
                ],
                [
                    'q' => $category['name'] . 'はどこで買うのが安い？',
                    'a' => '楽天市場・Yahoo!ショッピング・Amazonの3サイトで価格が異なります。MotoHubのパーツ検索で横断比較すれば、最安値のショップを簡単に見つけられます。ポイント還元率も含めて検討するのがおすすめです。',
                ],
                [
                    'q' => $category['name'] . 'の交換時期・寿命は？',
                    'a' => $category['replacement_guide'],
                ],
                [
                    'q' => $category['name'] . 'のおすすめブランドは？',
                    'a' => '人気ブランドは' . implode('、', array_column($category['brands'], 'name')) . 'など。' . $category['brands'][0]['name'] . 'は' . $category['brands'][0]['description'] . '。用途や予算に合わせて選びましょう。',
                ],
                [
                    'q' => $category['name'] . 'を安く買うコツは？',
                    'a' => $category['point_tips'],
                ],
            ];

            $faqLd = [
                '@context' => 'https://schema.org',
                '@type' => 'FAQPage',
                'mainEntity' => array_map(function ($entry) {
                    return [
                        '@type' => 'Question',
                        'name' => $entry['q'],
                        'acceptedAnswer' => [
                            '@type' => 'Answer',
                            'text' => $entry['a'],
                        ],
                    ];
                }, $faqEntries),
            ];
        @endphp
        <script type="application/ld+json">{!! json_encode($breadcrumbLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) !!}</script>
        <script type="application/ld+json">{!! json_encode($faqLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) !!}</script>
    </x-slot:styles>

    <x-slot:navigation>
        <x-navigation :showSearch="true" />
    </x-slot:navigation>

    <div class="bg-gray-50 min-h-screen">
        {{-- ヘッダー --}}
        <section class="bg-gradient-to-r from-gray-900 to-gray-800 text-white py-10">
            <div class="max-w-5xl mx-auto px-4 text-center">
                <h1 class="text-2xl sm:text-3xl font-black mb-2">バイク用{{ $category['name'] }}の価格比較</h1>
                <p class="text-gray-300 text-sm">楽天・Yahoo!・Amazonから最安値を検索</p>
            </div>
        </section>

        <div class="max-w-5xl mx-auto px-4 py-8">
            {{-- パンくず --}}
            <nav class="text-xs font-bold text-gray-400 mb-6" aria-label="Breadcrumb">
                <ol class="flex items-center space-x-2">
                    <li><a href="/" class="hover:text-gray-600 transition-colors">ホーム</a></li>
                    <li><span class="text-gray-300">></span></li>
                    <li><a href="{{ route('parts.index') }}" class="hover:text-gray-600 transition-colors">パーツ検索</a></li>
                    <li><span class="text-gray-300">></span></li>
                    <li><span class="text-gray-800">{{ $category['name'] }}</span></li>
                </ol>
            </nav>

            {{-- カテゴリ説明 --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-8">
                <p class="text-sm text-gray-700 leading-relaxed">{{ $category['description'] }}</p>
                <div class="flex flex-wrap gap-2 mt-4">
                    @foreach($category['keywords'] as $kw)
                        <a href="{{ route('parts.index', ['keyword' => $kw]) }}"
                           class="inline-flex items-center px-3 py-1.5 bg-gray-100 hover:bg-blue-50 text-gray-600 hover:text-blue-600 text-xs font-bold rounded-full border border-gray-200 hover:border-blue-200 transition-colors">
                            {{ $kw }}
                        </a>
                    @endforeach
                </div>
            </div>

            {{-- 検索フォーム --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-8">
                <h2 class="text-sm font-black text-gray-800 mb-3">{{ $category['name'] }}をキーワードで検索</h2>
                <form action="{{ route('parts.index') }}" method="GET" class="flex gap-3">
                    <input type="text" name="keyword" value="{{ $category['name'] }}" placeholder="キーワードを入力"
                           class="flex-1 border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                    <button type="submit"
                            class="bg-blue-600 hover:bg-blue-700 text-white font-bold text-sm px-6 py-2.5 rounded-lg transition-colors shrink-0">
                        検索
                    </button>
                </form>
            </div>

            {{-- 選び方ガイド --}}
            <section class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-8">
                <h2 class="text-lg font-black text-gray-800 mb-4">{{ $category['guide']['title'] }}</h2>
                <div class="space-y-3">
                    @foreach($category['guide']['sections'] as $idx => $section)
                    <details class="group border border-gray-100 rounded-lg"{{ $idx === 0 ? ' open' : '' }}>
                        <summary class="flex items-center justify-between cursor-pointer px-4 py-3 text-sm font-bold text-gray-800 hover:bg-gray-50 rounded-lg transition-colors">
                            <span>{{ $section['heading'] }}</span>
                            <i data-lucide="chevron-down" class="w-4 h-4 text-gray-400 group-open:rotate-180 transition-transform shrink-0 ml-2"></i>
                        </summary>
                        <p class="text-sm text-gray-600 leading-relaxed px-4 pb-4">{{ $section['text'] }}</p>
                    </details>
                    @endforeach
                </div>
            </section>

            {{-- 価格帯 --}}
            <section class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-8">
                <h2 class="text-lg font-black text-gray-800 mb-4">{{ $category['name'] }}の価格帯</h2>
                <div class="flex items-center justify-between text-center gap-4">
                    <div class="flex-1">
                        <p class="text-xs text-gray-500 mb-1">最安値帯</p>
                        <p class="text-lg font-black text-blue-600">&yen;{{ number_format($category['price_range']['min']) }}〜</p>
                    </div>
                    <div class="flex-1 bg-blue-50 rounded-xl py-3">
                        <p class="text-xs text-gray-500 mb-1">平均価格</p>
                        <p class="text-xl font-black text-blue-700">&yen;{{ number_format($category['price_range']['average']) }}</p>
                    </div>
                    <div class="flex-1">
                        <p class="text-xs text-gray-500 mb-1">高価格帯</p>
                        <p class="text-lg font-black text-gray-600">〜&yen;{{ number_format($category['price_range']['max']) }}</p>
                    </div>
                </div>
                <div class="mt-4">
                    <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                        <div class="h-full bg-gradient-to-r from-blue-400 to-blue-600 rounded-full" style="width: 100%"></div>
                    </div>
                    <div class="flex justify-between text-xs text-gray-400 mt-1">
                        <span>&yen;{{ number_format($category['price_range']['min']) }}</span>
                        <span>&yen;{{ number_format($category['price_range']['max']) }}</span>
                    </div>
                </div>
            </section>

            {{-- 人気商品（サイドバー付き） --}}
            @if(count($items) > 0)
            <section class="mb-8">
                <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
                    <h2 class="text-lg font-black text-gray-800">{{ $category['name'] }}の人気商品</h2>
                    <div class="flex items-center gap-3">
                        <button id="cat-filter-toggle" type="button"
                            class="lg:hidden inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-gray-200 rounded-lg text-sm font-bold text-gray-700 hover:bg-gray-50 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                            絞り込み
                        </button>
                        <select id="cat-sort" class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none bg-white">
                            <option value="default">おすすめ順</option>
                            <option value="price-asc">価格が安い順</option>
                            <option value="price-desc">価格が高い順</option>
                            <option value="review">レビュー評価順</option>
                        </select>
                    </div>
                </div>

                <div class="lg:flex lg:gap-6">
                    {{-- サイドバー --}}
                    <aside id="cat-filter-sidebar" class="hidden lg:block lg:w-[200px] lg:shrink-0 mb-4 lg:mb-0">
                        <div class="bg-white rounded-xl border border-gray-100 p-4 lg:sticky lg:top-4">
                            <h3 class="text-sm font-black text-gray-800 mb-3">絞り込み</h3>
                            <div class="mb-4">
                                <h4 class="text-xs font-bold text-gray-600 mb-2">価格帯</h4>
                                <div class="space-y-1.5">
                                    <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                                        <input type="checkbox" class="cat-filter-price rounded border-gray-300" value="0-3000"> 〜3,000円
                                    </label>
                                    <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                                        <input type="checkbox" class="cat-filter-price rounded border-gray-300" value="3000-10000"> 3,000〜10,000円
                                    </label>
                                    <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                                        <input type="checkbox" class="cat-filter-price rounded border-gray-300" value="10000-30000"> 10,000〜30,000円
                                    </label>
                                    <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                                        <input type="checkbox" class="cat-filter-price rounded border-gray-300" value="30000-50000"> 30,000〜50,000円
                                    </label>
                                    <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                                        <input type="checkbox" class="cat-filter-price rounded border-gray-300" value="50000-"> 50,000円〜
                                    </label>
                                </div>
                            </div>
                            <div class="mb-4 border-t border-gray-100 pt-4">
                                <h4 class="text-xs font-bold text-gray-600 mb-2">送料</h4>
                                <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                                    <input type="checkbox" id="cat-filter-shipping" class="rounded border-gray-300"> 送料無料のみ
                                </label>
                            </div>
                            <div class="border-t border-gray-100 pt-3">
                                <button type="button" id="cat-filter-reset" class="text-xs font-bold text-blue-600 hover:text-blue-800 transition-colors">条件をリセット</button>
                            </div>
                        </div>
                    </aside>

                    {{-- 商品グリッド --}}
                    <div class="flex-1 min-w-0">
                        <div id="cat-product-grid" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            @foreach($items as $item)
                            <div class="product-card bg-white rounded-xl shadow-sm hover:shadow-lg transition-shadow overflow-hidden flex flex-col h-full border border-gray-100"
                                 data-price="{{ $item['price'] }}" data-postage="{{ $item['postage_flag'] ?? 0 }}" data-review="{{ $item['review_avg'] ?? 0 }}">
                                <div class="aspect-square bg-gray-100 flex items-center justify-center overflow-hidden">
                                    @if(!empty($item['image']))
                                        <img src="{{ str_replace('?_ex=128x128', '?_ex=300x300', $item['image']) }}" alt="{{ $item['name'] }}" class="w-full h-full object-contain p-2" loading="lazy">
                                    @else
                                        <div class="text-gray-300 text-4xl">&#128295;</div>
                                    @endif
                                </div>
                                <div class="p-4 flex flex-col flex-grow">
                                    <h3 class="text-sm font-bold text-gray-800 line-clamp-2 mb-1">{{ $item['name'] }}</h3>
                                    @if(!empty($item['caption']))
                                    <p class="text-xs text-gray-500 line-clamp-2 mb-1">{{ $item['caption'] }}</p>
                                    @endif
                                    <p class="text-xs text-gray-500 mb-2">{{ $item['shop'] }}</p>
                                    <div class="mt-auto">
                                        <div class="flex items-center gap-2 mb-2">
                                            <p class="text-lg font-black text-red-600">&yen;{{ number_format($item['price']) }}</p>
                                            @if(($item['postage_flag'] ?? 0) == 1)
                                            <span class="inline-block px-1.5 py-0.5 bg-green-100 text-green-700 text-[10px] font-bold rounded">送料無料</span>
                                            @endif
                                        </div>
                                        @if($item['review_count'] > 0)
                                        @php $stars = (int) round($item['review_avg']); @endphp
                                        <div class="flex items-center gap-1 text-xs mb-3">
                                            @foreach(range(1, 5) as $s)
                                                <span class="{{ $s <= $stars ? 'text-yellow-400' : 'text-gray-300' }}">&#9733;</span>
                                            @endforeach
                                            <span class="text-gray-500 ml-1">({{ $item['review_count'] }}件)</span>
                                        </div>
                                        @endif
                                        <a href="{{ $item['url'] }}" target="_blank" rel="noopener noreferrer"
                                           class="block text-center bg-red-500 hover:bg-red-600 text-white text-xs font-bold py-2.5 rounded-lg transition-colors">
                                            楽天市場で見る
                                        </a>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="mt-4 text-center">
                    <a href="{{ route('parts.index', ['keyword' => $category['name']]) }}"
                       class="inline-flex items-center gap-1 text-sm font-bold text-blue-600 hover:text-blue-800 transition-colors">
                        「{{ $category['name'] }}」をもっと検索する
                        <i data-lucide="arrow-right" class="w-4 h-4"></i>
                    </a>
                </div>
            </section>
            @endif

            {{-- 車種別にパーツを探す --}}
            <section class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-8">
                @php
                    $allPopularBikes = array_values(array_unique(array_merge(
                        $category['popular_bikes'] ?? [],
                        ['CBR250RR', 'PCX', 'レブル250', 'スーパーカブ', 'モンキー125', 'CT125',
                         'YZF-R25', 'MT-07', 'セロー250', 'Ninja250', 'Z900RS', 'Ninja400',
                         'GSX-R125', 'Vストローム250', 'アドレスV125', 'CB400SF', 'W800', 'ZX-25R',
                         'TMAX', 'ジクサー']
                    )));
                    $allPopularBikes = array_slice($allPopularBikes, 0, 20);
                @endphp
                <h3 class="text-lg font-black text-gray-800 mb-4">車種別に{{ $category['name'] }}を探す</h3>
                <div class="flex flex-wrap gap-2">
                    @foreach($allPopularBikes as $bike)
                        <a href="{{ route('parts.index', ['keyword' => $bike . ' ' . $category['name']]) }}"
                           class="inline-flex items-center px-3 py-1.5 bg-gray-100 hover:bg-blue-50 text-gray-700 hover:text-blue-600 text-sm font-bold rounded-full border border-gray-200 hover:border-blue-200 transition-colors">
                            {{ $bike }}
                        </a>
                    @endforeach
                </div>
            </section>

            {{-- ブランドからパーツを探す --}}
            <section class="mb-8">
                @php
                    $categoryBrandNames = array_column($category['brands'], 'name');
                    $genericBrands = ['ヨシムラ', 'デイトナ', 'キタコ', 'モリワキ', 'コミネ', 'RSタイチ', 'ショウエイ', 'アライ'];
                    $allBrandNames = array_values(array_unique(array_merge($categoryBrandNames, $genericBrands)));
                @endphp
                <h3 class="text-lg font-black text-gray-800 mb-4">ブランドから{{ $category['name'] }}を探す</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    @foreach($category['brands'] as $brand)
                    <a href="{{ route('parts.index', ['keyword' => $brand['name'] . ' ' . $category['name']]) }}"
                       class="bg-white rounded-xl border border-gray-100 p-4 hover:shadow-md hover:border-blue-200 transition-all group">
                        <p class="text-sm font-black text-gray-800 group-hover:text-blue-600 transition-colors">{{ $brand['name'] }}</p>
                        <p class="text-xs text-gray-500 mt-1">{{ $brand['description'] }}</p>
                    </a>
                    @endforeach
                </div>
                @php
                    $extraBrands = array_diff($allBrandNames, $categoryBrandNames);
                @endphp
                @if(count($extraBrands) > 0)
                <div class="flex flex-wrap gap-2 mt-3">
                    @foreach($extraBrands as $brand)
                    <a href="{{ route('parts.index', ['keyword' => $brand . ' ' . $category['name']]) }}"
                       class="inline-flex items-center px-3 py-1.5 bg-gray-100 hover:bg-blue-50 text-gray-600 hover:text-blue-600 text-xs font-bold rounded-full border border-gray-200 hover:border-blue-200 transition-colors">
                        {{ $brand }}
                    </a>
                    @endforeach
                </div>
                @endif
            </section>

            {{-- ポイント還元のコツ --}}
            <section class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-8">
                <h2 class="text-lg font-black text-gray-800 mb-4">{{ $category['name'] }}をお得に買うコツ</h2>
                <p class="text-sm text-gray-700 leading-relaxed mb-4">{{ $category['point_tips'] }}</p>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm border-collapse">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="text-left px-4 py-2.5 font-bold text-gray-700 border-b border-gray-200">サイト</th>
                                <th class="text-left px-4 py-2.5 font-bold text-gray-700 border-b border-gray-200">ポイント還元</th>
                                <th class="text-left px-4 py-2.5 font-bold text-gray-700 border-b border-gray-200">お得なタイミング</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="border-b border-gray-100">
                                <td class="px-4 py-2.5 font-bold text-red-600">楽天市場</td>
                                <td class="px-4 py-2.5 text-gray-600">最大16.5倍（SPU）</td>
                                <td class="px-4 py-2.5 text-gray-600">お買い物マラソン・スーパーSALE</td>
                            </tr>
                            <tr class="border-b border-gray-100 bg-gray-50/50">
                                <td class="px-4 py-2.5 font-bold text-red-500">Yahoo!</td>
                                <td class="px-4 py-2.5 text-gray-600">最大5%（PayPay）</td>
                                <td class="px-4 py-2.5 text-gray-600">5のつく日・日曜日</td>
                            </tr>
                            <tr>
                                <td class="px-4 py-2.5 font-bold text-yellow-600">Amazon</td>
                                <td class="px-4 py-2.5 text-gray-600">1%〜（ポイント対象品）</td>
                                <td class="px-4 py-2.5 text-gray-600">プライムデー・タイムセール祭り</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            {{-- 交換時期ガイド --}}
            <section class="bg-blue-50 rounded-2xl border border-blue-100 p-6 mb-8">
                <div class="flex items-start gap-3">
                    <div class="shrink-0 w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                        <i data-lucide="wrench" class="w-5 h-5 text-blue-600"></i>
                    </div>
                    <div>
                        <h2 class="text-sm font-black text-gray-800 mb-2">{{ $category['name'] }}の交換時期・メンテナンス</h2>
                        <p class="text-sm text-gray-700 leading-relaxed">{{ $category['replacement_guide'] }}</p>
                    </div>
                </div>
            </section>

            {{-- FAQ --}}
            <section class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-8">
                <h2 class="text-lg font-black text-gray-800 mb-4">よくある質問</h2>
                <div class="space-y-0">
                    @foreach($faqEntries as $fIdx => $faq)
                    <details class="group"{{ $fIdx === 0 ? ' open' : '' }}>
                        <summary class="flex items-center justify-between cursor-pointer text-sm font-bold text-gray-800 py-3{{ $fIdx > 0 ? ' border-t border-gray-100' : '' }}">
                            <span>Q. {{ $faq['q'] }}</span>
                            <i data-lucide="chevron-down" class="w-4 h-4 text-gray-400 group-open:rotate-180 transition-transform shrink-0 ml-2"></i>
                        </summary>
                        <p class="text-sm text-gray-600 leading-relaxed pb-3 pl-4">{{ $faq['a'] }}</p>
                    </details>
                    @endforeach
                </div>
            </section>

            {{-- 関連カテゴリ --}}
            <section class="mb-8">
                <h2 class="text-lg font-black text-gray-800 mb-4">他のパーツカテゴリ</h2>
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                    @foreach($otherCategories as $other)
                    <a href="{{ route('parts.category', $other['slug']) }}"
                       class="bg-white rounded-xl border border-gray-100 p-4 hover:shadow-md hover:border-blue-200 transition-all text-center group">
                        <span class="text-sm font-black text-gray-800 group-hover:text-blue-600 transition-colors">{{ $other['name'] }}</span>
                    </a>
                    @endforeach
                </div>
            </section>
        </div>
    </div>

    <x-slot:scripts>
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const grid = document.getElementById('cat-product-grid');
        if (!grid) return;

        const toggleBtn = document.getElementById('cat-filter-toggle');
        const sidebar = document.getElementById('cat-filter-sidebar');
        const sortSelect = document.getElementById('cat-sort');
        const freeShipping = document.getElementById('cat-filter-shipping');
        const resetBtn = document.getElementById('cat-filter-reset');

        if (toggleBtn && sidebar) {
            toggleBtn.addEventListener('click', () => sidebar.classList.toggle('hidden'));
        }

        function applyFilters() {
            const checkedPrices = Array.from(document.querySelectorAll('.cat-filter-price:checked')).map(cb => cb.value);
            const freeOnly = freeShipping && freeShipping.checked;
            const sort = sortSelect ? sortSelect.value : 'default';
            const cards = Array.from(grid.querySelectorAll('.product-card'));

            cards.forEach(card => {
                const price = parseInt(card.dataset.price) || 0;
                const postage = parseInt(card.dataset.postage) || 0;
                let show = true;

                if (checkedPrices.length > 0) {
                    show = checkedPrices.some(range => {
                        const [minStr, maxStr] = range.split('-');
                        const min = parseInt(minStr) || 0;
                        const max = maxStr ? parseInt(maxStr) : Infinity;
                        return price >= min && price <= max;
                    });
                }
                if (show && freeOnly && postage !== 1) show = false;

                card.style.display = show ? '' : 'none';
            });

            if (sort !== 'default') {
                const allCards = Array.from(grid.querySelectorAll('.product-card'));
                allCards.sort((a, b) => {
                    if (sort === 'price-asc') return (parseInt(a.dataset.price) || 0) - (parseInt(b.dataset.price) || 0);
                    if (sort === 'price-desc') return (parseInt(b.dataset.price) || 0) - (parseInt(a.dataset.price) || 0);
                    if (sort === 'review') return (parseFloat(b.dataset.review) || 0) - (parseFloat(a.dataset.review) || 0);
                    return 0;
                });
                allCards.forEach(card => grid.appendChild(card));
            }
        }

        document.querySelectorAll('.cat-filter-price').forEach(cb => cb.addEventListener('change', applyFilters));
        if (freeShipping) freeShipping.addEventListener('change', applyFilters);
        if (sortSelect) sortSelect.addEventListener('change', applyFilters);
        if (resetBtn) {
            resetBtn.addEventListener('click', () => {
                document.querySelectorAll('.cat-filter-price').forEach(cb => cb.checked = false);
                if (freeShipping) freeShipping.checked = false;
                if (sortSelect) sortSelect.value = 'default';
                applyFilters();
            });
        }
    });
    </script>
    </x-slot:scripts>
</x-layout>
