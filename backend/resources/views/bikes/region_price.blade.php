<x-layout>
    <x-slot:title>{{ $model->name }}の中古価格 エリア別相場・地域差 | MotoHub</x-slot:title>
    <x-slot:metaDescription>{{ $model->name }}の中古相場を全国8エリアの中央値で比較。最安は{{ $region['spread']['low']['block'] }}（約{{ $region['spread']['low']['median_man'] }}万円）、最高は{{ $region['spread']['high']['block'] }}（約{{ $region['spread']['high']['median_man'] }}万円）、約{{ $region['spread']['diff_man'] }}万円（{{ $region['spread']['pct'] }}%）の地域差。安く買えるエリアをチェック。</x-slot:metaDescription>

    <x-slot:navigation>
        <x-navigation :showSearch="true" />
    </x-slot:navigation>

    @php
        $sp = $region['spread'];
        $robustRegions = collect($region['regions'])->where('robust', true)->sortBy('median')->values();
    @endphp

    <div class="bg-gray-50 min-h-screen">
        {{-- ヘッダー --}}
        <div class="bg-white border-b border-gray-200 pt-8 pb-10 px-4">
            <div class="max-w-4xl mx-auto">
                <nav class="flex text-xs font-bold text-gray-400 mb-6" aria-label="Breadcrumb">
                    <ol class="flex items-center space-x-2">
                        <li><a href="/" class="hover:text-gray-600 transition-colors">HOME</a></li>
                        <li><span class="text-gray-300">＞</span></li>
                        <li><a href="{{ route('bikes.region_price_index') }}" class="hover:text-gray-600 transition-colors">エリア別相場</a></li>
                        <li><span class="text-gray-300">＞</span></li>
                        <li><span class="text-gray-800">{{ $model->name }}</span></li>
                    </ol>
                </nav>

                <h1 class="text-2xl sm:text-3xl font-black text-gray-900 mb-3 tracking-tight">
                    {{ $model->name }}の中古価格<span class="text-lg text-gray-400 ml-2">エリア別相場・地域差</span>
                </h1>

                @if(!empty($region['spread_narrative']))
                <p class="text-sm text-gray-600 leading-relaxed max-w-3xl">{{ $region['spread_narrative'] }}</p>
                @endif
            </div>
        </div>

        <div class="max-w-4xl mx-auto px-4 py-8 space-y-8">
            {{-- 最安/最高エリアのハイライト --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="bg-blue-50 rounded-2xl p-5 border border-blue-100">
                    <div class="text-xs font-bold text-blue-600 mb-1">最安エリア</div>
                    <div class="text-lg font-black text-gray-900">{{ $sp['low']['block'] }}</div>
                    <div class="text-2xl font-black text-blue-600 mt-1">約{{ $sp['low']['median_man'] }}<span class="text-sm text-gray-400 ml-0.5">万円</span></div>
                </div>
                <div class="bg-orange-50 rounded-2xl p-5 border border-orange-100">
                    <div class="text-xs font-bold text-orange-600 mb-1">最高エリア</div>
                    <div class="text-lg font-black text-gray-900">{{ $sp['high']['block'] }}</div>
                    <div class="text-2xl font-black text-orange-600 mt-1">約{{ $sp['high']['median_man'] }}<span class="text-sm text-gray-400 ml-0.5">万円</span></div>
                </div>
            </div>
            <p class="text-sm text-gray-500 -mt-4">
                最安と最高で約<strong class="text-gray-900">{{ $sp['diff_man'] }}万円（{{ $sp['pct'] }}%）</strong>の開きがあります。
            </p>

            {{-- エリア別中央値表（中央値昇順・件数併記で信頼性を可視化） --}}
            <div class="bg-white rounded-2xl shadow-sm p-6 border border-gray-100">
                <h2 class="text-lg font-black text-gray-900 mb-5 flex items-center gap-2">
                    <i data-lucide="map" class="w-5 h-5 text-green-500"></i>
                    {{ $model->name }} エリア別 中古相場（中央値）
                </h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b-2 border-gray-200">
                                <th class="text-left py-2.5 px-3 font-bold text-gray-500">エリア（安い順）</th>
                                <th class="text-right py-2.5 px-3 font-bold text-gray-500">中央値</th>
                                <th class="text-right py-2.5 px-3 font-bold text-gray-500">掲載台数</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($robustRegions as $r)
                            <tr>
                                <td class="py-2.5 px-3 font-bold text-gray-700">{{ $r['block'] }}</td>
                                <td class="py-2.5 px-3 text-right font-black text-blue-600">{{ $r['median_man'] }}<span class="text-xs text-gray-400 ml-0.5">万円</span></td>
                                <td class="py-2.5 px-3 text-right font-bold text-gray-500">{{ number_format($r['count']) }}<span class="text-xs text-gray-400 ml-0.5">台</span></td>
                            </tr>
                            @endforeach
                            @if(!empty($region['national']))
                            <tr class="bg-gray-50">
                                <td class="py-2.5 px-3 font-black text-gray-800">全国</td>
                                <td class="py-2.5 px-3 text-right font-black text-gray-900">{{ $region['national']['median_man'] }}<span class="text-xs text-gray-400 ml-0.5">万円</span></td>
                                <td class="py-2.5 px-3 text-right font-bold text-gray-600">{{ number_format($region['national']['count']) }}<span class="text-xs text-gray-400 ml-0.5">台</span></td>
                            </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
                <p class="text-[10px] text-gray-400 mt-4">※支払総額（total_price）の中央値。掲載台数20台以上のエリアのみ表示。1販売店あたりの寄与は最大5台に制限して算出。</p>
            </div>

            {{-- 在庫導線 + 通知CTA --}}
            <div class="bg-white rounded-2xl shadow-sm p-6 border border-gray-100">
                <h2 class="text-base font-black text-gray-900 mb-4">{{ $model->name }} を探す</h2>
                <div class="flex flex-col sm:flex-row gap-3">
                    <a href="{{ route('bikes.search', ['bike_model_id' => $model->id]) }}" class="inline-flex items-center justify-center gap-2 bg-gray-900 hover:bg-gray-800 text-white font-bold text-sm px-5 py-3 rounded-xl transition-colors">
                        <i data-lucide="search" class="w-4 h-4"></i> {{ $model->name }} の在庫一覧
                    </a>
                    {{-- 値下げ・入荷通知CTA（push-manager.js が描画・bike_model_id紐付け） --}}
                    <div class="flex-1" id="push-area-spread-{{ $model->id }}" data-model-id="{{ $model->id }}" data-model-name="{{ $model->name }}"></div>
                </div>
            </div>

            {{-- モデル詳細への相互リンク --}}
            <a href="{{ url($model->seo_url) }}" class="flex items-center justify-between bg-white hover:bg-gray-50 rounded-2xl p-5 border border-gray-100 shadow-sm transition-colors group">
                <div>
                    <div class="font-black text-gray-900 group-hover:text-blue-600 transition-colors">{{ $model->name }} の詳細・スペック・相場推移</div>
                    <div class="text-xs font-bold text-gray-400 mt-1">カタログ情報・買取相場・レビュー</div>
                </div>
                <i data-lucide="chevron-right" class="w-5 h-5 text-gray-400"></i>
            </a>
        </div>

        {{-- JSON-LD（WebPage + BreadcrumbList。価格の過剰主張はしない） --}}
        <script type="application/ld+json">
        {
            "@@context": "https://schema.org",
            "@@type": "WebPage",
            "name": "{{ $model->name }}の中古価格 エリア別相場・地域差",
            "description": "{{ $model->name }}の中古相場を全国8エリアの中央値で比較",
            "breadcrumb": {
                "@@type": "BreadcrumbList",
                "itemListElement": [
                    { "@@type": "ListItem", "position": 1, "name": "HOME", "item": "{{ url('/') }}" },
                    { "@@type": "ListItem", "position": 2, "name": "エリア別相場", "item": "{{ route('bikes.region_price_index') }}" },
                    { "@@type": "ListItem", "position": 3, "name": "{{ $model->name }}" }
                ]
            }
        }
        </script>
    </div>
</x-layout>
