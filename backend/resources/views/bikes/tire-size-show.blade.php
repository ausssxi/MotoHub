<x-layout>
    @php
        $size = $data['size'];
        $totalModels = (int) $data['total_models'];
        $withStock = (int) $data['models_with_stock'];
        $stockTotal = (int) $data['stock_total'];
        $price = $data['price']; // null または ['min','max','avg']（すべて円・int）
        $sampleNames = $data['sample_names'] ?? [];

        // title: 在庫0台のときは在庫の部分を出さない。
        $pageTitle = 'タイヤサイズ'.$size.'の適合車種一覧'
            .($stockTotal > 0 ? '｜中古在庫'.number_format($stockTotal).'台' : '')
            .' - MotoHub';

        // meta description: 車種数・在庫台数・代表車種名を含め、サイズごとに変わる文にする。
        $sampleText = ! empty($sampleNames) ? '（'.implode('・', $sampleNames).'ほか）' : '';
        $pageDescription = 'タイヤサイズ'.$size.'を前輪に装着するバイク'.number_format($totalModels).'車種'.$sampleText.'を掲載。'
            .($stockTotal > 0
                ? 'うち'.number_format($withStock).'車種・中古在庫'.number_format($stockTotal).'台を価格順に比較できます。'
                : '純正装着サイズが同じ車種をまとめて確認できます。');
    @endphp
    <x-slot:title>{{ $pageTitle }}</x-slot:title>
    <x-slot:metaDescription>{{ $pageDescription }}</x-slot:metaDescription>

    <x-slot:styles>
        <script type="application/ld+json">
        {
            "@@context": "https://schema.org",
            "@@type": "BreadcrumbList",
            "itemListElement": [
                {"@@type": "ListItem", "position": 1, "name": "HOME", "item": "{{ url('/') }}"},
                {"@@type": "ListItem", "position": 2, "name": "バイク検索", "item": "{{ route('bikes.search') }}"},
                {"@@type": "ListItem", "position": 3, "name": "タイヤサイズ一覧", "item": "{{ route('bikes.tire_size.index') }}"},
                {"@@type": "ListItem", "position": 4, "name": "{{ $size }}", "item": "{{ url()->current() }}"}
            ]
        }
        </script>
    </x-slot:styles>

    <x-slot:navigation>
        <x-navigation :showSearch="true" />
    </x-slot:navigation>

    <div class="bg-gray-50 min-h-screen">
        <div class="bg-white border-b border-gray-200 pt-8 pb-10 px-4">
            <div class="max-w-7xl mx-auto">
                {{-- パンくずリスト --}}
                <nav class="flex text-xs font-bold text-gray-400 mb-6" aria-label="Breadcrumb">
                    <ol class="flex items-center space-x-2">
                        <li><a href="/" class="hover:text-gray-600 transition-colors">HOME</a></li>
                        <li><span class="text-gray-300">＞</span></li>
                        <li><a href="{{ route('bikes.search') }}" class="hover:text-gray-600 transition-colors">バイク検索</a></li>
                        <li><span class="text-gray-300">＞</span></li>
                        <li><a href="{{ route('bikes.tire_size.index') }}" class="hover:text-gray-600 transition-colors">タイヤサイズ一覧</a></li>
                        <li><span class="text-gray-300">＞</span></li>
                        <li><span class="text-gray-800">{{ $size }}</span></li>
                    </ol>
                </nav>

                {{-- 1. h1 --}}
                <h1 class="text-2xl sm:text-3xl font-black text-gray-900 mb-3 tracking-tight">タイヤサイズ {{ $size }} を装着する車種</h1>

                {{-- 2. 車種数・在庫のある車種数 --}}
                <p class="text-sm text-gray-600">
                    このサイズを前輪に装着する車種は<strong class="text-gray-900">{{ number_format($totalModels) }}車種</strong>、
                    うち中古在庫があるのは<strong class="text-gray-900">{{ number_format($withStock) }}車種</strong>です。
                </p>

                {{-- 3. 在庫台数・価格帯（在庫0台なら価格の行は出さない） --}}
                @if($stockTotal > 0)
                <div class="mt-4 grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <div class="bg-gray-50 rounded-xl p-3"><p class="text-[10px] font-bold text-gray-400">中古在庫</p><p class="text-sm font-black text-gray-900">{{ number_format($stockTotal) }}台</p></div>
                    @if($price)
                    <div class="bg-gray-50 rounded-xl p-3"><p class="text-[10px] font-bold text-gray-400">最低価格</p><p class="text-sm font-black text-gray-900">{{ number_format((int) $price['min']) }}円</p></div>
                    <div class="bg-gray-50 rounded-xl p-3"><p class="text-[10px] font-bold text-gray-400">最高価格</p><p class="text-sm font-black text-gray-900">{{ number_format((int) $price['max']) }}円</p></div>
                    <div class="bg-gray-50 rounded-xl p-3"><p class="text-[10px] font-bold text-gray-400">平均価格</p><p class="text-sm font-black text-gray-900">{{ number_format((int) $price['avg']) }}円</p></div>
                    @endif
                </div>
                <p class="text-[10px] text-gray-400 mt-2">※価格は支払総額（車両本体価格＋諸費用込み）の目安です。</p>
                @endif
            </div>
        </div>

        <div class="max-w-7xl mx-auto px-4 py-8 space-y-8">
            {{-- 4. 車種一覧（在庫あり優先→車種名昇順・最大60件） --}}
            <div>
                <h2 class="text-lg font-black text-gray-900 mb-4">{{ $size }} を履く車種</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                    @foreach($data['items'] as $item)
                    <a href="{{ route('bikes.model_detail', ['mfrSlug' => $item['mfr_slug'], 'modelSlug' => $item['model_slug']]) }}"
                       class="flex items-center justify-between rounded-xl bg-white border border-gray-100 p-3 hover:shadow-md transition-shadow">
                        <span class="min-w-0">
                            @if($item['manufacturer'] !== '')<span class="block text-[10px] font-bold text-gray-400">{{ $item['manufacturer'] }}</span>@endif
                            <span class="block text-sm font-black text-gray-900 truncate">{{ $item['name'] }}</span>
                        </span>
                        @if((int) $item['stock'] > 0)<span class="shrink-0 text-[11px] font-bold text-green-600">在庫{{ number_format((int) $item['stock']) }}台</span>@endif
                    </a>
                    @endforeach
                </div>
            </div>

            {{-- 5. 後輪サイズの組み合わせ（リンクにしない・最大10件） --}}
            @if(! empty($data['rear']))
            <div>
                <h2 class="text-lg font-black text-gray-900 mb-4">前輪 {{ $size }} の車種が履く後輪サイズ</h2>
                <div class="flex flex-wrap gap-2">
                    @foreach($data['rear'] as $r)
                    <span class="inline-flex items-center gap-2 rounded-full bg-white border border-gray-100 px-3 py-1.5">
                        <span class="text-sm font-black text-gray-900">{{ $r['size'] }}</span>
                        <span class="text-[11px] font-bold text-gray-400">{{ number_format((int) $r['count']) }}車種</span>
                    </span>
                    @endforeach
                </div>
            </div>
            @endif
        </div>
    </div>
</x-layout>
