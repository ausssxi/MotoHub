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
                <div class="w-16 h-16 sm:w-20 sm:h-20 bg-blue-50 rounded-full flex items-center justify-center mx-auto mb-6 text-blue-400">
                    <i data-lucide="git-compare-arrows" class="w-8 h-8 sm:w-10 sm:h-10"></i>
                </div>
                <h2 class="text-lg sm:text-xl font-black text-gray-800 mb-2">車両を比較しよう</h2>
                <p class="text-sm text-gray-500 mb-8">気になるバイクを並べてスペック・価格を一目で比較できます</p>

                {{-- 使い方ステップ --}}
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 max-w-2xl mx-auto mb-10 text-left sm:text-center">
                    <div class="flex sm:flex-col items-start sm:items-center gap-3 sm:gap-2">
                        <div class="w-10 h-10 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center font-black text-sm shrink-0">1</div>
                        <div>
                            <p class="text-sm font-black text-gray-800">検索結果で比較アイコンをタップ</p>
                            <p class="text-xs text-gray-400 mt-0.5">車両カードの <i data-lucide="layers" class="w-3 h-3 inline-block"></i> アイコン</p>
                        </div>
                    </div>
                    <div class="flex sm:flex-col items-start sm:items-center gap-3 sm:gap-2">
                        <div class="w-10 h-10 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center font-black text-sm shrink-0">2</div>
                        <div>
                            <p class="text-sm font-black text-gray-800">2〜5台を追加</p>
                            <p class="text-xs text-gray-400 mt-0.5">気になる車両を複数選んでOK</p>
                        </div>
                    </div>
                    <div class="flex sm:flex-col items-start sm:items-center gap-3 sm:gap-2">
                        <div class="w-10 h-10 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center font-black text-sm shrink-0">3</div>
                        <div>
                            <p class="text-sm font-black text-gray-800">このページで一覧比較</p>
                            <p class="text-xs text-gray-400 mt-0.5">価格・年式・走行距離を横並び表示</p>
                        </div>
                    </div>
                </div>

                <a href="{{ route('bikes.search') }}" class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-8 py-3 rounded-full text-sm font-black transition-colors shadow-lg shadow-blue-500/20">
                    <i data-lucide="search" class="w-4 h-4"></i>
                    車両を探しに行く
                </a>
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