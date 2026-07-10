<x-layout>
    <x-slot:title>{{ $model1->name }} vs {{ $model2->name }} 徹底比較 | MotoHub</x-slot:title>
    <x-slot:metaDescription>{{ $model1->name }}と{{ $model2->name }}のスペック・中古相場を徹底比較。排気量・車両重量・シート高・価格帯分布を並べてチェック。あなたに合った1台を見つけましょう。</x-slot:metaDescription>
    {{-- canonical は layout 既定の url()->current()。非canonicalは controller が 301 するため常に正規URLで描画される --}}

    <x-slot:navigation>
        <x-navigation :showSearch="true" />
    </x-slot:navigation>

    <div class="bg-gray-50 min-h-screen">

        {{-- ヘッダー --}}
        <div class="bg-white border-b border-gray-200 pt-8 pb-10 px-4">
            <div class="max-w-5xl mx-auto">
                {{-- パンくずリスト --}}
                <nav class="flex text-xs font-bold text-gray-400 mb-6" aria-label="Breadcrumb">
                    <ol class="flex items-center space-x-2">
                        <li><a href="/" class="hover:text-gray-600 transition-colors">HOME</a></li>
                        <li><span class="text-gray-300">＞</span></li>
                        <li><a href="{{ route('bikes.models') }}" class="hover:text-gray-600 transition-colors">車種一覧</a></li>
                        <li><span class="text-gray-300">＞</span></li>
                        <li><span class="text-gray-800">{{ $model1->name }} vs {{ $model2->name }}</span></li>
                    </ol>
                </nav>

                <h1 class="text-2xl sm:text-3xl font-black text-gray-900 mb-3 tracking-tight">
                    {{ $model1->name }} vs {{ $model2->name }}<span class="text-lg text-gray-400 ml-2">徹底比較</span>
                </h1>

                <p class="text-sm text-gray-500 leading-relaxed max-w-3xl">
                    {{ $model1->name }}と{{ $model2->name }}のスペック・中古価格相場を比較します。
                    排気量・車両重量・シート高などの基本スペックから、現在の在庫数・平均価格・価格帯分布まで一目で比較できます。
                </p>
            </div>
        </div>

        {{-- 車種画像 --}}
        <div class="bg-white border-b border-gray-100">
            <div class="max-w-5xl mx-auto px-4 py-8">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <div class="text-center">
                        <div class="bg-gray-50 rounded-2xl overflow-hidden aspect-[4/3] flex items-center justify-center">
                            @if($model1->image_url)
                                <img src="{{ $model1->image_url }}" alt="{{ $model1->name }}" class="w-full h-full object-contain" loading="lazy">
                            @else
                                <div class="text-gray-300 flex flex-col items-center gap-2">
                                    <i data-lucide="image" class="w-12 h-12"></i>
                                    <span class="text-xs font-bold">No Image</span>
                                </div>
                            @endif
                        </div>
                        {{-- 逆方向の内部リンク: 各車種の詳細ページへ --}}
                        <a href="{{ $model1->seo_url }}" class="mt-3 inline-block font-black text-blue-600 text-sm hover:underline">{{ $model1->name }}の詳細</a>
                        <p class="text-xs font-bold text-gray-400">{{ $model1->manufacturer?->name }} / {{ $model1->displacement ? $model1->displacement . 'cc' : '-' }}</p>
                    </div>
                    <div class="text-center">
                        <div class="bg-gray-50 rounded-2xl overflow-hidden aspect-[4/3] flex items-center justify-center">
                            @if($model2->image_url)
                                <img src="{{ $model2->image_url }}" alt="{{ $model2->name }}" class="w-full h-full object-contain" loading="lazy">
                            @else
                                <div class="text-gray-300 flex flex-col items-center gap-2">
                                    <i data-lucide="image" class="w-12 h-12"></i>
                                    <span class="text-xs font-bold">No Image</span>
                                </div>
                            @endif
                        </div>
                        <a href="{{ $model2->seo_url }}" class="mt-3 inline-block font-black text-blue-600 text-sm hover:underline">{{ $model2->name }}の詳細</a>
                        <p class="text-xs font-bold text-gray-400">{{ $model2->manufacturer?->name }} / {{ $model2->displacement ? $model2->displacement . 'cc' : '-' }}</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- スペック比較テーブル --}}
        <div class="bg-white border-b border-gray-100">
            <div class="max-w-5xl mx-auto px-4 py-8">
                <h2 class="text-lg font-black text-gray-900 mb-5 flex items-center gap-2">
                    <i data-lucide="clipboard-list" class="w-5 h-5 text-blue-500"></i>
                    スペック比較
                </h2>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b-2 border-gray-200">
                                <th class="text-left py-3 px-4 font-bold text-gray-500 w-1/4">項目</th>
                                <th class="text-center py-3 px-4 font-black text-gray-900 w-[37.5%] bg-blue-50 rounded-tl-xl">{{ $model1->name }}</th>
                                <th class="text-center py-3 px-4 font-black text-gray-900 w-[37.5%] bg-orange-50 rounded-tr-xl">{{ $model2->name }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @php
                                $specs = [
                                    ['label' => 'メーカー',   'v1' => $model1->manufacturer?->name, 'v2' => $model2->manufacturer?->name],
                                    ['label' => 'カテゴリ',   'v1' => $model1->categoryData?->name, 'v2' => $model2->categoryData?->name],
                                    ['label' => '総排気量',   'v1' => $model1->displacement ? "{$model1->displacement}cc" : null, 'v2' => $model2->displacement ? "{$model2->displacement}cc" : null],
                                    ['label' => 'エンジン種類','v1' => $model1->engine_type, 'v2' => $model2->engine_type],
                                    ['label' => '車両重量',   'v1' => $model1->weight ? "{$model1->weight}kg" : null, 'v2' => $model2->weight ? "{$model2->weight}kg" : null],
                                    ['label' => 'シート高',   'v1' => $model1->seat_height ? "{$model1->seat_height}mm" : null, 'v2' => $model2->seat_height ? "{$model2->seat_height}mm" : null],
                                ];
                            @endphp

                            @foreach($specs as $spec)
                            <tr>
                                <td class="py-3 px-4 font-bold text-gray-500">{{ $spec['label'] }}</td>
                                <td class="py-3 px-4 text-center font-bold text-gray-900 bg-blue-50/50">{{ $spec['v1'] ?? '-' }}</td>
                                <td class="py-3 px-4 text-center font-bold text-gray-900 bg-orange-50/50">{{ $spec['v2'] ?? '-' }}</td>
                            </tr>
                            @endforeach

                            {{-- 車検 --}}
                            <tr>
                                <td class="py-3 px-4 font-bold text-gray-500">車検</td>
                                <td class="py-3 px-4 text-center font-bold bg-blue-50/50 {{ ($model1->displacement ?? 0) <= 250 ? 'text-green-600' : 'text-gray-900' }}">
                                    {{ ($model1->displacement ?? 0) <= 250 ? '不要' : '必要（2年ごと）' }}
                                </td>
                                <td class="py-3 px-4 text-center font-bold bg-orange-50/50 {{ ($model2->displacement ?? 0) <= 250 ? 'text-green-600' : 'text-gray-900' }}">
                                    {{ ($model2->displacement ?? 0) <= 250 ? '不要' : '必要（2年ごと）' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- KPI比較 --}}
        @if(($kpi['model1']['total_count'] ?? 0) > 0 || ($kpi['model2']['total_count'] ?? 0) > 0)
        <div class="bg-white border-b border-gray-100">
            <div class="max-w-5xl mx-auto px-4 py-8">
                <h2 class="text-lg font-black text-gray-900 mb-5 flex items-center gap-2">
                    <i data-lucide="bar-chart-3" class="w-5 h-5 text-green-500"></i>
                    中古相場比較
                </h2>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b-2 border-gray-200">
                                <th class="text-left py-3 px-4 font-bold text-gray-500 w-1/4">指標</th>
                                <th class="text-center py-3 px-4 font-black text-gray-900 w-[37.5%] bg-blue-50">{{ $model1->name }}</th>
                                <th class="text-center py-3 px-4 font-black text-gray-900 w-[37.5%] bg-orange-50">{{ $model2->name }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr>
                                <td class="py-3 px-4 font-bold text-gray-500">掲載台数</td>
                                <td class="py-3 px-4 text-center font-black text-gray-900 bg-blue-50/50">{{ number_format($kpi['model1']['total_count']) }}<span class="text-xs text-gray-400 ml-1">台</span></td>
                                <td class="py-3 px-4 text-center font-black text-gray-900 bg-orange-50/50">{{ number_format($kpi['model2']['total_count']) }}<span class="text-xs text-gray-400 ml-1">台</span></td>
                            </tr>
                            <tr>
                                <td class="py-3 px-4 font-bold text-gray-500">中古相場（中央値）</td>
                                <td class="py-3 px-4 text-center font-black text-blue-600 bg-blue-50/50">{{ $kpi['model1']['median_price'] ?? '-' }}<span class="text-xs text-gray-400 ml-1">万円</span></td>
                                <td class="py-3 px-4 text-center font-black text-blue-600 bg-orange-50/50">{{ $kpi['model2']['median_price'] ?? '-' }}<span class="text-xs text-gray-400 ml-1">万円</span></td>
                            </tr>
                            <tr>
                                <td class="py-3 px-4 font-bold text-gray-500">安値帯</td>
                                <td class="py-3 px-4 text-center font-black text-green-600 bg-blue-50/50">{{ $kpi['model1']['min_price'] ?? '-' }}<span class="text-xs text-gray-400 ml-1">万円</span></td>
                                <td class="py-3 px-4 text-center font-black text-green-600 bg-orange-50/50">{{ $kpi['model2']['min_price'] ?? '-' }}<span class="text-xs text-gray-400 ml-1">万円</span></td>
                            </tr>
                            <tr>
                                <td class="py-3 px-4 font-bold text-gray-500">高値帯</td>
                                <td class="py-3 px-4 text-center font-black text-red-500 bg-blue-50/50">{{ $kpi['model1']['max_price'] ?? '-' }}<span class="text-xs text-gray-400 ml-1">万円</span></td>
                                <td class="py-3 px-4 text-center font-black text-red-500 bg-orange-50/50">{{ $kpi['model2']['max_price'] ?? '-' }}<span class="text-xs text-gray-400 ml-1">万円</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p class="text-[11px] text-gray-400 mt-3">
                    ※ 現在の在庫データに基づく中古相場（中央値・外れ値除外）。安値帯/高値帯は概ね下位5%/上位5%水準。
                </p>
            </div>
        </div>
        @endif

        {{-- 価格帯分布 --}}
        @if(!empty($kpi['model1']['price_distribution']) || !empty($kpi['model2']['price_distribution']))
        <div class="bg-white border-b border-gray-100">
            <div class="max-w-5xl mx-auto px-4 py-8">
                <h2 class="text-lg font-black text-gray-900 mb-5 flex items-center gap-2">
                    <i data-lucide="pie-chart" class="w-5 h-5 text-purple-500"></i>
                    価格帯分布
                </h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    {{-- Model 1 --}}
                    <div class="bg-blue-50 rounded-2xl p-5">
                        <h3 class="text-sm font-black text-gray-900 mb-4">{{ $model1->name }}</h3>
                        @if(!empty($kpi['model1']['price_distribution']))
                        <div class="space-y-2">
                            @foreach($kpi['model1']['price_distribution'] as $band)
                            <div class="flex items-center gap-3">
                                <div class="w-20 text-xs font-bold text-gray-600 text-right flex-shrink-0">{{ $band['label'] }}</div>
                                <div class="flex-1 bg-white rounded-full h-4 overflow-hidden">
                                    <div class="h-full rounded-full {{ $band['is_max'] ? 'bg-blue-600' : 'bg-blue-400' }}" style="width: {{ $band['bar_width'] }}%"></div>
                                </div>
                                <div class="w-12 text-xs font-bold text-gray-500 flex-shrink-0">{{ number_format($band['count']) }}台</div>
                            </div>
                            @endforeach
                        </div>
                        @else
                        <p class="text-sm text-gray-400">在庫データなし</p>
                        @endif
                    </div>

                    {{-- Model 2 --}}
                    <div class="bg-orange-50 rounded-2xl p-5">
                        <h3 class="text-sm font-black text-gray-900 mb-4">{{ $model2->name }}</h3>
                        @if(!empty($kpi['model2']['price_distribution']))
                        <div class="space-y-2">
                            @foreach($kpi['model2']['price_distribution'] as $band)
                            <div class="flex items-center gap-3">
                                <div class="w-20 text-xs font-bold text-gray-600 text-right flex-shrink-0">{{ $band['label'] }}</div>
                                <div class="flex-1 bg-white rounded-full h-4 overflow-hidden">
                                    <div class="h-full rounded-full {{ $band['is_max'] ? 'bg-orange-600' : 'bg-orange-400' }}" style="width: {{ $band['bar_width'] }}%"></div>
                                </div>
                                <div class="w-12 text-xs font-bold text-gray-500 flex-shrink-0">{{ number_format($band['count']) }}台</div>
                            </div>
                            @endforeach
                        </div>
                        @else
                        <p class="text-sm text-gray-400">在庫データなし</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @endif

        {{-- 在庫を探すリンク --}}
        <div class="bg-white border-b border-gray-100">
            <div class="max-w-5xl mx-auto px-4 py-8">
                <h2 class="text-lg font-black text-gray-900 mb-5 flex items-center gap-2">
                    <i data-lucide="search" class="w-5 h-5 text-blue-500"></i>
                    在庫を探す
                </h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <a href="{{ route('bikes.search', ['keyword' => $model1->name]) }}" class="flex items-center justify-between bg-blue-50 hover:bg-blue-100 rounded-2xl p-5 transition-colors group">
                        <div>
                            <div class="font-black text-gray-900 group-hover:text-blue-600 transition-colors">{{ $model1->name }}の在庫一覧</div>
                            <div class="text-xs font-bold text-gray-400 mt-1">{{ number_format($kpi['model1']['total_count']) }}台掲載中</div>
                        </div>
                        <i data-lucide="chevron-right" class="w-5 h-5 text-gray-400 group-hover:text-blue-500"></i>
                    </a>
                    <a href="{{ route('bikes.search', ['keyword' => $model2->name]) }}" class="flex items-center justify-between bg-orange-50 hover:bg-orange-100 rounded-2xl p-5 transition-colors group">
                        <div>
                            <div class="font-black text-gray-900 group-hover:text-orange-600 transition-colors">{{ $model2->name }}の在庫一覧</div>
                            <div class="text-xs font-bold text-gray-400 mt-1">{{ number_format($kpi['model2']['total_count']) }}台掲載中</div>
                        </div>
                        <i data-lucide="chevron-right" class="w-5 h-5 text-gray-400 group-hover:text-orange-500"></i>
                    </a>
                </div>

                {{-- 車種詳細ページリンク --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">
                    <a href="{{ url($model1->seo_url) }}" class="flex items-center justify-between bg-gray-50 hover:bg-gray-100 rounded-2xl p-5 transition-colors group">
                        <div>
                            <div class="font-black text-gray-900 group-hover:text-blue-600 transition-colors">{{ $model1->name }}の詳細・スペック</div>
                            <div class="text-xs font-bold text-gray-400 mt-1">カタログ情報・レビュー</div>
                        </div>
                        <i data-lucide="chevron-right" class="w-5 h-5 text-gray-400"></i>
                    </a>
                    <a href="{{ url($model2->seo_url) }}" class="flex items-center justify-between bg-gray-50 hover:bg-gray-100 rounded-2xl p-5 transition-colors group">
                        <div>
                            <div class="font-black text-gray-900 group-hover:text-orange-600 transition-colors">{{ $model2->name }}の詳細・スペック</div>
                            <div class="text-xs font-bold text-gray-400 mt-1">カタログ情報・レビュー</div>
                        </div>
                        <i data-lucide="chevron-right" class="w-5 h-5 text-gray-400"></i>
                    </a>
                </div>
            </div>
        </div>

        {{-- 販売中の車両（両モデル・安い順）＋入荷通知CTA --}}
        <div class="bg-white border-b border-gray-100">
            <div class="max-w-5xl mx-auto px-4 py-8">
                <h2 class="text-lg font-black text-gray-900 mb-5 flex items-center gap-2">
                    <i data-lucide="tag" class="w-5 h-5 text-red-500"></i>
                    販売中の車両
                </h2>

                @php
                    $inventoryBlocks = [
                        ['model' => $model1, 'items' => $inventory1, 'accent' => 'blue'],
                        ['model' => $model2, 'items' => $inventory2, 'accent' => 'orange'],
                    ];
                @endphp

                <div class="space-y-8">
                    @foreach($inventoryBlocks as $block)
                    <div>
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-base font-black text-gray-900">
                                <span class="text-{{ $block['accent'] }}-600">{{ $block['model']->name }}</span>
                                <span class="text-xs font-bold text-gray-400 ml-1">の販売中車両（安い順）</span>
                            </h3>
                            <a href="{{ route('bikes.search', ['bike_model_id' => $block['model']->id]) }}" class="text-xs font-bold text-{{ $block['accent'] }}-600 hover:underline shrink-0">すべて見る</a>
                        </div>

                        @if(!empty($block['items']))
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            @foreach($block['items'] as $card)
                                {{-- お得バッジは既知の計算バグ保護のため比較ページでは必ず非表示 --}}
                                @include('bikes.partials.bike_card', ['listing' => $card, 'hideBargainBadge' => true])
                            @endforeach
                        </div>
                        @else
                        <p class="text-sm font-bold text-gray-400 bg-gray-50 rounded-xl py-6 text-center">現在、販売中の在庫はありません。</p>
                        @endif

                        {{-- 入荷・値下げ通知CTA（push-manager.js が描画。bike_model_id 紐付け） --}}
                        <div class="mt-4" id="push-area-compare-{{ $block['model']->id }}"
                             data-model-id="{{ $block['model']->id }}"
                             data-model-name="{{ $block['model']->name }}"></div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- よくある質問（スペック/価格から機械生成。FAQPage JSON-LDと内容一致） --}}
        @if(!empty($faq))
        <div class="bg-white border-b border-gray-100">
            <div class="max-w-5xl mx-auto px-4 py-8">
                <h2 class="text-lg font-black text-gray-900 mb-5 flex items-center gap-2">
                    <i data-lucide="help-circle" class="w-5 h-5 text-teal-500"></i>
                    よくある質問
                </h2>
                <div class="space-y-3">
                    @foreach($faq as $item)
                    <div class="bg-gray-50 rounded-2xl p-5 border border-gray-100">
                        <p class="font-black text-gray-900 text-sm flex items-start gap-2">
                            <span class="text-teal-500 font-black shrink-0">Q.</span>
                            <span>{{ $item['q'] }}</span>
                        </p>
                        <p class="text-sm text-gray-600 leading-relaxed mt-2 flex items-start gap-2">
                            <span class="text-gray-400 font-black shrink-0">A.</span>
                            <span>{{ $item['a'] }}</span>
                        </p>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif

        {{-- 関連比較 --}}
        @if(!empty($relatedComparisons))
        <div class="bg-white border-b border-gray-100">
            <div class="max-w-5xl mx-auto px-4 py-8">
                <h2 class="text-lg font-black text-gray-900 mb-5 flex items-center gap-2">
                    <i data-lucide="git-compare" class="w-5 h-5 text-indigo-500"></i>
                    関連する比較
                </h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    @foreach($relatedComparisons as $related)
                    <a href="{{ $related['url'] }}" class="flex items-center justify-between bg-gray-50 hover:bg-gray-100 rounded-xl p-4 transition-colors group">
                        <span class="font-bold text-sm text-gray-700 group-hover:text-indigo-600 transition-colors">{{ $related['label'] }}</span>
                        <i data-lucide="chevron-right" class="w-4 h-4 text-gray-400"></i>
                    </a>
                    @endforeach
                </div>
            </div>
        </div>
        @endif

        {{-- 比較一覧（ハブ）へ戻る --}}
        <div class="max-w-5xl mx-auto px-4 py-8">
            <a href="{{ route('bikes.model_compare_hub') }}" class="inline-flex items-center gap-2 text-sm font-bold text-gray-500 hover:text-indigo-600 transition-colors">
                <i data-lucide="arrow-left" class="w-4 h-4"></i>
                車種比較一覧へ戻る
            </a>
        </div>

        {{-- JSON-LD 構造化データ --}}
        <script type="application/ld+json">
        {
            "@@context": "https://schema.org",
            "@@type": "WebPage",
            "name": "{{ $model1->name }} vs {{ $model2->name }} 徹底比較",
            "description": "{{ $model1->name }}と{{ $model2->name }}のスペック・中古相場を徹底比較",
            "breadcrumb": {
                "@@type": "BreadcrumbList",
                "itemListElement": [
                    { "@@type": "ListItem", "position": 1, "name": "HOME", "item": "{{ url('/') }}" },
                    { "@@type": "ListItem", "position": 2, "name": "車種一覧", "item": "{{ route('bikes.models') }}" },
                    { "@@type": "ListItem", "position": 3, "name": "{{ $model1->name }} vs {{ $model2->name }}" }
                ]
            }
        }
        </script>

        {{-- FAQPage 構造化データ（表示中のFAQと完全一致の内容のみ）。WebPage/BreadcrumbListとは別ブロックで衝突なし --}}
        @if(!empty($faq))
        <script type="application/ld+json">
        @php
            echo json_encode([
                '@context' => 'https://schema.org',
                '@type' => 'FAQPage',
                'mainEntity' => array_map(fn ($f) => [
                    '@type' => 'Question',
                    'name' => $f['q'],
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => $f['a'],
                    ],
                ], $faq),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        @endphp
        </script>
        @endif
    </div>
</x-layout>
