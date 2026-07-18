<x-layout>
    <x-slot:title>{{ $pageInfo['title'] }} | {{ number_format($kpi['total_count']) }}台掲載 | MotoHub</x-slot:title>
    <x-slot:metaDescription>{{ $pageInfo['label'] }}の中古バイクを{{ number_format($kpi['total_count']) }}台掲載中。{{ $pageInfo['description'] }}</x-slot:metaDescription>

    @if($kpi['total_count'] < 5)
        <x-slot:robotsMeta>noindex, follow</x-slot:robotsMeta>
    @endif

    <x-slot:styles>
        <script type="application/ld+json">
        {
            "@@context": "https://schema.org",
            "@@type": "BreadcrumbList",
            "itemListElement": [
                {
                    "@@type": "ListItem",
                    "position": 1,
                    "name": "HOME",
                    "item": "{{ url('/') }}"
                },
                {
                    "@@type": "ListItem",
                    "position": 2,
                    "name": "バイク検索",
                    "item": "{{ route('bikes.search') }}"
                },
                {
                    "@@type": "ListItem",
                    "position": 3,
                    "name": "{{ $pageInfo['label'] }}",
                    "item": "{{ url()->current() }}"
                }
            ]
        }
        </script>
    </x-slot:styles>

    <x-slot:navigation>
        <x-navigation :showSearch="true" />
    </x-slot:navigation>

    <div class="bg-gray-50 min-h-screen">

        {{-- ヒーローセクション --}}
        <div class="bg-white border-b border-gray-200 pt-8 pb-10 px-4">
            <div class="max-w-7xl mx-auto">
                {{-- パンくずリスト --}}
                <nav class="flex text-xs font-bold text-gray-400 mb-6" aria-label="Breadcrumb">
                    <ol class="flex items-center space-x-2">
                        <li><a href="/" class="hover:text-gray-600 transition-colors">HOME</a></li>
                        <li><span class="text-gray-300">＞</span></li>
                        <li><a href="{{ route('bikes.search') }}" class="hover:text-gray-600 transition-colors">バイク検索</a></li>
                        <li><span class="text-gray-300">＞</span></li>
                        <li><span class="text-gray-800">{{ $pageInfo['label'] }}</span></li>
                    </ol>
                </nav>

                {{-- H1 --}}
                <h1 class="text-2xl sm:text-3xl font-black text-gray-900 mb-3 tracking-tight">
                    {{ $pageInfo['title'] }}
                </h1>

                <div class="prose prose-sm max-w-4xl text-gray-500 leading-relaxed bg-gray-50 p-5 rounded-2xl border border-gray-100">
                    <p class="mb-2">{{ $pageInfo['description'] }}</p>
                    <p class="text-xs">
                        現在、<strong class="text-blue-600">{{ number_format($kpi['total_count']) }}台</strong> の{{ $pageInfo['label'] }}バイクが掲載されています。
                        価格相場や人気車種をチェックして、あなたにピッタリの1台を見つけましょう。
                    </p>
                </div>

                {{-- 新基準原付ハブへの誘導（原付・125ccの読者は意図がドンピシャ） --}}
                @if($mode === 'cc' && in_array($slug, ['50', '125'], true))
                    <a href="{{ route('shinkijun_gentsuki') }}"
                       class="group mt-4 flex items-center gap-3 bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-2xl p-4 max-w-4xl hover:border-blue-400 hover:shadow-md transition-all">
                        <span class="shrink-0 w-10 h-10 rounded-xl bg-blue-600 text-white flex items-center justify-center">
                            <i data-lucide="badge-check" class="w-5 h-5"></i>
                        </span>
                        <span class="flex-1 min-w-0">
                            <span class="block text-sm font-black text-gray-900">新基準原付の対象モデル・相場はこちら</span>
                            <span class="block text-xs text-gray-500 mt-0.5">2025年登場の新基準原付（Lite）の対象モデル一覧・新古車の相場・原付二種との違い</span>
                        </span>
                        <i data-lucide="chevron-right" class="shrink-0 w-5 h-5 text-blue-400 group-hover:translate-x-0.5 transition-transform"></i>
                    </a>
                @endif

                {{-- 保険ハブへの誘導（この排気量の維持費・保険）＝双方向内部リンク --}}
                @if($mode === 'cc')
                    <a href="{{ route('hoken') }}"
                       class="group mt-3 flex items-center gap-3 bg-emerald-50 border border-emerald-200 rounded-2xl p-4 max-w-4xl hover:border-emerald-400 hover:shadow-md transition-all">
                        <span class="shrink-0 w-10 h-10 rounded-xl bg-emerald-600 text-white flex items-center justify-center">
                            <i data-lucide="receipt-japanese-yen" class="w-5 h-5"></i>
                        </span>
                        <span class="flex-1 min-w-0">
                            <span class="block text-sm font-black text-gray-900">{{ $pageInfo['label'] ?? 'この排気量' }}の維持費・保険（税金・自賠責）</span>
                            <span class="block text-xs text-gray-500 mt-0.5">軽自動車税・自賠責の固定費早見表と、任意保険・ファミリーバイク特約の選び方</span>
                        </span>
                        <i data-lucide="chevron-right" class="shrink-0 w-5 h-5 text-emerald-400 group-hover:translate-x-0.5 transition-transform"></i>
                    </a>
                @endif
            </div>
        </div>

        {{-- KPIブロック --}}
        @if($kpi['total_count'] > 0)
        <div class="bg-white border-b border-gray-100">
            <div class="max-w-7xl mx-auto px-4 py-6">
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="bg-gray-50 rounded-2xl p-4 text-center">
                        <div class="text-xs font-bold text-gray-400 mb-1">掲載台数</div>
                        <div class="text-2xl font-black text-gray-900">{{ number_format($kpi['total_count']) }}<span class="text-sm font-bold text-gray-400 ml-1">台</span></div>
                    </div>
                    <div class="bg-gray-50 rounded-2xl p-4 text-center">
                        <div class="text-xs font-bold text-gray-400 mb-1">平均価格</div>
                        <div class="text-2xl font-black text-blue-600">{{ $kpi['avg_price'] ?? '-' }}<span class="text-sm font-bold text-gray-400 ml-1">万円</span></div>
                    </div>
                    <div class="bg-gray-50 rounded-2xl p-4 text-center">
                        <div class="text-xs font-bold text-gray-400 mb-1">最安値</div>
                        <div class="text-2xl font-black text-green-600">{{ $kpi['min_price'] ?? '-' }}<span class="text-sm font-bold text-gray-400 ml-1">万円</span></div>
                    </div>
                    <div class="bg-gray-50 rounded-2xl p-4 text-center">
                        <div class="text-xs font-bold text-gray-400 mb-1">最高値</div>
                        <div class="text-2xl font-black text-red-600">{{ $kpi['max_price'] ?? '-' }}<span class="text-sm font-bold text-gray-400 ml-1">万円</span></div>
                    </div>
                </div>
            </div>
        </div>
        @endif

        {{-- 人気車種TOP10 --}}
        @if($topModels->isNotEmpty())
        <div class="bg-white border-b border-gray-100">
            <div class="max-w-7xl mx-auto px-4 py-8">
                <h2 class="text-lg font-black text-gray-900 mb-6 flex items-center gap-2">
                    <i data-lucide="trophy" class="w-5 h-5 text-yellow-500"></i>
                    {{ $pageInfo['label'] }} 人気車種 TOP10
                </h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @foreach($topModels as $i => $model)
                    <div class="flex items-center gap-4 bg-gray-50 rounded-2xl p-4">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0 font-black text-white {{ $i === 0 ? 'bg-yellow-500' : ($i === 1 ? 'bg-gray-400' : ($i === 2 ? 'bg-amber-700' : 'bg-gray-300')) }}">
                            {{ $i + 1 }}
                        </div>
                        <div class="min-w-0 flex-1">
                            @if($model['mfr_slug'] && $model['model_slug'])
                                <a href="{{ url('/bikes/' . $model['mfr_slug'] . '/' . $model['model_slug']) }}" class="font-black text-gray-900 text-sm truncate block hover:text-blue-600 transition-colors">{{ $model['name'] }}</a>
                            @else
                                <div class="font-black text-gray-900 text-sm truncate">{{ $model['name'] }}</div>
                            @endif
                            <div class="text-xs text-gray-500 font-bold">
                                {{ number_format($model['count']) }}台掲載
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

        {{-- 最新入庫 --}}
        @if($latestListings->isNotEmpty())
        <div class="py-8">
            <div class="max-w-7xl mx-auto px-4">
                <h2 class="text-lg font-black text-gray-900 mb-6 flex items-center gap-2">
                    <i data-lucide="clock" class="w-5 h-5 text-blue-500"></i>
                    最新入庫 {{ $pageInfo['label'] }}バイク
                </h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                    @foreach($latestListings as $listing)
                        @include('bikes.partials.bike_card', ['listing' => $listing, 'isFirstView' => $loop->index < 4])
                    @endforeach
                </div>

                {{-- 全件検索リンク --}}
                <div class="mt-8 text-center">
                    <a href="{{ $searchUrl }}"
                       class="inline-flex items-center gap-2 bg-blue-600 text-white px-8 py-3 rounded-xl font-bold text-sm hover:bg-blue-700 transition-colors shadow-md">
                        <i data-lucide="search" class="w-4 h-4"></i>
                        {{ $pageInfo['label'] }}のバイクをすべて見る
                    </a>
                </div>
            </div>
        </div>
        @endif

        {{-- カテゴリ解説文 --}}
        <div class="bg-white border-t border-gray-100">
            <div class="max-w-7xl mx-auto px-4 py-10">
                <h2 class="text-lg font-black text-gray-900 mb-4">{{ $pageInfo['label'] }}バイクとは</h2>
                <div class="prose prose-sm max-w-none text-gray-600 leading-relaxed">
                    <p>{{ $pageInfo['description'] }}</p>
                    @if($kpi['total_count'] > 0)
                    <p class="mt-3">
                        MotoHubでは現在{{ number_format($kpi['total_count']) }}台の{{ $pageInfo['label'] }}バイクを掲載中。
                        平均価格は{{ $kpi['avg_price'] ?? '-' }}万円、最安値{{ $kpi['min_price'] ?? '-' }}万円から選べます。
                        条件を絞り込んで、あなたに最適な1台を見つけてください。
                    </p>
                    @endif
                </div>
            </div>
        </div>

        {{-- 関連カテゴリリンク --}}
        <div class="bg-gray-50 border-t border-gray-200">
            <div class="max-w-7xl mx-auto px-4 py-10">
                <h3 class="text-lg font-black text-gray-900 mb-6 flex items-center gap-2">
                    <i data-lucide="link-2" class="w-5 h-5 text-blue-500"></i>
                    関連カテゴリから探す
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    {{-- 排気量カテゴリ --}}
                    <div>
                        <h4 class="text-xs font-black text-gray-400 uppercase tracking-widest mb-3 border-b border-gray-200 pb-2">排気量別</h4>
                        <ul class="space-y-2">
                            @foreach($relatedLinks['cc'] as $link)
                            <li>
                                <a href="{{ $link['url'] }}" class="text-sm font-bold text-gray-600 hover:text-blue-600 hover:underline flex items-center gap-2">
                                    <i data-lucide="gauge" class="w-3.5 h-3.5 text-gray-400"></i>
                                    {{ $link['label'] }}
                                </a>
                            </li>
                            @endforeach
                        </ul>
                    </div>

                    {{-- タイプカテゴリ --}}
                    <div>
                        <h4 class="text-xs font-black text-gray-400 uppercase tracking-widest mb-3 border-b border-gray-200 pb-2">タイプ別</h4>
                        <ul class="space-y-2">
                            @foreach($relatedLinks['type'] as $link)
                            <li>
                                <a href="{{ $link['url'] }}" class="text-sm font-bold text-gray-600 hover:text-blue-600 hover:underline flex items-center gap-2">
                                    <i data-lucide="bike" class="w-3.5 h-3.5 text-gray-400"></i>
                                    {{ $link['label'] }}
                                </a>
                            </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </div>

    </div>
</x-layout>
