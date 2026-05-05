<x-layout>
    <x-slot:title>輸入バイク中古車一覧 | 海外メーカー {{ $stats['total_count'] }}台掲載 | MotoHub</x-slot:title>
    <x-slot:metaDescription>ハーレー・BMW・ドゥカティ・トライアンフなど海外メーカーの中古バイクを{{ number_format($stats['total_count']) }}台掲載。{{ $stats['maker_count'] }}ブランドの価格相場・在庫状況をまとめてチェック。</x-slot:metaDescription>

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
        });
        </script>
    </x-slot:scripts>

    <x-slot:navigation>
        <x-navigation :showSearch="true" />
    </x-slot:navigation>

    <div class="bg-gray-50 min-h-screen">

        {{-- ヒーローセクション --}}
        <div class="bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 text-white pt-10 pb-14 px-4">
            <div class="max-w-5xl mx-auto text-center">
                {{-- パンくず --}}
                <nav class="flex justify-center text-xs font-bold text-gray-400 mb-8" aria-label="Breadcrumb">
                    <ol class="flex items-center space-x-2">
                        <li><a href="/" class="hover:text-white transition-colors">HOME</a></li>
                        <li><span class="text-gray-500">＞</span></li>
                        <li><span class="text-white">輸入バイク</span></li>
                    </ol>
                </nav>

                <div class="inline-block bg-white/10 text-white/90 text-[10px] font-black px-3 py-1.5 rounded-full mb-4 tracking-widest uppercase backdrop-blur">
                    Imported Motorcycles
                </div>
                <h1 class="text-3xl sm:text-4xl font-black mb-4 tracking-tight">
                    輸入バイク中古車まとめ
                </h1>
                <p class="text-gray-600 font-bold text-lg sm:text-xl leading-relaxed max-w-2xl mx-auto">
                    ハーレーダビッドソン・BMW・ドゥカティ・トライアンフなど<br class="hidden sm:block">
                    {{ $stats['maker_count'] }}ブランドの海外メーカー中古バイクを網羅的に掲載
                </p>

                {{-- 統計カード --}}
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-10 max-w-3xl mx-auto">
                    <div class="bg-white rounded-2xl p-4 text-center">
                        <div class="text-2xl font-black text-gray-900">{{ number_format($stats['total_count']) }}</div>
                        <div class="text-xs text-gray-500 font-bold mt-1">掲載台数</div>
                    </div>
                    <div class="bg-white rounded-2xl p-4 text-center">
                        <div class="text-2xl font-black text-gray-900">{{ $stats['maker_count'] }}</div>
                        <div class="text-xs text-gray-500 font-bold mt-1">ブランド</div>
                    </div>
                    <div class="bg-white rounded-2xl p-4 text-center">
                        <div class="text-2xl font-black text-gray-900">{{ $stats['avg_price'] }}<span class="text-sm">万円</span></div>
                        <div class="text-xs text-gray-500 font-bold mt-1">平均価格</div>
                    </div>
                    <div class="bg-white rounded-2xl p-4 text-center">
                        <div class="text-2xl font-black text-gray-900">{{ $stats['min_price'] }}<span class="text-sm">〜</span></div>
                        <div class="text-xs text-gray-500 font-bold mt-1">最安値（万円）</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="max-w-5xl mx-auto px-4 py-12">

            {{-- メーカー一覧 --}}
            <section class="mb-16">
                <h2 class="text-xl font-black text-gray-900 mb-6 flex items-center gap-2">
                    <span class="w-1 h-6 bg-blue-600 rounded-full"></span>
                    海外メーカー一覧
                </h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($makerCards as $card)
                        @if(!empty($card['slug']))
                        <a href="{{ route('bikes.overseas.maker', ['makerSlug' => $card['slug']]) }}"
                           class="block bg-white rounded-2xl border border-gray-100 overflow-hidden hover:shadow-lg hover:border-blue-200 transition-all group">
                            @if(!empty($card['image_url']))
                                <img src="{{ $card['image_url'] }}" alt="{{ $card['name'] }}" class="w-full h-36 object-cover" loading="lazy" onerror="handleImageError(this)">
                            @else
                                <div class="w-full h-36 bg-gradient-to-br from-gray-100 to-gray-200 flex items-center justify-center">
                                    <span class="text-3xl font-black text-gray-300">{{ mb_substr($card['name'], 0, 1) }}</span>
                                </div>
                            @endif
                            <div class="p-5">
                                <div class="flex items-center justify-between mb-3">
                                    <h3 class="font-black text-gray-900 group-hover:text-blue-600 transition-colors">{{ $card['name'] }}</h3>
                                    @if(!empty($card['country']) && $card['country'] !== '不明')
                                        <span class="text-[10px] font-bold text-gray-400 bg-gray-100 px-2 py-0.5 rounded-full">{{ $card['country'] }}</span>
                                    @endif
                                </div>
                                <div class="flex items-center gap-3 text-xs text-gray-500 font-bold mb-3">
                                    <span class="text-blue-600">{{ $card['count'] }}台</span>
                                    @if($card['min_price'] && $card['max_price'])
                                        <span>{{ $card['min_price'] }}〜{{ $card['max_price'] }}万円</span>
                                    @endif
                                </div>
                                @if(!empty($card['top_models']))
                                    <div class="flex flex-wrap gap-1">
                                        @foreach($card['top_models'] as $model)
                                            <span class="text-[10px] bg-gray-50 text-gray-500 px-2 py-0.5 rounded-full border border-gray-100">{{ $model }}</span>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </a>
                        @endif
                    @endforeach
                </div>
            </section>

            {{-- 価格帯分布 --}}
            <section class="mb-16">
                <h2 class="text-xl font-black text-gray-900 mb-6 flex items-center gap-2">
                    <span class="w-1 h-6 bg-green-600 rounded-full"></span>
                    価格帯分布
                </h2>
                <div class="bg-white rounded-2xl border border-gray-100 p-6">
                    <canvas id="price-chart" height="200"></canvas>
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
                                <div class="text-[10px] font-bold text-gray-400">{{ $listing->bikeModel?->manufacturer?->name }}</div>
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

            {{-- FAQ --}}
            <section class="mb-16">
                <h2 class="text-xl font-black text-gray-900 mb-6 flex items-center gap-2">
                    <span class="w-1 h-6 bg-purple-600 rounded-full"></span>
                    よくある質問
                </h2>
                <div class="space-y-3">
                    <details class="bg-white rounded-2xl border border-gray-100 group">
                        <summary class="px-5 py-4 cursor-pointer font-bold text-sm text-gray-800 list-none flex items-center justify-between">
                            輸入バイクの中古相場はどれくらい？
                            <span class="text-gray-300 group-open:rotate-180 transition-transform">&#9660;</span>
                        </summary>
                        <div class="px-5 pb-4 text-sm text-gray-500 leading-relaxed">
                            現在の掲載データでは、輸入バイク全体の平均価格は約{{ $stats['avg_price'] }}万円です。最安{{ $stats['min_price'] }}万円〜最高{{ $stats['max_price'] }}万円と幅広い価格帯があります。ブランドやモデルによって大きく異なるため、メーカー別ページで詳細をご確認ください。
                        </div>
                    </details>
                    <details class="bg-white rounded-2xl border border-gray-100 group">
                        <summary class="px-5 py-4 cursor-pointer font-bold text-sm text-gray-800 list-none flex items-center justify-between">
                            輸入バイクのメンテナンス費用は高い？
                            <span class="text-gray-300 group-open:rotate-180 transition-transform">&#9660;</span>
                        </summary>
                        <div class="px-5 pb-4 text-sm text-gray-500 leading-relaxed">
                            一般的に国産車より部品代は高くなりますが、正規ディーラーの充実やパーツ流通の改善により、以前ほどの差はなくなっています。定期メンテナンスをしっかり行えば、国産車と同様に長く乗ることが可能です。
                        </div>
                    </details>
                    <details class="bg-white rounded-2xl border border-gray-100 group">
                        <summary class="px-5 py-4 cursor-pointer font-bold text-sm text-gray-800 list-none flex items-center justify-between">
                            初めての輸入バイクにおすすめのメーカーは？
                            <span class="text-gray-300 group-open:rotate-180 transition-transform">&#9660;</span>
                        </summary>
                        <div class="px-5 pb-4 text-sm text-gray-500 leading-relaxed">
                            BMW、トライアンフ、ドゥカティは日本国内のディーラーネットワークが充実しており、アフターサービスが受けやすいため初めての輸入バイクにおすすめです。特にBMWはシャフトドライブモデルが多く、チェーンメンテが不要で維持しやすいと言われています。
                        </div>
                    </details>
                    <details class="bg-white rounded-2xl border border-gray-100 group">
                        <summary class="px-5 py-4 cursor-pointer font-bold text-sm text-gray-800 list-none flex items-center justify-between">
                            並行輸入車と正規輸入車の違いは？
                            <span class="text-gray-300 group-open:rotate-180 transition-transform">&#9660;</span>
                        </summary>
                        <div class="px-5 pb-4 text-sm text-gray-500 leading-relaxed">
                            正規輸入車は国内正規ディーラー経由で販売されたもので、メーカー保証やリコール対応が受けられます。並行輸入車は海外から直接輸入されたもので、価格が安い場合がありますが、保証やアフターサービスが限定される場合があります。中古購入時は販売店に確認しましょう。
                        </div>
                    </details>
                </div>
            </section>

        </div>
    </div>

    {{-- FAQ JSON-LD --}}
    <script type="application/ld+json">
    {
        "@@context": "https://schema.org",
        "@@type": "FAQPage",
        "mainEntity": [
            {
                "@@type": "Question",
                "name": "輸入バイクの中古相場はどれくらい？",
                "acceptedAnswer": {
                    "@@type": "Answer",
                    "text": "現在の掲載データでは、輸入バイク全体の平均価格は約{{ $stats['avg_price'] }}万円です。最安{{ $stats['min_price'] }}万円〜最高{{ $stats['max_price'] }}万円と幅広い価格帯があります。"
                }
            },
            {
                "@@type": "Question",
                "name": "輸入バイクのメンテナンス費用は高い？",
                "acceptedAnswer": {
                    "@@type": "Answer",
                    "text": "一般的に国産車より部品代は高くなりますが、正規ディーラーの充実やパーツ流通の改善により、以前ほどの差はなくなっています。"
                }
            },
            {
                "@@type": "Question",
                "name": "初めての輸入バイクにおすすめのメーカーは？",
                "acceptedAnswer": {
                    "@@type": "Answer",
                    "text": "BMW、トライアンフ、ドゥカティは日本国内のディーラーネットワークが充実しており、アフターサービスが受けやすいため初めての輸入バイクにおすすめです。"
                }
            },
            {
                "@@type": "Question",
                "name": "並行輸入車と正規輸入車の違いは？",
                "acceptedAnswer": {
                    "@@type": "Answer",
                    "text": "正規輸入車は国内正規ディーラー経由で販売されたもので、メーカー保証やリコール対応が受けられます。並行輸入車は海外から直接輸入されたもので、価格が安い場合がありますが、保証やアフターサービスが限定される場合があります。"
                }
            }
        ]
    }
    </script>
</x-layout>
