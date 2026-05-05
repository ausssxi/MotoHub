<x-layout>
    <x-slot:title>{{ $manufacturer->name }}の中古バイク {{ $stats['total_count'] }}台 | 輸入バイク | MotoHub</x-slot:title>
    <x-slot:metaDescription>{{ $manufacturer->name }}の中古バイクを{{ $stats['total_count'] }}台掲載。平均価格{{ $stats['avg_price'] }}万円、人気モデルランキング・価格帯分布・年式分布をチェック。</x-slot:metaDescription>

    <x-slot:scripts>
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js" defer></script>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const priceData = @json($priceDistribution);
            new Chart(document.getElementById('price-chart'), {
                type: 'bar',
                data: {
                    labels: priceData.map(d => d.label),
                    datasets: [{
                        label: '台数',
                        data: priceData.map(d => d.count),
                        backgroundColor: 'rgba(37, 99, 235, 0.8)',
                        borderRadius: 8,
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, ticks: { precision: 0 } },
                        x: { grid: { display: false } }
                    }
                }
            });

            @if(!empty($yearDistribution))
            const yearData = @json($yearDistribution);
            new Chart(document.getElementById('year-chart'), {
                type: 'bar',
                data: {
                    labels: yearData.map(d => d.label),
                    datasets: [{
                        label: '台数',
                        data: yearData.map(d => d.count),
                        backgroundColor: 'rgba(245, 158, 11, 0.8)',
                        borderRadius: 8,
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, ticks: { precision: 0 } },
                        x: { grid: { display: false } }
                    }
                }
            });
            @endif
        });
        </script>
    </x-slot:scripts>

    <x-slot:navigation>
        <x-navigation :showSearch="true" />
    </x-slot:navigation>

    <div class="bg-gray-50 min-h-screen">

        {{-- ヒーロー --}}
        <div class="relative text-white pt-8 pb-12 px-4 overflow-hidden">
            @if(!empty($heroImage))
                <img src="{{ $heroImage }}" alt="{{ $manufacturer->name }}" class="absolute inset-0 w-full h-full object-cover" onerror="handleImageError(this)">
            @endif
            <div class="absolute inset-0 bg-gradient-to-br from-slate-900/90 via-slate-800/85 to-slate-900/90"></div>
            <div class="max-w-5xl mx-auto relative z-10">
                {{-- パンくず --}}
                <nav class="flex text-xs font-bold text-gray-400 mb-6" aria-label="Breadcrumb">
                    <ol class="flex items-center space-x-2">
                        <li><a href="/" class="hover:text-white transition-colors">HOME</a></li>
                        <li><span class="text-gray-500">＞</span></li>
                        <li><a href="{{ route('bikes.overseas') }}" class="hover:text-white transition-colors">輸入バイク</a></li>
                        <li><span class="text-gray-500">＞</span></li>
                        <li><span class="text-white">{{ $manufacturer->name }}</span></li>
                    </ol>
                </nav>

                {{-- パンくずJSON-LD --}}
                <script type="application/ld+json">
                {
                    "@@context": "https://schema.org",
                    "@@type": "BreadcrumbList",
                    "itemListElement": [
                        {"@@type": "ListItem", "position": 1, "name": "HOME", "item": "{{ url('/') }}"},
                        {"@@type": "ListItem", "position": 2, "name": "輸入バイク", "item": "{{ route('bikes.overseas') }}"},
                        {"@@type": "ListItem", "position": 3, "name": "{{ $manufacturer->name }}"}
                    ]
                }
                </script>

                <div class="text-center">
                    @if(!empty($manufacturer->country) && $manufacturer->country !== '不明')
                        <span class="inline-block bg-white/20 text-white text-[10px] font-black px-3 py-1 rounded-full mb-3">{{ $manufacturer->country }}</span>
                    @endif
                    <h1 class="text-3xl sm:text-4xl font-black mb-3 tracking-tight">{{ $manufacturer->name }}</h1>
                    <p class="text-gray-300 text-sm font-bold">中古バイク在庫・価格相場・人気モデル</p>
                </div>

                {{-- 統計カード --}}
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-8 max-w-3xl mx-auto">
                    <div class="bg-white rounded-2xl p-4 text-center">
                        <div class="text-2xl font-black text-gray-900">{{ number_format($stats['total_count']) }}</div>
                        <div class="text-xs text-gray-500 font-bold mt-1">在庫台数</div>
                    </div>
                    <div class="bg-white rounded-2xl p-4 text-center">
                        <div class="text-2xl font-black text-gray-900">{{ $stats['avg_price'] }}<span class="text-sm">万</span></div>
                        <div class="text-xs text-gray-500 font-bold mt-1">平均価格</div>
                    </div>
                    <div class="bg-white rounded-2xl p-4 text-center">
                        <div class="text-2xl font-black text-gray-900">{{ $stats['median_price'] }}<span class="text-sm">万</span></div>
                        <div class="text-xs text-gray-500 font-bold mt-1">中央値</div>
                    </div>
                    <div class="bg-white rounded-2xl p-4 text-center">
                        <div class="text-2xl font-black text-gray-900">{{ $stats['min_price'] }}<span class="text-sm">〜</span></div>
                        <div class="text-xs text-gray-500 font-bold mt-1">最安値（万円）</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="max-w-5xl mx-auto px-4 py-12">

            {{-- 人気モデルランキング --}}
            <section class="mb-16">
                <h2 class="text-xl font-black text-gray-900 mb-6 flex items-center gap-2">
                    <span class="w-1 h-6 bg-blue-600 rounded-full"></span>
                    人気モデルランキング
                </h2>
                <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden">
                    @foreach($topModels as $index => $model)
                        @if(!empty($model->mfr_slug) && !empty($model->model_slug))
                        <a href="{{ route('bikes.model_detail', ['mfrSlug' => $model->mfr_slug, 'modelSlug' => $model->model_slug]) }}"
                           class="flex items-center px-5 py-4 hover:bg-gray-50 transition-colors {{ !$loop->last ? 'border-b border-gray-50' : '' }}">
                        @else
                        <div class="flex items-center px-5 py-4 {{ !$loop->last ? 'border-b border-gray-50' : '' }}">
                        @endif
                            <span class="w-8 h-8 rounded-full flex items-center justify-center shrink-0 text-sm font-black
                                {{ $index < 3 ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-500' }}">
                                {{ $index + 1 }}
                            </span>
                            <div class="ml-4 min-w-0 flex-1">
                                <div class="font-black text-gray-900 text-sm truncate">{{ $model->name }}</div>
                                <div class="text-xs text-gray-400 font-bold mt-0.5">{{ $model->listing_count }}台掲載</div>
                            </div>
                            @if($model->avg_price)
                                <div class="text-sm font-black text-blue-600 shrink-0">{{ $model->avg_price }}万円<span class="text-[10px] text-gray-400 font-bold ml-1">平均</span></div>
                            @endif
                        @if(!empty($model->mfr_slug) && !empty($model->model_slug))
                        </a>
                        @else
                        </div>
                        @endif
                    @endforeach
                </div>
            </section>

            {{-- 価格帯・年式分布 --}}
            <section class="mb-16">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <h2 class="text-lg font-black text-gray-900 mb-4 flex items-center gap-2">
                            <span class="w-1 h-5 bg-green-600 rounded-full"></span>
                            価格帯分布
                        </h2>
                        <div class="bg-white rounded-2xl border border-gray-100 p-5">
                            <canvas id="price-chart" height="180"></canvas>
                        </div>
                    </div>
                    @if(!empty($yearDistribution))
                    <div>
                        <h2 class="text-lg font-black text-gray-900 mb-4 flex items-center gap-2">
                            <span class="w-1 h-5 bg-amber-500 rounded-full"></span>
                            年式分布
                        </h2>
                        <div class="bg-white rounded-2xl border border-gray-100 p-5">
                            <canvas id="year-chart" height="180"></canvas>
                        </div>
                    </div>
                    @endif
                </div>
            </section>

            {{-- 新着入荷 --}}
            <section class="mb-16">
                <h2 class="text-xl font-black text-gray-900 mb-6 flex items-center gap-2">
                    <span class="w-1 h-6 bg-orange-500 rounded-full"></span>
                    新着入荷
                </h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @foreach($newArrivals as $listing)
                        <a href="{{ route('bikes.show', $listing->id) }}"
                           class="flex bg-white rounded-2xl border border-gray-100 overflow-hidden hover:shadow-md transition-shadow">
                            @if(!empty($listing->images[0]))
                                <img src="{{ $listing->images[0] }}" alt="{{ $listing->bikeModel?->name }}"
                                     class="w-28 h-24 object-cover shrink-0" loading="lazy" onerror="handleImageError(this)">
                            @else
                                <div class="w-28 h-24 bg-gray-100 shrink-0 flex items-center justify-center">
                                    <span class="text-gray-300 text-xs">No Image</span>
                                </div>
                            @endif
                            <div class="p-3 min-w-0">
                                <div class="text-sm font-black text-gray-900 truncate">{{ $listing->bikeModel?->name }}</div>
                                @if($listing->total_price)
                                    <div class="text-sm font-black text-blue-600 mt-1">{{ number_format($listing->total_price) }}円</div>
                                @else
                                    <div class="text-sm font-bold text-gray-400 mt-1">価格ASK</div>
                                @endif
                                <div class="text-[10px] text-gray-400 mt-0.5">{{ $listing->model_year ? $listing->model_year . '年' : '' }} {{ $listing->mileage ? number_format($listing->mileage) . 'km' : '' }}</div>
                            </div>
                        </a>
                    @endforeach
                </div>
            </section>

            {{-- 他のメーカー --}}
            @if($otherMakers->isNotEmpty())
            <section class="mb-16">
                <h2 class="text-xl font-black text-gray-900 mb-6 flex items-center gap-2">
                    <span class="w-1 h-6 bg-gray-400 rounded-full"></span>
                    他の輸入バイクメーカー
                </h2>
                <div class="flex flex-wrap gap-2">
                    @foreach($otherMakers as $other)
                        @if(!empty($other->slug))
                        <a href="{{ route('bikes.overseas.maker', ['makerSlug' => $other->slug]) }}"
                           class="bg-white border border-gray-100 px-4 py-2 rounded-full text-sm font-bold text-gray-700 hover:border-blue-200 hover:text-blue-600 transition-colors">
                            {{ $other->name }}
                        </a>
                        @endif
                    @endforeach
                </div>
            </section>
            @endif

            {{-- CTA --}}
            <div class="text-center">
                <a href="{{ route('bikes.overseas') }}"
                   class="inline-flex items-center gap-2 bg-gray-900 text-white px-6 py-3 rounded-full font-bold text-sm hover:bg-gray-700 transition-colors">
                    ← 輸入バイク一覧に戻る
                </a>
            </div>
        </div>
    </div>

</x-layout>
