<x-layout>
    <x-slot:title>絶版バイク・生産終了バイク一覧 | 中古相場と在庫【{{ date('Y') }}年最新】- MotoHub</x-slot:title>
    <x-slot:metaDescription>SR400、RZ250、NSR250Rなど生産終了した絶版バイクを一覧表示。現在の中古相場・在庫数をリアルタイムで確認できます。</x-slot:metaDescription>
    <x-slot:navigation><x-navigation :showSearch="true" /></x-slot:navigation>

    <x-slot:styles>
        @php
            $itemListElements = [];
            foreach ($models->take(30) as $i => $model) {
                $itemListElements[] = [
                    '@type' => 'ListItem',
                    'position' => $i + 1 + (($models->currentPage() - 1) * 30),
                    'url' => url($model->seo_url),
                    'name' => $model->displayLabel(),
                ];
            }
            $jsonLd = [
                '@context' => 'https://schema.org',
                '@type' => 'ItemList',
                'name' => '絶版バイク・生産終了モデル一覧',
                'description' => '生産終了した絶版バイクの中古相場・在庫一覧',
                'numberOfItems' => $totalCount,
                'itemListElement' => $itemListElements,
            ];
        @endphp
        <script type="application/ld+json">{!! json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
    </x-slot:styles>

    <div class="max-w-6xl mx-auto px-4 py-6 sm:py-10">
        {{-- パンくず --}}
        <nav class="flex text-xs font-bold text-gray-400 mb-6">
            <ol class="flex items-center space-x-2">
                <li><a href="{{ route('bikes.index') }}" class="hover:text-black transition">HOME</a></li>
                <li><span class="text-gray-300">/</span></li>
                <li class="text-gray-600">絶版バイク</li>
            </ol>
        </nav>

        {{-- ヒーローセクション --}}
        <div class="bg-gradient-to-br from-gray-900 via-gray-800 to-gray-900 rounded-2xl p-8 sm:p-12 mb-8 text-white">
            <h1 class="text-2xl sm:text-4xl font-black tracking-tight mb-3">
                絶版バイク・生産終了モデル一覧
            </h1>
            <p class="text-gray-300 text-sm sm:text-base font-bold mb-6">
                もう新車では買えない名車たち。中古市場での相場・在庫をチェック
            </p>
            <div class="flex flex-wrap gap-4">
                <div class="bg-white/10 backdrop-blur-sm rounded-xl px-5 py-3">
                    <span class="text-[10px] text-gray-400 block">絶版モデル数</span>
                    <span class="text-2xl font-black">{{ number_format($totalCount) }}<span class="text-xs">車種</span></span>
                </div>
            </div>
        </div>

        {{-- 人気の絶版車ピックアップ --}}
        @if($pickup->isNotEmpty())
        <div class="mb-10">
            <h2 class="text-lg font-black text-gray-900 mb-4 flex items-center gap-2">
                <i data-lucide="flame" class="w-5 h-5 text-orange-500"></i>
                人気の絶版車ピックアップ
            </h2>
            <div class="flex gap-3 overflow-x-auto pb-2 -mx-4 px-4 snap-x snap-mandatory scrollbar-hide">
                @foreach($pickup as $model)
                @php
                    $ps = $pickupPriceStats[$model->id] ?? null;
                @endphp
                <a href="{{ url($model->seo_url) }}"
                   class="snap-start shrink-0 w-64 sm:w-72 group bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden hover:shadow-md transition">
                    <div class="h-48 bg-gray-100 overflow-hidden">
                        @if($model->image_url)
                            <img src="{{ $model->image_url }}" alt="{{ $model->displayLabel() }}"
                                 class="w-full h-full object-cover group-hover:scale-105 transition-transform" loading="lazy">
                        @else
                            <div class="w-full h-full flex items-center justify-center text-gray-300">
                                <i data-lucide="bike" class="w-10 h-10"></i>
                            </div>
                        @endif
                    </div>
                    <div class="p-3">
                        <p class="text-[10px] font-bold text-gray-400">{{ $model->manufacturer?->name }}</p>
                        <p class="text-sm font-black text-gray-900 line-clamp-1 group-hover:text-blue-600 transition">{{ $model->displayLabel() }}</p>
                        @if($model->production_end_year)
                            <span class="text-[10px] text-red-500 font-bold">{{ $model->production_end_year }}年終了</span>
                        @endif
                        @if($ps)
                            <div class="mt-1 flex items-baseline gap-1">
                                <span class="text-xs font-black text-gray-900">{{ $ps['min'] }}〜{{ $ps['max'] }}万円</span>
                                <span class="text-[10px] text-gray-400">({{ $ps['count'] }}台)</span>
                            </div>
                        @elseif($model->active_count > 0)
                            <p class="mt-1 text-[10px] text-gray-400">{{ $model->active_count }}台在庫</p>
                        @else
                            <p class="mt-1 text-[10px] text-gray-400">在庫なし</p>
                        @endif
                    </div>
                </a>
                @endforeach
            </div>
        </div>
        @endif

        {{-- フィルター --}}
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 sm:p-6 mb-6">
            <form method="GET" action="{{ route('bikes.discontinued') }}" class="space-y-4">
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    {{-- メーカー --}}
                    <div>
                        <label class="text-[10px] font-black text-gray-500 uppercase tracking-wider block mb-1">メーカー</label>
                        <select name="manufacturer" class="w-full text-sm border-gray-200 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                            <option value="">すべて</option>
                            @foreach($manufacturers as $mfr)
                                <option value="{{ $mfr->slug }}" {{ ($currentFilters['manufacturer'] ?? '') === $mfr->slug ? 'selected' : '' }}>
                                    {{ $mfr->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- 排気量帯 --}}
                    <div>
                        <label class="text-[10px] font-black text-gray-500 uppercase tracking-wider block mb-1">排気量</label>
                        <select name="displacement" class="w-full text-sm border-gray-200 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                            <option value="">すべて</option>
                            <option value="125" {{ ($currentFilters['displacement'] ?? '') === '125' ? 'selected' : '' }}>〜125cc</option>
                            <option value="250" {{ ($currentFilters['displacement'] ?? '') === '250' ? 'selected' : '' }}>126〜250cc</option>
                            <option value="400" {{ ($currentFilters['displacement'] ?? '') === '400' ? 'selected' : '' }}>251〜400cc</option>
                            <option value="401" {{ ($currentFilters['displacement'] ?? '') === '401' ? 'selected' : '' }}>401cc〜</option>
                        </select>
                    </div>

                    {{-- 生産終了年代 --}}
                    <div>
                        <label class="text-[10px] font-black text-gray-500 uppercase tracking-wider block mb-1">終了年代</label>
                        <select name="era" class="w-full text-sm border-gray-200 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                            <option value="">すべて</option>
                            <option value="2000" {{ ($currentFilters['era'] ?? '') === '2000' ? 'selected' : '' }}>〜2000年</option>
                            <option value="2010" {{ ($currentFilters['era'] ?? '') === '2010' ? 'selected' : '' }}>2001〜2010年</option>
                            <option value="2020" {{ ($currentFilters['era'] ?? '') === '2020' ? 'selected' : '' }}>2011〜2020年</option>
                        </select>
                    </div>

                    {{-- ソート --}}
                    <div>
                        <label class="text-[10px] font-black text-gray-500 uppercase tracking-wider block mb-1">並び順</label>
                        <select name="sort" class="w-full text-sm border-gray-200 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                            <option value="stock" {{ ($currentFilters['sort'] ?? 'stock') === 'stock' ? 'selected' : '' }}>在庫数順</option>
                            <option value="year" {{ ($currentFilters['sort'] ?? '') === 'year' ? 'selected' : '' }}>終了年順</option>
                            <option value="name" {{ ($currentFilters['sort'] ?? '') === 'name' ? 'selected' : '' }}>名前順</option>
                        </select>
                    </div>
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="px-6 py-2 bg-gray-900 text-white text-xs font-bold rounded-lg hover:bg-gray-700 transition">
                        絞り込む
                    </button>
                    @if(!empty(array_filter($currentFilters)))
                        <a href="{{ route('bikes.discontinued') }}" class="px-4 py-2 text-xs font-bold text-gray-500 hover:text-gray-900 transition">
                            リセット
                        </a>
                    @endif
                </div>
            </form>
        </div>

        {{-- 車種カード一覧 --}}
        @if($models->isNotEmpty())
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3 sm:gap-4 mb-8">
            @foreach($models as $model)
            @php
                $ps = $priceStats[$model->id] ?? null;
            @endphp
            <a href="{{ url($model->seo_url) }}"
               class="group bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden hover:shadow-md hover:border-gray-200 transition">
                {{-- 画像 --}}
                <div class="aspect-[4/3] bg-gray-100 overflow-hidden relative">
                    @if($model->image_url)
                        <img src="{{ $model->image_url }}" alt="{{ $model->displayLabel() }}"
                             class="w-full h-full object-cover group-hover:scale-105 transition-transform" loading="lazy">
                    @else
                        <div class="w-full h-full flex items-center justify-center text-gray-300">
                            <i data-lucide="bike" class="w-10 h-10"></i>
                        </div>
                    @endif
                    @if($model->production_end_year)
                        <span class="absolute top-2 left-2 bg-red-600 text-white text-[10px] font-bold px-2 py-0.5 rounded">
                            {{ $model->production_end_year }}年終了
                        </span>
                    @endif
                </div>

                {{-- 情報 --}}
                <div class="p-3">
                    <p class="text-[10px] font-bold text-gray-400">{{ $model->manufacturer?->name }}</p>
                    <p class="text-sm font-black text-gray-900 line-clamp-1 group-hover:text-blue-600 transition-colors">
                        {{ $model->displayLabel() }}
                    </p>
                    @if($ps)
                        <div class="mt-2 space-y-0.5">
                            <div class="flex items-baseline gap-1">
                                <span class="text-xs font-black text-gray-900">{{ $ps['min'] }}〜{{ $ps['max'] }}万円</span>
                            </div>
                            <p class="text-[10px] text-blue-600 font-bold">{{ $ps['count'] }}台在庫あり</p>
                        </div>
                    @else
                        <p class="mt-2 text-[10px] text-gray-400">現在在庫なし</p>
                    @endif
                </div>
            </a>
            @endforeach
        </div>

        {{-- ページネーション --}}
        <div class="mb-10">
            {{ $models->links() }}
        </div>
        @else
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-12 text-center mb-10">
            <i data-lucide="search-x" class="w-12 h-12 text-gray-300 mx-auto mb-4"></i>
            <p class="text-gray-500 font-bold">条件に一致する絶版車が見つかりませんでした。</p>
            <a href="{{ route('bikes.discontinued') }}" class="inline-block mt-3 text-sm text-blue-600 font-bold hover:underline">フィルターをリセット</a>
        </div>
        @endif

        {{-- SEOテキストセクション --}}
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 sm:p-8 mb-8">
            <h2 class="text-lg font-black text-gray-900 mb-4">絶版バイクとは</h2>
            <div class="text-sm text-gray-600 leading-relaxed space-y-4">
                <p>
                    絶版バイク（生産終了車）とは、メーカーが生産を終了したバイクのことです。排ガス規制の強化や市場ニーズの変化により、多くの名車が生産終了となっています。
                    しかし、中古市場ではこれらのモデルが今でも活発に取引されており、モデルによってはプレミア価格がつくこともあります。
                </p>

                <h3 class="text-base font-black text-gray-800 pt-2">絶版車を買うメリット</h3>
                <p>
                    絶版車には現行モデルにはない独自の魅力があります。
                    空冷エンジンの鼓動感、2ストロークの爆発的な加速、レトロなデザインなど、現代のバイクでは味わえない個性が詰まっています。
                    また、人気モデルは資産価値が維持される傾向にあり、大切に乗れば購入時と同等以上の価格で売却できるケースもあります。
                </p>

                <h3 class="text-base font-black text-gray-800 pt-2">絶版車を買う際の注意点</h3>
                <p>
                    一方で、年式が古いモデルは純正部品の供給が終了している場合があります。
                    購入前に整備記録の確認、消耗部品の在庫状況、専門ショップの有無などを調べておくことが重要です。
                    MotoHubでは各車種の中古在庫数と相場をリアルタイムで表示しているので、市場の動向を把握した上で購入を検討できます。
                </p>

                <h3 class="text-base font-black text-gray-800 pt-2">人気の絶版車カテゴリ</h3>
                <p>
                    特に人気が高いのは、ホンダ CB400SF・NSR250R、ヤマハ SR400・RZ250、カワサキ ゼファー・ZRX、スズキ GSX1100Sカタナなどの定番モデルです。
                    250ccクラスの2ストロークスポーツや、空冷4気筒ネイキッドは根強いファンが多く、中古市場でも高い人気を維持しています。
                </p>
            </div>
        </div>

        {{-- 関連リンク --}}
        <div class="flex flex-wrap gap-3 mb-8">
            <a href="{{ route('ranking.index') }}" class="inline-flex items-center gap-1.5 px-4 py-2 bg-gray-100 text-xs font-bold text-gray-700 rounded-full hover:bg-gray-200 transition">
                <i data-lucide="trophy" class="w-3.5 h-3.5"></i>
                売れ筋ランキング
            </a>
            <a href="{{ route('bikes.bargains') }}" class="inline-flex items-center gap-1.5 px-4 py-2 bg-gray-100 text-xs font-bold text-gray-700 rounded-full hover:bg-gray-200 transition">
                <i data-lucide="tag" class="w-3.5 h-3.5"></i>
                お買い得バイク
            </a>
            <a href="{{ route('bikes.search') }}" class="inline-flex items-center gap-1.5 px-4 py-2 bg-gray-100 text-xs font-bold text-gray-700 rounded-full hover:bg-gray-200 transition">
                <i data-lucide="search" class="w-3.5 h-3.5"></i>
                バイク検索
            </a>
        </div>
    </div>
</x-layout>
