{{-- 相場コンテンツへの内部回遊リンク（検索結果ページ用・常時表示） --}}
<div class="mt-12 bg-white rounded-3xl shadow-sm p-6 sm:p-8 border border-gray-100 animate-in fade-in duration-500">
    <h2 class="text-lg sm:text-xl font-black text-gray-900 mb-6 flex items-center gap-2">
        <span class="bg-emerald-100 text-emerald-600 p-2 rounded-lg">
            <i data-lucide="trending-up" class="w-5 h-5"></i>
        </span>
        相場データで探す
    </h2>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <a href="{{ route('market') }}"
           class="group flex items-center gap-3 bg-gray-50 rounded-xl p-4 border border-gray-100 hover:border-blue-300 hover:shadow-md transition-all duration-300 hover:-translate-y-0.5">
            <span class="shrink-0 bg-white text-blue-600 p-2.5 rounded-lg border border-gray-100">
                <i data-lucide="bar-chart-3" class="w-5 h-5"></i>
            </span>
            <span class="min-w-0">
                <span class="block text-sm font-black text-gray-800 group-hover:text-blue-600 transition-colors">全国の中古相場・値動き</span>
                <span class="block text-[11px] font-bold text-gray-400 mt-0.5">価格の全体感をつかむ</span>
            </span>
        </a>

        <a href="{{ route('bikes.trends') }}"
           class="group flex items-center gap-3 bg-gray-50 rounded-xl p-4 border border-gray-100 hover:border-blue-300 hover:shadow-md transition-all duration-300 hover:-translate-y-0.5">
            <span class="shrink-0 bg-white text-rose-600 p-2.5 rounded-lg border border-gray-100">
                <i data-lucide="flame" class="w-5 h-5"></i>
            </span>
            <span class="min-w-0">
                <span class="block text-sm font-black text-gray-800 group-hover:text-blue-600 transition-colors">今値上がりしている車種</span>
                <span class="block text-[11px] font-bold text-gray-400 mt-0.5">高騰トレンドをチェック</span>
            </span>
        </a>

        <a href="{{ route('bikes.region_price_index') }}"
           class="group flex items-center gap-3 bg-gray-50 rounded-xl p-4 border border-gray-100 hover:border-blue-300 hover:shadow-md transition-all duration-300 hover:-translate-y-0.5">
            <span class="shrink-0 bg-white text-amber-600 p-2.5 rounded-lg border border-gray-100">
                <i data-lucide="map-pin" class="w-5 h-5"></i>
            </span>
            <span class="min-w-0">
                <span class="block text-sm font-black text-gray-800 group-hover:text-blue-600 transition-colors">地域で価格差が大きい車種</span>
                <span class="block text-[11px] font-bold text-gray-400 mt-0.5">買う地域で差が出る</span>
            </span>
        </a>
    </div>
</div>
