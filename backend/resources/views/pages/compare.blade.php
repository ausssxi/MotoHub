<x-layout>
    {{-- 1. タイトル --}}
    <x-slot:title>
        車両比較 - MotoHub
    </x-slot:title>

    {{-- 2. ナビゲーション --}}
    <x-slot:navigation>
        <x-navigation :showSearch="true" />
    </x-slot:navigation>

    {{-- 3. メインコンテンツ --}}
    <div class="bg-gray-50 min-h-screen py-6 sm:py-12 px-2 sm:px-6 lg:px-8">
        <div class="max-w-7xl mx-auto">

            <div id="compare-empty" class="hidden bg-white rounded-3xl p-8 sm:p-16 text-center shadow-sm border border-gray-100">
                <div class="w-16 h-16 sm:w-20 sm:h-20 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-6 text-gray-300">
                    <i data-lucide="layers" class="w-8 h-8 sm:w-10 sm:h-10"></i>
                </div>
                <h2 class="text-lg sm:text-xl font-black text-gray-800 mb-2">比較する車両がありません</h2>
                <p class="text-sm sm:text-base text-gray-400 mb-8">検索結果から「比較」ボタンを押して追加してください</p>
                <a href="{{ route('bikes.search') }}" class="inline-block bg-gray-900 text-white px-6 py-2 sm:px-8 sm:py-3 rounded-full text-sm sm:text-base font-black">車両を探しに行く</a>
            </div>

            <div id="compare-container" class="hidden">
                {{-- 
                    【デザイン調整】
                    [&_img]:から始まるクラスを追加しました。
                    これにより、JSで生成される全ての画像に「角丸・4:3比率・画像切り抜き」が適用されます。
                --}}
                <div class="bg-white rounded-2xl sm:rounded-3xl shadow-xl shadow-gray-200/50 border border-gray-100 overflow-hidden overflow-x-auto pb-1 snap-x snap-mandatory scroll-pl-32 sm:scroll-pl-60 [&_img]:rounded-xl sm:[&_img]:rounded-2xl [&_img]:aspect-[4/3] [&_img]:object-cover [&_img]:w-full [&_img]:mb-3 [&_img]:border [&_img]:border-gray-50">
                    
                    <table class="w-auto border-collapse"> 
                        <thead>
                            <tr id="compare-header" class="bg-gray-50/50">
                                <th class="p-2 sm:p-4 w-32 sm:w-60 min-w-[128px] sm:min-w-[240px] text-left sticky left-0 bg-gray-50 z-20 border-r border-gray-100 shadow-[4px_0_12px_-4px_rgba(0,0,0,0.1)]">
                                    <div class="flex flex-col gap-0.5 sm:gap-1">
                                        <span class="hidden sm:block text-[10px] font-black text-gray-300 uppercase tracking-widest italic">Comparison</span>
                                        <span class="text-xs sm:text-base font-black text-gray-800">スペック比較</span>
                                    </div>
                                </th>
                                {{-- ここにJSで車両カラム（th/imgを含む）が挿入されます --}}
                            </tr>
                        </thead>
                        <tbody id="compare-body" class="text-xs sm:text-sm">
                            <tr class="border-t border-gray-100" data-prop="total_price">
                                <td class="p-2 sm:p-4 bg-gray-50 font-bold text-gray-400 italic sticky left-0 z-10 border-r border-gray-100 shadow-[4px_0_12px_-4px_rgba(0,0,0,0.1)]">支払総額</td>
                            </tr>
                            <tr class="border-t border-gray-100" data-prop="model_year">
                                <td class="p-2 sm:p-4 bg-gray-50 font-bold text-gray-400 italic sticky left-0 z-10 border-r border-gray-100 shadow-[4px_0_12px_-4px_rgba(0,0,0,0.1)]">年式</td>
                            </tr>
                            <tr class="border-t border-gray-100" data-prop="mileage">
                                <td class="p-2 sm:p-4 bg-gray-50 font-bold text-gray-400 italic sticky left-0 z-10 border-r border-gray-100 shadow-[4px_0_12px_-4px_rgba(0,0,0,0.1)]">走行距離</td>
                            </tr>
                            <tr class="border-t border-gray-100" data-prop="condition">
                                <td class="p-2 sm:p-4 bg-gray-50 font-bold text-gray-400 italic sticky left-0 z-10 border-r border-gray-100 shadow-[4px_0_12px_-4px_rgba(0,0,0,0.1)]">コンディション</td>
                            </tr>
                            <tr class="border-t border-gray-100" data-prop="store_name">
                                <td class="p-2 sm:p-4 bg-gray-50 font-bold text-gray-400 italic sticky left-0 z-10 border-r border-gray-100 shadow-[4px_0_12px_-4px_rgba(0,0,0,0.1)]">販売店</td>
                            </tr>
                             <tr class="border-t border-gray-100" data-prop="prefecture">
                                <td class="p-2 sm:p-4 bg-gray-50 font-bold text-gray-400 italic sticky left-0 z-10 border-r border-gray-100 shadow-[4px_0_12px_-4px_rgba(0,0,0,0.1)]">都道府県</td>
                            </tr>
                            <tr class="border-t border-gray-100" data-prop="site_name">
                                <td class="p-2 sm:p-4 bg-gray-50 font-bold text-gray-400 italic sticky left-0 z-10 border-r border-gray-100 shadow-[4px_0_12px_-4px_rgba(0,0,0,0.1)]">掲載サイト</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <script src="{{ asset('js/compare/manager.js') }}"></script>
    <script src="{{ asset('js/compare/page.js') }}"></script>
</x-layout>