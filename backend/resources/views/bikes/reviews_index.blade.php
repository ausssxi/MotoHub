<x-layout>
    <x-slot:title>バイク 実車レビュー一覧 | オーナーの口コミ | MotoHub</x-slot:title>
    <x-slot:metaDescription>バイクオーナーの実車レビュー・口コミ一覧。車種ごとの評価・使用感をメーカー別・排気量別に探せます。あなたの愛車のレビューも投稿できます。</x-slot:metaDescription>

    {{-- 2頁目以降は薄い重複回避のため noindex（1頁目のみ index） --}}
    @if($reviews->currentPage() > 1)
    <x-slot:robotsMeta>noindex, follow</x-slot:robotsMeta>
    @endif

    <x-slot:navigation>
        <x-navigation :showSearch="true" />
    </x-slot:navigation>

    @php
        $ccLabel = function ($b) {
            if (($b['max'] ?? 0) >= 100000) return $b['min'] . 'cc〜';
            if (($b['min'] ?? 0) === 0) return '〜' . $b['max'] . 'cc';
            return $b['min'] . '〜' . $b['max'] . 'cc';
        };
    @endphp

    <div class="bg-gray-50 min-h-screen">
        <div class="bg-white border-b border-gray-200 pt-8 pb-10 px-4">
            <div class="max-w-5xl mx-auto">
                <nav class="flex text-xs font-bold text-gray-400 mb-6" aria-label="Breadcrumb">
                    <ol class="flex items-center space-x-2">
                        <li><a href="/" class="hover:text-gray-600 transition-colors">HOME</a></li>
                        <li><span class="text-gray-300">＞</span></li>
                        <li><span class="text-gray-800">レビュー</span></li>
                    </ol>
                </nav>

                <h1 class="text-2xl sm:text-3xl font-black text-gray-900 mb-3 tracking-tight">
                    バイク 実車レビュー一覧<span class="text-lg text-gray-400 ml-2">オーナーの口コミ</span>
                </h1>
                <p class="text-sm text-gray-500 leading-relaxed max-w-3xl">
                    バイクオーナーが投稿した実車レビュー・口コミの一覧です。メーカー別・排気量別に絞り込んで、気になる車種の評価をチェックできます。
                </p>

                {{-- 投稿CTA --}}
                <a href="{{ route('bikes.search') }}" class="inline-flex items-center gap-2 mt-5 bg-blue-600 hover:bg-blue-700 text-white font-bold text-sm px-5 py-3 rounded-xl transition-colors">
                    <i data-lucide="pen-line" class="w-4 h-4"></i>
                    あなたのバイクのレビューを書く
                </a>
            </div>
        </div>

        <div class="max-w-5xl mx-auto px-4 py-8">
            {{-- フィルタ --}}
            <div class="mb-6 space-y-3">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-xs font-black text-gray-400 mr-1">メーカー</span>
                    <a href="{{ route('bikes.reviews_index', ['cc' => $selectedCc]) }}"
                       class="px-3 py-1.5 rounded-lg text-xs font-bold {{ !$selectedMaker ? 'bg-blue-600 text-white' : 'bg-white text-gray-600 border border-gray-200 hover:bg-blue-50' }}">すべて</a>
                    @foreach($makers as $m)
                    <a href="{{ route('bikes.reviews_index', ['maker' => $m->id, 'cc' => $selectedCc]) }}"
                       class="px-3 py-1.5 rounded-lg text-xs font-bold {{ (int)$selectedMaker === (int)$m->id ? 'bg-blue-600 text-white' : 'bg-white text-gray-600 border border-gray-200 hover:bg-blue-50' }}">{{ $m->name }} <span class="text-[10px] opacity-70">{{ $m->c }}</span></a>
                    @endforeach
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-xs font-black text-gray-400 mr-1">排気量</span>
                    <a href="{{ route('bikes.reviews_index', ['maker' => $selectedMaker]) }}"
                       class="px-3 py-1.5 rounded-lg text-xs font-bold {{ !$selectedCc ? 'bg-green-600 text-white' : 'bg-white text-gray-600 border border-gray-200 hover:bg-green-50' }}">すべて</a>
                    @foreach($ccBands as $b)
                    <a href="{{ route('bikes.reviews_index', ['cc' => $b['slug'], 'maker' => $selectedMaker]) }}"
                       class="px-3 py-1.5 rounded-lg text-xs font-bold {{ $selectedCc === $b['slug'] ? 'bg-green-600 text-white' : 'bg-white text-gray-600 border border-gray-200 hover:bg-green-50' }}">{{ $ccLabel($b) }}</a>
                    @endforeach
                </div>
            </div>

            {{-- レビューカード --}}
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @forelse($reviews as $review)
                <a href="{{ $review->bikeModel->seo_url }}#reviews" class="group bg-white rounded-2xl p-5 shadow-sm border border-gray-100 hover:border-blue-300 hover:shadow-lg transition-all flex flex-col">
                    <div class="flex items-start justify-between mb-3">
                        <div class="min-w-0">
                            <span class="text-[9px] font-bold text-gray-400 block mb-0.5">{{ $review->bikeModel->manufacturer?->name }}</span>
                            <h2 class="text-sm font-black text-gray-800 group-hover:text-blue-600 transition-colors line-clamp-1">{{ $review->bikeModel->name }}</h2>
                        </div>
                        <div class="flex items-center text-yellow-400 shrink-0 ml-2">
                            <i data-lucide="star" class="w-4 h-4 fill-current"></i>
                            <span class="ml-1 text-sm font-black text-gray-700">{{ $review->rating }}</span>
                        </div>
                    </div>
                    <div class="flex-grow mb-3">
                        <h3 class="text-xs font-bold text-gray-900 mb-1 line-clamp-1">{{ $review->title }}</h3>
                        <p class="text-[11px] text-gray-500 leading-relaxed line-clamp-3">{{ $review->body }}</p>
                    </div>
                    <div class="flex items-center justify-between text-[10px] font-bold text-gray-400 pt-2 border-t border-gray-100">
                        <span class="truncate">{{ $review->nickname }}</span>
                        <span class="shrink-0 ml-2">{{ $review->created_at?->format('Y/m/d') }}</span>
                    </div>
                </a>
                @empty
                <div class="col-span-full bg-white rounded-2xl p-12 text-center border border-gray-100">
                    <p class="text-sm font-bold text-gray-400">この条件のレビューはまだありません。</p>
                </div>
                @endforelse
            </div>

            <div class="mt-8">{{ $reviews->links() }}</div>
        </div>

        {{-- JSON-LD: CollectionPage + Breadcrumb のみ（Review/Rating は付けない） --}}
        <script type="application/ld+json">
        {
            "@@context": "https://schema.org",
            "@@type": "CollectionPage",
            "name": "バイク 実車レビュー一覧",
            "description": "バイクオーナーの実車レビュー・口コミ一覧",
            "breadcrumb": {
                "@@type": "BreadcrumbList",
                "itemListElement": [
                    { "@@type": "ListItem", "position": 1, "name": "HOME", "item": "{{ url('/') }}" },
                    { "@@type": "ListItem", "position": 2, "name": "レビュー" }
                ]
            }
        }
        </script>
    </div>
</x-layout>
