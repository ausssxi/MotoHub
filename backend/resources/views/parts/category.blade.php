<x-layout>
    <x-slot:title>バイク用{{ $category['name'] }}の価格比較 | 楽天・Yahoo・Amazon最安値 - MotoHub</x-slot:title>
    <x-slot:metaDescription>{{ $category['description'] }}</x-slot:metaDescription>
    <x-slot:canonical>{{ route('parts.category', $category['slug']) }}</x-slot:canonical>

    <x-slot:styles>
        {{-- BreadcrumbList JSON-LD --}}
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
            $faqLd = [
                '@context' => 'https://schema.org',
                '@type' => 'FAQPage',
                'mainEntity' => [
                    [
                        '@type' => 'Question',
                        'name' => 'バイク用' . $category['name'] . 'の相場はいくら？',
                        'acceptedAnswer' => [
                            '@type' => 'Answer',
                            'text' => 'バイク用' . $category['name'] . 'の価格は商品やブランドにより幅がありますが、MotoHubでは楽天・Yahoo・Amazonの価格を一括比較して最安値を見つけることができます。',
                        ],
                    ],
                    [
                        '@type' => 'Question',
                        'name' => $category['name'] . 'はどこで買うのが安い？',
                        'acceptedAnswer' => [
                            '@type' => 'Answer',
                            'text' => '楽天市場・Yahoo!ショッピング・Amazonの3サイトで価格が異なります。MotoHubのパーツ検索で横断比較すれば、最安値のショップを簡単に見つけられます。ポイント還元率も含めて検討するのがおすすめです。',
                        ],
                    ],
                ],
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

            {{-- 人気商品 --}}
            @if(count($items) > 0)
            <section class="mb-8">
                <h2 class="text-lg font-black text-gray-800 mb-4">{{ $category['name'] }}の人気商品</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($items as $item)
                    <div class="bg-white rounded-xl shadow-sm hover:shadow-lg transition-shadow overflow-hidden flex flex-col h-full border border-gray-100">
                        <div class="aspect-square bg-gray-100 flex items-center justify-center overflow-hidden">
                            @if(!empty($item['image']))
                                <img src="{{ str_replace('?_ex=128x128', '?_ex=300x300', $item['image']) }}" alt="{{ $item['name'] }}" class="w-full h-full object-contain p-2" loading="lazy">
                            @else
                                <div class="text-gray-300 text-4xl">🔧</div>
                            @endif
                        </div>
                        <div class="p-4 flex flex-col flex-grow">
                            <h3 class="text-sm font-bold text-gray-800 line-clamp-2 mb-1">{{ $item['name'] }}</h3>
                            <p class="text-xs text-gray-500 mb-2">{{ $item['shop'] }}</p>
                            <div class="mt-auto">
                                <p class="text-lg font-black text-red-600 mb-2">&yen;{{ number_format($item['price']) }}</p>
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
                <div class="mt-4 text-center">
                    <a href="{{ route('parts.index', ['keyword' => $category['name']]) }}"
                       class="inline-flex items-center gap-1 text-sm font-bold text-blue-600 hover:text-blue-800 transition-colors">
                        「{{ $category['name'] }}」をもっと検索する
                        <i data-lucide="arrow-right" class="w-4 h-4"></i>
                    </a>
                </div>
            </section>
            @endif

            {{-- FAQ --}}
            <section class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-8">
                <h2 class="text-lg font-black text-gray-800 mb-4">よくある質問</h2>
                <div class="space-y-4">
                    <details class="group" open>
                        <summary class="flex items-center justify-between cursor-pointer text-sm font-bold text-gray-800 py-2">
                            <span>Q. バイク用{{ $category['name'] }}の相場はいくら？</span>
                            <i data-lucide="chevron-down" class="w-4 h-4 text-gray-400 group-open:rotate-180 transition-transform"></i>
                        </summary>
                        <p class="text-sm text-gray-600 leading-relaxed pb-2 pl-4">
                            バイク用{{ $category['name'] }}の価格は商品やブランドにより幅がありますが、MotoHubでは楽天・Yahoo・Amazonの価格を一括比較して最安値を見つけることができます。
                        </p>
                    </details>
                    <details class="group">
                        <summary class="flex items-center justify-between cursor-pointer text-sm font-bold text-gray-800 py-2 border-t border-gray-100 pt-4">
                            <span>Q. {{ $category['name'] }}はどこで買うのが安い？</span>
                            <i data-lucide="chevron-down" class="w-4 h-4 text-gray-400 group-open:rotate-180 transition-transform"></i>
                        </summary>
                        <p class="text-sm text-gray-600 leading-relaxed pb-2 pl-4">
                            楽天市場・Yahoo!ショッピング・Amazonの3サイトで価格が異なります。MotoHubのパーツ検索で横断比較すれば、最安値のショップを簡単に見つけられます。ポイント還元率も含めて検討するのがおすすめです。
                        </p>
                    </details>
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
</x-layout>
