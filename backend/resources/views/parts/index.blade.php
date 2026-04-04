<x-layout>
    <x-slot:title>バイクパーツ検索 | MotoHub</x-slot:title>

    <x-slot:metaDescription>バイクパーツの価格を楽天・Yahoo・Amazonで一括比較。マフラー・タイヤ・ブレーキパッドなど10カテゴリの選び方ガイド付き。車種別・ブランド別でも検索可能。</x-slot:metaDescription>

    <x-slot:navigation>
        <x-navigation :showSearch="true" />
    </x-slot:navigation>

    <div class="bg-gray-50 min-h-screen">
        {{-- ヘッ���ー --}}
        <section class="bg-gradient-to-r from-gray-900 to-gray-800 text-white py-10">
            <div class="max-w-5xl mx-auto px-4 text-center">
                <h1 class="text-2xl sm:text-3xl font-black mb-2">バイクパーツ検索</h1>
                <p class="text-gray-300 text-sm">楽天・Yahoo!・Amazonの価格を一括比較。最安値のパーツを見つけよう。</p>
            </div>
        </section>

        {{-- 検索フォーム --}}
        <section id="search-form-section" class="max-w-5xl mx-auto px-4 -mt-6">
            <form id="parts-search-form" class="bg-white rounded-xl shadow-lg p-5 sm:p-6">
                <div id="form-error" class="hidden mb-4 bg-red-50 border border-red-200 text-red-700 rounded-lg p-3 text-sm font-bold"></div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
                    <div>
                        <label for="keyword" class="block text-xs font-bold text-gray-600 mb-1">キーワード</label>
                        <input type="text" id="keyword" name="keyword" placeholder="例: マフラー、ブレーキパッド"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                    </div>
                    <div class="relative">
                        <label for="bike" class="block text-xs font-bold text-gray-600 mb-1">車種名</label>
                        <input type="text" id="bike" name="bike" placeholder="例: CBR250RR、YZF-R25" autocomplete="off"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        <ul id="suggest-list" class="hidden absolute z-50 left-0 right-0 top-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-60 overflow-y-auto"></ul>
                    </div>
                    <div>
                        <label for="category" class="block text-xs font-bold text-gray-600 mb-1">パーツカテゴリ（任意）</label>
                        <select id="category" name="category"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none bg-white">
                            <option value="">すべて</option>
                            <option value="マフラー">マフラー</option>
                            <option value="タイヤ">タイヤ</option>
                            <option value="ブレーキ">ブレーキ</option>
                            <option value="シート">シート</option>
                            <option value="ミラー">ミラー</option>
                            <option value="カウル">カウル</option>
                            <option value="チェーン">チェーン</option>
                            <option value="サスペンション">サスペンション</option>
                            <option value="オイル">オイル</option>
                            <option value="バッテリー">バッテリー</option>
                            <option value="ヘルメット">ヘルメット</option>
                            <option value="グローブ">グローブ</option>
                            <option value="ジャケット">ジャケット</option>
                        </select>
                    </div>
                </div>
                <button type="submit" id="search-btn"
                    class="w-full sm:w-auto bg-blue-600 hover:bg-blue-700 text-white font-bold text-sm px-8 py-2.5 rounded-lg transition-colors flex items-center justify-center gap-2 mx-auto">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                    検索する
                </button>
            </form>
        </section>

        {{-- 初期コンテンツ（検索実行時に非表示） --}}
        <div id="initial-content">

            {{-- 車種から探す --}}
            <section class="max-w-5xl mx-auto px-4 mt-8" x-data="{ maker: 'honda' }">
                <h2 class="text-lg font-black text-gray-800 mb-4">車種からパーツを探す</h2>
                @php
                    $makers = [
                        'honda' => ['label' => 'ホンダ', 'bikes' => ['CBR250RR', 'PCX', 'レブル250', 'スーパーカブ', 'モンキー125', 'CT125', 'CB400SF', 'CRF250L']],
                        'yamaha' => ['label' => 'ヤマハ', 'bikes' => ['YZF-R25', 'MT-07', 'セロー250', 'TMAX', 'ジョグ', 'シグナス', 'XSR700', 'テネレ700']],
                        'suzuki' => ['label' => 'スズキ', 'bikes' => ['GSX-R125', 'Vストローム250', 'ジクサー', 'アドレスV125', 'バーグマン', 'GSX250R']],
                        'kawasaki' => ['label' => 'カワサキ', 'bikes' => ['Ninja250', 'Z900RS', 'Ninja400', 'Z400', 'KLX230', 'W800', 'ZX-25R']],
                    ];
                @endphp
                <div class="flex flex-wrap gap-2 mb-4">
                    @foreach($makers as $key => $maker)
                    <button type="button"
                        x-on:click="maker = '{{ $key }}'"
                        x-bind:class="maker === '{{ $key }}' ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-700 border-gray-200 hover:border-blue-300 hover:text-blue-600'"
                        class="px-4 py-2 text-sm font-bold rounded-full border transition-colors">
                        {{ $maker['label'] }}
                    </button>
                    @endforeach
                </div>
                @foreach($makers as $key => $maker)
                <div x-show="maker === '{{ $key }}'" x-cloak class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                    @foreach($maker['bikes'] as $bike)
                    <a href="{{ route('parts.index', ['keyword' => $bike]) }}"
                       class="bg-white rounded-lg border border-gray-100 px-3 py-2.5 text-sm font-bold text-gray-700 hover:bg-blue-50 hover:border-blue-200 hover:text-blue-600 transition-colors text-center">
                        {{ $bike }}
                    </a>
                    @endforeach
                </div>
                @endforeach
            </section>

            {{-- カテゴリから探す --}}
            <section class="max-w-5xl mx-auto px-4 mt-8">
                <h2 class="text-lg font-black text-gray-800 mb-4">カテゴリから探す</h2>
                @php
                    $categoryCards = [
                        ['slug' => 'muffler', 'icon' => '&#128295;', 'name' => 'マフラー', 'desc' => '音と走りを変える'],
                        ['slug' => 'tire', 'icon' => '&#128710;', 'name' => 'タイヤ', 'desc' => '安全の基本'],
                        ['slug' => 'brake-pad', 'icon' => '&#128721;', 'name' => 'ブレーキパッド', 'desc' => '効きが命'],
                        ['slug' => 'oil', 'icon' => '&#128738;', 'name' => 'エンジンオイル', 'desc' => 'エンジンを守る'],
                        ['slug' => 'chain', 'icon' => '&#9939;', 'name' => 'チェーン・スプロケ', 'desc' => '駆動の要'],
                        ['slug' => 'battery', 'icon' => '&#128267;', 'name' => 'バッテリー', 'desc' => '始動の必需品'],
                        ['slug' => 'helmet', 'icon' => '&#9937;', 'name' => 'ヘルメット', 'desc' => '頭を守る'],
                        ['slug' => 'gloves', 'icon' => '&#129508;', 'name' => 'グローブ', 'desc' => '手を守る'],
                        ['slug' => 'phone-mount', 'icon' => '&#128241;', 'name' => 'スマホホルダー', 'desc' => 'ナビを快適に'],
                        ['slug' => 'drive-recorder', 'icon' => '&#128249;', 'name' => 'ドラレコ', 'desc' => '安全の証拠'],
                    ];
                @endphp
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                    @foreach($categoryCards as $cat)
                    <a href="{{ route('parts.category', $cat['slug']) }}"
                       class="bg-white rounded-xl border border-gray-100 p-4 hover:shadow-md hover:border-blue-200 transition-all group text-center">
                        <div class="text-2xl mb-1">{!! $cat['icon'] !!}</div>
                        <p class="text-sm font-black text-gray-800 group-hover:text-blue-600 transition-colors">{{ $cat['name'] }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">{{ $cat['desc'] }}</p>
                    </a>
                    @endforeach
                </div>
            </section>

            {{-- 人気ブランドから探す --}}
            <section class="max-w-5xl mx-auto px-4 mt-8">
                <h2 class="text-lg font-black text-gray-800 mb-4">人気ブランドから探す</h2>
                @php
                    $brands = [
                        'ヨシムラ', 'デイトナ', 'キタコ', 'モリワキ', 'コミネ', 'RSタイチ', 'ショウエイ',
                        'アライ', 'ブリヂストン', 'ダンロップ', 'DID', 'RK', 'カストロール', 'ワコーズ',
                    ];
                @endphp
                <div class="flex flex-wrap gap-2">
                    @foreach($brands as $brand)
                    <a href="{{ route('parts.index', ['keyword' => $brand]) }}"
                       class="inline-flex items-center px-4 py-2 bg-white border border-gray-200 rounded-full text-sm font-bold text-gray-700 hover:bg-blue-50 hover:border-blue-300 hover:text-blue-600 transition-colors shadow-sm">
                        {{ $brand }}
                    </a>
                    @endforeach
                </div>
            </section>

            {{-- 人気商品セクション --}}
            @if(!empty($popularItems))
            <section id="popular-items" class="max-w-5xl mx-auto px-4 mt-8 mb-8">
                @php
                    $categoryEmojis = ['マフラー' => '&#128295;', 'タイヤ' => '&#128710;', 'チェーン' => '&#9939;'];
                @endphp
                @foreach($popularItems as $category => $items)
                    @if(count($items) > 0)
                    <div class="mb-8">
                        <h2 class="text-lg font-black text-gray-800 mb-4">&#127942; {{ $category }}の売れ筋ランキング</h2>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                            @foreach($items as $idx => $item)
                            @php $rank = $idx + 1; @endphp
                            <div class="bg-white rounded-xl shadow hover:shadow-lg transition-shadow overflow-hidden flex flex-col h-full relative">
                                <span class="absolute top-2 left-2 z-10 w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold {{ $rank === 1 ? 'bg-yellow-400 text-white' : ($rank === 2 ? 'bg-gray-400 text-white' : ($rank === 3 ? 'bg-amber-600 text-white' : 'bg-gray-200 text-gray-600')) }}">{{ $rank }}</span>
                                <div class="aspect-square bg-gray-100 flex items-center justify-center overflow-hidden">
                                    @if(!empty($item['image']))
                                        <img src="{{ str_replace('?_ex=128x128', '?_ex=300x300', $item['image']) }}" alt="{{ $item['name'] }}" class="w-full h-full object-contain p-2" loading="lazy">
                                    @else
                                        <div class="text-gray-300 text-4xl">&#128295;</div>
                                    @endif
                                </div>
                                <div class="p-3 flex flex-col flex-grow">
                                    <h3 class="text-xs font-bold text-gray-800 line-clamp-2 mb-1">{{ $item['name'] }}</h3>
                                    <p class="text-[10px] text-gray-500 mb-2">{{ $item['shop'] }}</p>
                                    <div class="mt-auto">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <p class="text-base font-black text-red-600">&yen;{{ number_format($item['price']) }}</p>
                                            @if(($item['postage_flag'] ?? 0) == 1)
                                            <span class="inline-block px-1.5 py-0.5 bg-green-100 text-green-700 text-[10px] font-bold rounded">送料無料</span>
                                            @endif
                                            @if(($item['point_rate'] ?? 1) > 1)
                                            <span class="inline-block px-1.5 py-0.5 bg-red-100 text-red-600 text-[10px] font-bold rounded">ポイント{{ $item['point_rate'] }}倍</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                        <div class="mt-3 text-center">
                            <button type="button" onclick="quickSearch('{{ $category }}')"
                                class="text-sm font-bold text-blue-600 hover:text-blue-800 hover:underline transition-colors">
                                「{{ $category }}」でもっと検索 &rarr;
                            </button>
                        </div>
                    </div>
                    @endif
                @endforeach
            </section>
            @endif

            {{-- パーツ選びのヒント --}}
            <section class="max-w-5xl mx-auto px-4 mt-8">
                <h2 class="text-lg font-black text-gray-800 mb-4">パーツ選びのヒント</h2>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="bg-white rounded-xl border border-gray-100 p-5 hover:shadow-md transition-shadow">
                        <div class="text-2xl mb-2">&#128230;</div>
                        <h3 class="text-sm font-black text-gray-800 mb-2">楽天 vs Yahoo vs Amazon どこが安い？</h3>
                        <p class="text-xs text-gray-600 leading-relaxed mb-3">同じ商品でもサイトごとに価格が異なります。楽天はポイント還元、Yahooは5のつく日、Amazonは即日配送が強み。</p>
                        <a href="{{ route('parts.category', 'muffler') }}" class="text-xs font-bold text-blue-600 hover:text-blue-800 transition-colors">カテゴリページで詳しく &rarr;</a>
                    </div>
                    <div class="bg-white rounded-xl border border-gray-100 p-5 hover:shadow-md transition-shadow">
                        <div class="text-2xl mb-2">&#128176;</div>
                        <h3 class="text-sm font-black text-gray-800 mb-2">ポイント還元を活用しよう</h3>
                        <p class="text-xs text-gray-600 leading-relaxed mb-3">楽天SPUで最大16.5倍、Yahoo!は5のつく日にポイント4倍、Amazonプライムデーで大幅値引き。タイミングを狙ってお得に購入。</p>
                        <a href="{{ route('parts.category', 'tire') }}" class="text-xs font-bold text-blue-600 hover:text-blue-800 transition-colors">各カテゴリのポイント情報 &rarr;</a>
                    </div>
                    <div class="bg-white rounded-xl border border-gray-100 p-5 hover:shadow-md transition-shadow">
                        <div class="text-2xl mb-2">&#128269;</div>
                        <h3 class="text-sm font-black text-gray-800 mb-2">車種適合の確認方法</h3>
                        <p class="text-xs text-gray-600 leading-relaxed mb-3">JAN（バーコード番号）や品番で検索すると、同一商品を高精度で比較可能。車種名+パーツ名での検索もおすすめです。</p>
                        <a href="#search-form-section" class="text-xs font-bold text-blue-600 hover:text-blue-800 transition-colors">検索フォームで探す &rarr;</a>
                    </div>
                </div>
            </section>

            {{-- SEO用テキストコンテンツ --}}
            <section class="max-w-5xl mx-auto px-4 mt-12 mb-8">
                <h2 class="text-lg font-black text-gray-800 mb-4">MotoHubのバイクパーツ検索とは</h2>
                <div class="bg-white rounded-2xl border border-gray-100 p-6 space-y-4">
                    <p class="text-sm text-gray-600 leading-relaxed">
                        MotoHubでは楽天市場・Yahoo!ショッピング・Amazonからバイクパーツを横断検索できます。同じ商品でもショップによって価格が異なるため、一括比較することで最安値を見つけられます。
                    </p>
                    <p class="text-sm text-gray-600 leading-relaxed">
                        JAN（バーコード番号）・品番マッチングにより、同一商品を高精度で比較。送料やポイント還元も考慮して、本当にお得なショップを探せます。
                    </p>
                    <p class="text-sm text-gray-600 leading-relaxed">
                        マフラー、タイヤ、ブレーキパッドなど10カテゴリの選び方ガイドも充実。初めてのパーツ交換でも安心して選べます。
                    </p>
                </div>
            </section>

        </div>{{-- /initial-content --}}

        {{-- ローディング --}}
        <div id="loading" class="hidden max-w-5xl mx-auto px-4 py-12 text-center">
            <div class="inline-block w-8 h-8 border-4 border-blue-500 border-t-transparent rounded-full animate-spin"></div>
            <p class="text-gray-500 text-sm mt-3">検索中...</p>
        </div>

        {{-- エラー表示 --}}
        <div id="error-message" class="hidden max-w-6xl mx-auto px-4 py-6">
            <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg p-4 text-sm"></div>
        </div>

        {{-- 検索結果 --}}
        <section id="results" class="hidden max-w-6xl mx-auto px-4 py-6">
            {{-- Amazon検索バー --}}
            <div id="amazon-bar" class="hidden mb-4 bg-[#FFF8EE] border border-[#FFD89E] rounded-xl p-3 flex items-center justify-between gap-3">
                <span class="text-xs font-bold text-gray-700">Amazonでも検索する</span>
                <a id="amazon-search-link" href="#" target="_blank" rel="noopener noreferrer"
                    class="shrink-0 inline-flex items-center gap-1.5 bg-[#FF9900] hover:bg-[#e88b00] text-white font-bold text-xs px-4 py-2 rounded-lg transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                    Amazonで見る
                </a>
            </div>

            {{-- 結果ヘッダー --}}
            <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
                <h2 class="text-lg font-bold text-gray-800">検索結果 <span id="result-count" class="text-blue-600 text-sm font-normal"></span></h2>
                <div class="flex items-center gap-3">
                    <button id="filter-toggle-btn" type="button"
                        class="lg:hidden inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-gray-200 rounded-lg text-sm font-bold text-gray-700 hover:bg-gray-50 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                        絞り込み
                    </button>
                    <select id="sort-select" class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none bg-white">
                        <option value="default">おすすめ順</option>
                        <option value="price-asc">価格が安い順</option>
                        <option value="price-desc">価格が高い順</option>
                        <option value="review">レビュー評価順</option>
                    </select>
                </div>
            </div>

            <div class="lg:flex lg:gap-6">
                {{-- サイドバー --}}
                <aside id="filter-sidebar" class="hidden lg:block lg:w-[250px] lg:shrink-0 mb-4 lg:mb-0">
                    <div class="bg-white rounded-xl border border-gray-100 p-4 lg:sticky lg:top-4">
                        <h3 class="text-sm font-black text-gray-800 mb-3">絞り込み条件</h3>

                        {{-- 価格帯 --}}
                        <div class="mb-4">
                            <h4 class="text-xs font-bold text-gray-600 mb-2">価格帯</h4>
                            <div class="space-y-1.5">
                                <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                                    <input type="checkbox" class="filter-price rounded border-gray-300" value="0-3000"> 〜3,000円
                                </label>
                                <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                                    <input type="checkbox" class="filter-price rounded border-gray-300" value="3000-10000"> 3,000〜10,000円
                                </label>
                                <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                                    <input type="checkbox" class="filter-price rounded border-gray-300" value="10000-30000"> 10,000〜30,000円
                                </label>
                                <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                                    <input type="checkbox" class="filter-price rounded border-gray-300" value="30000-50000"> 30,000〜50,000円
                                </label>
                                <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                                    <input type="checkbox" class="filter-price rounded border-gray-300" value="50000-"> 50,000円〜
                                </label>
                            </div>
                        </div>

                        {{-- 送料 --}}
                        <div class="mb-4 border-t border-gray-100 pt-4">
                            <h4 class="text-xs font-bold text-gray-600 mb-2">送料</h4>
                            <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                                <input type="checkbox" id="filter-free-shipping" class="rounded border-gray-300"> 送料無料のみ
                            </label>
                        </div>

                        {{-- リセット --}}
                        <div class="border-t border-gray-100 pt-3">
                            <button type="button" id="filter-reset-btn" class="text-xs font-bold text-blue-600 hover:text-blue-800 transition-colors">
                                条件をリセット
                            </button>
                        </div>
                    </div>
                </aside>

                {{-- メインコンテンツ --}}
                <div class="flex-1 min-w-0">
                    <div id="filter-active" class="hidden mb-3 text-xs text-gray-500">
                        <span id="filtered-count"></span>件表示中（全<span id="total-loaded-count"></span>件中）
                    </div>

                    <div id="result-grid" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4"></div>

                    <div id="load-more-wrap" class="hidden text-center mt-8">
                        <button id="load-more-btn"
                            class="inline-flex items-center gap-2 bg-white border-2 border-blue-600 text-blue-600 hover:bg-blue-50 font-bold text-sm px-8 py-3 rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                            <span id="load-more-text">もっと見る</span>
                            <div id="load-more-spinner" class="hidden w-4 h-4 border-2 border-blue-600 border-t-transparent rounded-full animate-spin"></div>
                        </button>
                    </div>
                </div>
            </div>
        </section>

        {{-- 結果0件 --}}
        <div id="no-results" class="hidden max-w-5xl mx-auto px-4 py-12 text-center">
            <div class="text-5xl mb-3">&#128269;</div>
            <p class="text-gray-500 font-bold">該当するパーツが見つかりませんでした</p>
            <p class="text-gray-400 text-sm mt-1">キーワードを変えて再検索してみてください</p>
        </div>
    </div>

    <x-slot:scripts>
    <script>
    function quickSearch(keyword) {
        const keywordInput = document.getElementById('keyword');
        keywordInput.value = keyword;
        document.getElementById('parts-search-form').dispatchEvent(new Event('submit', { cancelable: true }));
    }

    function escapeHtml(text) {
        const el = document.createElement('span');
        el.textContent = text;
        return el.innerHTML;
    }

    document.addEventListener('DOMContentLoaded', () => {
        const AMAZON_TAG = @json(config('services.amazon.associate_tag', ''));
        const YAHOO_BASE = 'https://shopping.yahoo.co.jp/search?p=';

        // ===== DOM =====
        const form = document.getElementById('parts-search-form');
        const formError = document.getElementById('form-error');
        const loading = document.getElementById('loading');
        const results = document.getElementById('results');
        const resultGrid = document.getElementById('result-grid');
        const resultCount = document.getElementById('result-count');
        const noResults = document.getElementById('no-results');
        const errorMessage = document.getElementById('error-message');
        const searchBtn = document.getElementById('search-btn');
        const loadMoreWrap = document.getElementById('load-more-wrap');
        const loadMoreBtn = document.getElementById('load-more-btn');
        const loadMoreText = document.getElementById('load-more-text');
        const loadMoreSpinner = document.getElementById('load-more-spinner');
        const keywordInput = document.getElementById('keyword');
        const bikeInput = document.getElementById('bike');
        const suggestList = document.getElementById('suggest-list');
        const amazonBar = document.getElementById('amazon-bar');
        const amazonSearchLink = document.getElementById('amazon-search-link');
        const initialContent = document.getElementById('initial-content');

        // Filter DOM
        const filterSidebar = document.getElementById('filter-sidebar');
        const filterToggleBtn = document.getElementById('filter-toggle-btn');
        const sortSelect = document.getElementById('sort-select');
        const filterFreeShipping = document.getElementById('filter-free-shipping');
        const filterResetBtn = document.getElementById('filter-reset-btn');
        const filterActiveEl = document.getElementById('filter-active');
        const filteredCountEl = document.getElementById('filtered-count');
        const totalLoadedCountEl = document.getElementById('total-loaded-count');

        // ===== State =====
        let currentPage = 1;
        let totalCount = 0;
        let totalPages = 0;
        let displayedCount = 0;
        let isLoading = false;
        let lastSearchParams = null;
        let suggestTimer = null;
        let suggestItems = [];
        let suggestIndex = -1;

        // ===== バリデーション =====
        function validateForm() {
            const keyword = keywordInput.value.trim();
            const bike = bikeInput.value.trim();
            if (!keyword && !bike) {
                formError.textContent = 'キーワードまたは車種名を入力してください';
                formError.classList.remove('hidden');
                return false;
            }
            formError.classList.add('hidden');
            return true;
        }

        keywordInput.addEventListener('input', () => {
            if (keywordInput.value.trim() || bikeInput.value.trim()) formError.classList.add('hidden');
        });

        // ===== サジェスト =====
        function renderSuggest(items) {
            suggestItems = items;
            suggestIndex = -1;
            if (items.length === 0) { suggestList.classList.add('hidden'); return; }
            suggestList.innerHTML = items.map((item, i) =>
                `<li class="suggest-item px-3 py-2.5 text-sm cursor-pointer hover:bg-blue-50 border-b border-gray-100 last:border-0" data-index="${i}">
                    <span class="font-bold text-gray-800">${escapeHtml(item.name)}</span>
                    <span class="text-gray-400 text-xs ml-1">(${item.count}件)</span>
                </li>`
            ).join('');
            suggestList.classList.remove('hidden');
        }

        function selectSuggest(index) {
            if (index < 0 || index >= suggestItems.length) return;
            bikeInput.value = suggestItems[index].name;
            suggestList.classList.add('hidden');
            suggestItems = [];
            suggestIndex = -1;
            if (bikeInput.value.trim()) formError.classList.add('hidden');
        }

        function highlightSuggest(newIndex) {
            const items = suggestList.querySelectorAll('.suggest-item');
            items.forEach(el => el.classList.remove('bg-blue-50'));
            if (newIndex >= 0 && newIndex < items.length) {
                items[newIndex].classList.add('bg-blue-50');
                items[newIndex].scrollIntoView({ block: 'nearest' });
            }
            suggestIndex = newIndex;
        }

        bikeInput.addEventListener('input', () => {
            if (bikeInput.value.trim() || keywordInput.value.trim()) formError.classList.add('hidden');
            const q = bikeInput.value.trim();
            clearTimeout(suggestTimer);
            if (q.length < 1) { suggestList.classList.add('hidden'); return; }

            suggestTimer = setTimeout(async () => {
                try {
                    const res = await fetch(`/bikes/suggest?keyword=${encodeURIComponent(q)}`);
                    const data = await res.json();
                    renderSuggest(data.models || []);
                } catch { suggestList.classList.add('hidden'); }
            }, 300);
        });

        bikeInput.addEventListener('keydown', (e) => {
            if (suggestList.classList.contains('hidden') || suggestItems.length === 0) return;
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                highlightSuggest(suggestIndex < suggestItems.length - 1 ? suggestIndex + 1 : 0);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                highlightSuggest(suggestIndex > 0 ? suggestIndex - 1 : suggestItems.length - 1);
            } else if (e.key === 'Enter' && suggestIndex >= 0) {
                e.preventDefault();
                selectSuggest(suggestIndex);
            } else if (e.key === 'Escape') {
                suggestList.classList.add('hidden');
            }
        });

        suggestList.addEventListener('click', (e) => {
            const li = e.target.closest('.suggest-item');
            if (li) selectSuggest(parseInt(li.dataset.index));
        });

        document.addEventListener('click', (e) => {
            if (!bikeInput.contains(e.target) && !suggestList.contains(e.target)) suggestList.classList.add('hidden');
        });

        // ===== Amazon URL生成 =====
        function buildAmazonUrl(query) {
            let url = 'https://www.amazon.co.jp/s?k=' + encodeURIComponent(query);
            if (AMAZON_TAG) url += '&tag=' + encodeURIComponent(AMAZON_TAG);
            return url;
        }

        // ===== カード描画 =====
        function renderCard(item) {
            const stars = parseFloat(item.review_avg) || 0;
            const fullStars = Math.floor(stars);
            const halfStar = stars - fullStars >= 0.5;
            let starHtml = '';
            for (let i = 0; i < fullStars; i++) starHtml += '<span class="text-yellow-400">&#9733;</span>';
            if (halfStar) starHtml += '<span class="text-yellow-400">&#9733;</span>';
            const empty = 5 - fullStars - (halfStar ? 1 : 0);
            for (let i = 0; i < empty; i++) starHtml += '<span class="text-gray-300">&#9733;</span>';
            const price = Number(item.price).toLocaleString();
            const imageUrl = item.image ? item.image.replace('?_ex=128x128', '?_ex=300x300') : '';

            const jan = item.jan_code || '';
            const partNum = item.part_number || '';
            const hasCode = jan || partNum;
            const captionText = item.caption || '';
            const escapedCaption = captionText ? escapeHtml(captionText) : '';
            const postageBadge = item.postage_flag == 1
                ? '<span class="inline-block px-1.5 py-0.5 bg-green-100 text-green-700 text-[10px] font-bold rounded">送料無料</span>'
                : '';
            const pointBadge = (item.point_rate || 1) > 1
                ? `<span class="inline-block px-1.5 py-0.5 bg-red-100 text-red-600 text-[10px] font-bold rounded">ポイント${item.point_rate}倍</span>`
                : '';

            let compareUrl = '/parts/compare?';
            const cParams = new URLSearchParams();
            if (jan) cParams.set('jan', jan);
            if (partNum) cParams.set('partnum', partNum);
            cParams.set('keyword', item.name.substring(0, 100));
            compareUrl += cParams.toString();

            const fallbackQuery = partNum || item.name.substring(0, 60);
            const yahooUrl = YAHOO_BASE + encodeURIComponent(fallbackQuery);
            const amazonFallbackUrl = buildAmazonUrl(fallbackQuery);

            let buttonsHtml;
            if (hasCode) {
                const badge = jan
                    ? `<span class="text-[10px] text-gray-400">JAN: ${jan}</span>`
                    : `<span class="text-[10px] text-gray-400">品番: ${partNum}</span>`;
                buttonsHtml = `
                    ${badge}
                    <a href="${compareUrl}"
                        class="block text-center bg-gray-800 hover:bg-gray-900 text-white text-xs font-bold py-2 rounded-lg transition-colors mt-1">
                        価格を比較する
                    </a>`;
            } else {
                buttonsHtml = `
                    <div class="flex flex-col gap-1.5">
                        <a href="${escapeHtml(item.url)}" target="_blank" rel="noopener noreferrer"
                            class="block text-center bg-red-500 hover:bg-red-600 text-white text-xs font-bold py-2 rounded-lg transition-colors">
                            楽天市場で見る
                        </a>
                        <a href="${yahooUrl}" target="_blank" rel="noopener noreferrer"
                            class="block text-center bg-blue-500 hover:bg-blue-600 text-white text-xs font-bold py-2 rounded-lg transition-colors">
                            Yahoo!で探す
                        </a>
                        <a href="${amazonFallbackUrl}" target="_blank" rel="noopener noreferrer"
                            class="block text-center bg-amber-500 hover:bg-amber-600 text-white text-xs font-bold py-2 rounded-lg transition-colors">
                            Amazonで探す
                        </a>
                    </div>`;
            }

            const card = document.createElement('div');
            card.className = 'bg-white rounded-xl shadow hover:shadow-lg transition-shadow overflow-hidden flex flex-col h-full';
            card.dataset.price = item.price || 0;
            card.dataset.postage = item.postage_flag || 0;
            card.dataset.review = item.review_avg || 0;
            card.innerHTML = `
                <div class="aspect-square bg-gray-100 flex items-center justify-center overflow-hidden">
                    ${imageUrl
                        ? `<img src="${imageUrl}" alt="${escapeHtml(item.name)}" class="w-full h-full object-contain p-2" loading="lazy">`
                        : `<div class="text-gray-300 text-4xl">&#128295;</div>`}
                </div>
                <div class="p-3 flex flex-col flex-grow">
                    <h3 class="text-sm font-bold text-gray-800 line-clamp-2 mb-1">${escapeHtml(item.name)}</h3>
                    ${escapedCaption ? `<p class="text-xs text-gray-500 line-clamp-2 mb-1">${escapedCaption}</p>` : ''}
                    <p class="text-xs text-gray-500 mb-2">${escapeHtml(item.shop)}</p>
                    <div class="mt-auto">
                        <div class="flex items-center gap-2 flex-wrap mb-1">
                            <p class="text-lg font-black text-red-600">&yen;${price}</p>
                            ${postageBadge}
                            ${pointBadge}
                        </div>
                        <div class="flex items-center gap-1 text-xs mb-3">
                            ${starHtml}
                            <span class="text-gray-500 ml-1">(${item.review_count}件)</span>
                        </div>
                        ${buttonsHtml}
                    </div>
                </div>`;
            return card;
        }

        function updateResultCount() {
            resultCount.textContent = `${totalCount.toLocaleString()} 件中 ${displayedCount} 件を表示中`;
        }

        function updateLoadMoreButton(hasMore) {
            if (hasMore) {
                const remaining = totalCount - displayedCount;
                loadMoreText.textContent = `もっと見る（残り ${remaining.toLocaleString()} 件）`;
                loadMoreWrap.classList.remove('hidden');
            } else {
                loadMoreWrap.classList.add('hidden');
            }
        }

        function showError(msg) {
            errorMessage.querySelector('div').textContent = msg;
            errorMessage.classList.remove('hidden');
        }

        function updateAmazonLink() {
            const keyword = keywordInput.value.trim();
            const bike = bikeInput.value.trim();
            const category = document.getElementById('category').value;
            const parts = ['バイク'];
            if (keyword) parts.push(keyword);
            if (bike) parts.push(bike);
            if (category) parts.push(category);
            amazonSearchLink.href = buildAmazonUrl(parts.join(' '));
        }

        // ===== フィルター / ソート =====
        filterToggleBtn.addEventListener('click', () => {
            filterSidebar.classList.toggle('hidden');
        });

        function applyFilters() {
            const checkedPrices = Array.from(document.querySelectorAll('.filter-price:checked')).map(cb => cb.value);
            const freeOnly = filterFreeShipping.checked;
            const sortBy = sortSelect.value;
            const cards = Array.from(resultGrid.children);
            let visibleCount = 0;

            cards.forEach(card => {
                const price = parseInt(card.dataset.price) || 0;
                const postage = parseInt(card.dataset.postage) || 0;
                let show = true;

                if (checkedPrices.length > 0) {
                    show = checkedPrices.some(range => {
                        const [minStr, maxStr] = range.split('-');
                        const min = parseInt(minStr) || 0;
                        const max = maxStr ? parseInt(maxStr) : Infinity;
                        return price >= min && price <= max;
                    });
                }

                if (show && freeOnly && postage !== 1) show = false;

                card.style.display = show ? '' : 'none';
                if (show) visibleCount++;
            });

            if (sortBy !== 'default') {
                const allCards = Array.from(resultGrid.children);
                allCards.sort((a, b) => {
                    if (sortBy === 'price-asc') return (parseInt(a.dataset.price) || 0) - (parseInt(b.dataset.price) || 0);
                    if (sortBy === 'price-desc') return (parseInt(b.dataset.price) || 0) - (parseInt(a.dataset.price) || 0);
                    if (sortBy === 'review') return (parseFloat(b.dataset.review) || 0) - (parseFloat(a.dataset.review) || 0);
                    return 0;
                });
                allCards.forEach(card => resultGrid.appendChild(card));
            }

            if (checkedPrices.length > 0 || freeOnly) {
                filteredCountEl.textContent = visibleCount;
                totalLoadedCountEl.textContent = cards.length;
                filterActiveEl.classList.remove('hidden');
            } else {
                filterActiveEl.classList.add('hidden');
            }
        }

        document.querySelectorAll('.filter-price').forEach(cb => cb.addEventListener('change', applyFilters));
        filterFreeShipping.addEventListener('change', applyFilters);
        sortSelect.addEventListener('change', applyFilters);

        filterResetBtn.addEventListener('click', () => {
            document.querySelectorAll('.filter-price').forEach(cb => cb.checked = false);
            filterFreeShipping.checked = false;
            sortSelect.value = 'default';
            applyFilters();
        });

        // ===== API通信 =====
        async function fetchResults(page, append) {
            if (isLoading) return;
            isLoading = true;
            const params = new URLSearchParams(lastSearchParams.toString());
            params.set('page', page);
            try {
                const res = await fetch(`/parts/search?${params.toString()}`);
                const data = await res.json();
                if (data.error) {
                    showError(append ? '読み込みに失敗しました。再度お試しください。' : data.error);
                    return;
                }
                const items = data.items || [];
                if (!append && items.length === 0) { noResults.classList.remove('hidden'); return; }
                currentPage = data.currentPage;
                totalCount = data.totalCount;
                totalPages = data.totalPages;
                if (!append) { resultGrid.innerHTML = ''; displayedCount = 0; }
                items.forEach(item => resultGrid.appendChild(renderCard(item)));
                displayedCount += items.length;
                updateResultCount();
                updateLoadMoreButton(data.hasMore);
                results.classList.remove('hidden');
                amazonBar.classList.remove('hidden');
                applyFilters();
            } catch (err) {
                showError(append ? '読み込みに失敗しました。再度お試しください。' : '通信エラーが発生しました。もう一度お試しください。');
            } finally {
                isLoading = false;
            }
        }

        // ===== 検索フォーム送信 =====
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (!validateForm()) return;

            const keyword = keywordInput.value.trim();
            const bike = bikeInput.value.trim();
            const category = document.getElementById('category').value;
            const params = new URLSearchParams();
            if (keyword) params.set('keyword', keyword);
            if (bike) params.set('bike', bike);
            if (category) params.set('category', category);

            loading.classList.remove('hidden');
            results.classList.add('hidden');
            noResults.classList.add('hidden');
            errorMessage.classList.add('hidden');
            loadMoreWrap.classList.add('hidden');
            amazonBar.classList.add('hidden');
            suggestList.classList.add('hidden');
            if (initialContent) initialContent.classList.add('hidden');
            searchBtn.disabled = true;
            searchBtn.classList.add('opacity-50');

            // Reset filters on new search
            document.querySelectorAll('.filter-price').forEach(cb => cb.checked = false);
            filterFreeShipping.checked = false;
            sortSelect.value = 'default';
            filterActiveEl.classList.add('hidden');

            currentPage = 1; totalCount = 0; totalPages = 0; displayedCount = 0;
            lastSearchParams = params;

            updateAmazonLink();

            await fetchResults(1, false);

            loading.classList.add('hidden');
            searchBtn.disabled = false;
            searchBtn.classList.remove('opacity-50');
        });

        // ===== もっと見る =====
        loadMoreBtn.addEventListener('click', async () => {
            if (isLoading || !lastSearchParams) return;
            loadMoreBtn.disabled = true;
            loadMoreText.classList.add('hidden');
            loadMoreSpinner.classList.remove('hidden');
            errorMessage.classList.add('hidden');
            await fetchResults(currentPage + 1, true);
            loadMoreBtn.disabled = false;
            loadMoreText.classList.remove('hidden');
            loadMoreSpinner.classList.add('hidden');
        });

        // ===== URLパラメータから自動検索 =====
        const urlParams = new URLSearchParams(window.location.search);
        const urlKeyword = urlParams.get('keyword');
        const urlBike = urlParams.get('bike');
        if (urlKeyword) keywordInput.value = urlKeyword;
        if (urlBike) bikeInput.value = urlBike;
        if (urlKeyword || urlBike) {
            form.dispatchEvent(new Event('submit', { cancelable: true }));
        }
    });
    </script>
    </x-slot:scripts>
</x-layout>
