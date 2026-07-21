<x-layout>
    <x-slot:title>バイク盗難（オートバイ盗）全国データと推移・盗難対策｜MotoHub</x-slot:title>
    <x-slot:metaDescription>警察庁の犯罪統計（オートバイ盗）をもとに、バイク盗難の全国の認知件数・検挙率・年次推移と、効果的な盗難対策をMotoHubがまとめました。</x-slot:metaDescription>
    <x-slot:canonical>{{ route('theft') }}</x-slot:canonical>

    @php
        $breadcrumb = [
            '@context' => 'https://schema.org', '@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'トップ', 'item' => url('/')],
                ['@type' => 'ListItem', 'position' => 2, 'name' => 'バイク盗難データ', 'item' => route('theft')],
            ],
        ];
        $faqSchema = [
            '@context' => 'https://schema.org', '@type' => 'FAQPage',
            'mainEntity' => collect($faqs)->map(fn ($f) => [
                '@type' => 'Question', 'name' => $f['q'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']],
            ])->all(),
        ];
        $ctaUrl = $affiliate['url'] ?? '';
    @endphp
    <script type="application/ld+json">{!! json_encode($breadcrumb, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    <script type="application/ld+json">{!! json_encode($faqSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>

    <div class="max-w-3xl mx-auto px-4 py-8 sm:py-12">
        <header class="mb-8">
            <h1 class="text-2xl sm:text-3xl font-black text-gray-900 leading-tight">バイクの盗難データ（オートバイ盗・全国）と対策</h1>
            <p class="text-sm text-gray-500 mt-3 leading-relaxed">警察庁の犯罪統計（オートバイ盗）をもとに、全国の認知件数・検挙率・年次推移を淡々とまとめています。数字はそのまま掲載し、過度に不安を煽らないことを方針としています。</p>
            @if($hasData)
            <p class="text-[11px] text-gray-400 font-bold mt-2">最終確認 {{ $source['checked_at'] }}</p>
            @endif
        </header>

        @if($hasData && $latest)
            {{-- 最新年サマリ --}}
            <section class="mb-8 grid grid-cols-3 gap-3">
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 text-center">
                    <p class="text-[11px] font-bold text-gray-400 mb-1">認知件数（{{ $latest['year'] }}年）</p>
                    <p class="text-2xl font-black text-red-600 leading-none">{{ number_format($latest['recognized']) }}<span class="text-xs text-gray-400 font-bold ml-0.5">件</span></p>
                </div>
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 text-center">
                    <p class="text-[11px] font-bold text-gray-400 mb-1">検挙率</p>
                    <p class="text-2xl font-black text-gray-900 leading-none">{{ $latest['clearance_rate'] !== null ? $latest['clearance_rate'] : '-' }}<span class="text-xs text-gray-400 font-bold ml-0.5">%</span></p>
                </div>
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 text-center">
                    <p class="text-[11px] font-bold text-gray-400 mb-1">前年比</p>
                    @if($latest['yoy_pct'] !== null)
                    <p class="text-2xl font-black leading-none {{ $latest['yoy_pct'] <= 0 ? 'text-emerald-600' : 'text-red-600' }}">{{ ($latest['yoy_pct'] > 0 ? '+' : '') . number_format($latest['yoy_pct'], 1) }}<span class="text-xs text-gray-400 font-bold ml-0.5">%</span></p>
                    @else
                    <p class="text-2xl font-black text-gray-300 leading-none">-</p>
                    @endif
                </div>
            </section>

            <p class="text-sm text-gray-600 leading-relaxed mb-8">
                {{ $latest['year'] }}年のオートバイ盗の全国の認知件数は
                <span class="font-black text-red-600">{{ number_format($latest['recognized']) }}件</span>、
                検挙件数は <span class="font-bold text-gray-900">{{ number_format($latest['cleared']) }}件</span>
                （検挙率 {{ $latest['clearance_rate'] !== null ? $latest['clearance_rate'].'%' : '-' }}）でした。
                @if($latest['yoy_pct'] !== null)
                前年からの増減は <span class="font-bold {{ $latest['yoy_pct'] <= 0 ? 'text-emerald-600' : 'text-red-600' }}">{{ ($latest['yoy_pct'] > 0 ? '+' : '') . number_format($latest['yoy_pct'], 1) }}%</span> です。
                @endif
            </p>

            {{-- 年次推移グラフ --}}
            @if(count($series) >= 2)
            <section class="mb-8 bg-white rounded-3xl shadow-sm border border-gray-100 p-6">
                <h2 class="text-base font-black text-gray-900 mb-3">オートバイ盗 認知件数の推移（{{ $series[0]['year'] }}〜{{ end($series)['year'] }}年・全国）</h2>
                @include('bikes.partials.sparkline', ['points' => array_column($series, 'recognized'), 'w' => 640, 'h' => 140, 'color' => '#dc2626', 'label' => 'オートバイ盗（全国）の認知件数の推移'])
                <div class="flex justify-between text-[11px] text-gray-400 font-bold mt-1">
                    <span>{{ $series[0]['year'] }}年 {{ number_format($series[0]['recognized']) }}件</span>
                    <span>{{ end($series)['year'] }}年 {{ number_format(end($series)['recognized']) }}件</span>
                </div>
            </section>
            @endif
        @else
            <section class="mb-8 bg-white rounded-3xl shadow-sm border border-gray-100 p-6">
                <p class="text-sm text-gray-500">統計データは準備中です。公開までしばらくお待ちください。</p>
            </section>
        @endif

        {{-- 盗難対策の一般論（淡々と） --}}
        <section class="mb-8 bg-white rounded-3xl shadow-sm border border-gray-100 p-6">
            <h2 class="text-base font-black text-gray-900 mb-3">バイクの盗難対策の基本</h2>
            <ul class="space-y-2 text-sm text-gray-600 leading-relaxed list-disc pl-5">
                <li><span class="font-bold text-gray-800">地球ロック</span>：動かせない構造物（頑丈な支柱等）とフレームを施錠し、車体ごと持ち去られるのを防ぐ。</li>
                <li><span class="font-bold text-gray-800">複数ロックの併用</span>：チェーン＋ディスクロック等、種類の異なる鍵で解錠の手間を増やす。</li>
                <li><span class="font-bold text-gray-800">保管場所</span>：屋内・防犯カメラのある駐輪場・人目のある場所を選ぶ。</li>
                <li><span class="font-bold text-gray-800">アラーム・車体カバー</span>：振動アラームやカバーで物色対象になりにくくする。</li>
                <li><span class="font-bold text-gray-800">保険での備え</span>：任意保険の車両補償や専用の盗難保険も選択肢。</li>
            </ul>
        </section>

        {{-- 盗難保険CTA（★affiliate.url 設定時のみ・PR表記付き。未設定なら偽ボタンを出さない） --}}
        @if(!empty($ctaUrl))
        <section class="mb-8 bg-gray-900 rounded-3xl p-6 text-center">
            <p class="text-white/70 text-[10px] font-black tracking-widest uppercase mb-2">PR</p>
            <h2 class="text-white text-lg font-black mb-1">{{ $affiliate['headline'] }}</h2>
            <p class="text-white/60 text-xs mb-4 leading-relaxed">{{ $affiliate['sub'] }}</p>
            <a href="{{ $ctaUrl }}" target="_blank" rel="nofollow sponsored noopener"
               class="inline-flex items-center gap-2 bg-white text-gray-900 font-black text-sm px-6 py-3 rounded-full hover:bg-gray-100 transition-colors">
                <i data-lucide="shield-check" class="w-4 h-4"></i>公式サイトで詳細を見る
            </a>
            @if(!empty($affiliate['provider']))
            <p class="text-white/50 text-[10px] font-bold mt-3">提供: {{ $affiliate['provider'] }}・PR</p>
            @endif
        </section>
        @endif

        {{-- FAQ --}}
        <section class="mb-8">
            <h2 class="text-base font-black text-gray-900 mb-3">よくある質問</h2>
            <div class="space-y-3">
                @foreach($faqs as $f)
                <div class="bg-white rounded-2xl border border-gray-100 p-4">
                    <p class="text-sm font-bold text-gray-900 mb-1">{{ $f['q'] }}</p>
                    <p class="text-[13px] text-gray-600 leading-relaxed">{{ $f['a'] }}</p>
                </div>
                @endforeach
            </div>
        </section>

        {{-- 出典・相互リンク --}}
        @if($hasData)
        <p class="text-[10px] text-gray-400 mb-4">出典: <a href="{{ $source['url'] }}" target="_blank" rel="nofollow noopener" class="underline hover:text-gray-600">{{ $source['label'] }}</a>（最終確認: {{ $source['checked_at'] }}）。検挙率・前年比は認知・検挙件数から算出。</p>
        @endif
        <nav class="flex flex-wrap gap-3 text-xs font-bold">
            <a href="{{ route('hoken') }}" class="inline-flex items-center gap-1.5 text-gray-500 hover:text-black"><i data-lucide="shield" class="w-3.5 h-3.5"></i>バイク保険・維持費</a>
            <a href="{{ route('bikes.prefectures') }}" class="inline-flex items-center gap-1.5 text-gray-500 hover:text-black"><i data-lucide="map-pin" class="w-3.5 h-3.5"></i>地域から中古バイクを探す</a>
        </nav>

        <p class="text-[11px] text-gray-400 leading-relaxed mt-6">本ページは公的統計（警察庁犯罪統計）に基づく情報提供を目的としています。統計値は出典のとおり掲載し、独自の推測値は作成していません。盗難保険は商品により補償・保険料が異なるため、加入検討時は各社の公式情報をご確認ください。</p>
    </div>
</x-layout>
