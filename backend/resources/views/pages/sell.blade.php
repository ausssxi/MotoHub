<x-layout>
    <x-slot:title>バイク買取相場・一括査定シミュレーション | MotoHub</x-slot:title>
    <x-slot:metaDescription>あなたのバイク、今いくらで売れる？MotoHubの膨大な市場データから、愛車のリアルな買取相場を3秒で診断します。会員登録不要・無料。</x-slot:metaDescription>

    <x-slot:navigation>
        <x-navigation :showSearch="false" />
    </x-slot:navigation>

    <div class="bg-gray-50 min-h-screen pb-20">
        
        {{-- ヒーローエリア --}}
        <div class="relative bg-gray-900 overflow-hidden">
            <div class="absolute inset-0 opacity-40">
                <img src="https://images.unsplash.com/photo-1558981403-c5f9899a28bc?q=80&w=2070" class="w-full h-full object-cover">
            </div>
            <div class="absolute inset-0 bg-gradient-to-b from-gray-900/50 to-gray-900"></div>

            <div class="relative max-w-4xl mx-auto px-4 pt-20 pb-32 text-center text-white">
                <h1 class="text-3xl sm:text-5xl font-black mb-6 leading-tight tracking-tight">
                    あなたのバイク、<br class="sm:hidden">今いくらで売れる？
                </h1>
                <p class="text-gray-300 font-bold text-sm sm:text-base mb-10">
                    国内最大級のデータを持つMotoHubが、<br>
                    現在の市場価格から<span class="text-yellow-400">「リアルな買取相場」</span>を算出します。
                </p>

                {{-- 査定フォーム --}}
                <div class="bg-white rounded-3xl p-6 sm:p-10 shadow-2xl max-w-2xl mx-auto text-left transform translate-y-10">
                    <form id="sell-form">
                        @csrf
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 mb-6">
                            
                            {{-- メーカー選択 --}}
                            <div>
                                <label class="block text-xs font-bold text-gray-500 mb-2">メーカー</label>
                                <div class="relative">
                                    <select id="select-maker" class="w-full bg-gray-50 border border-gray-200 text-gray-900 text-sm font-bold rounded-xl focus:ring-blue-500 focus:border-blue-500 block p-3.5 appearance-none">
                                        <option value="">選択してください</option>
                                        @foreach($manufacturers as $maker)
                                            <option value="{{ $maker->id }}">{{ $maker->name }}</option>
                                        @endforeach
                                    </select>
                                    <i data-lucide="chevron-down" class="absolute right-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none"></i>
                                </div>
                            </div>

                            {{-- 車種選択 --}}
                            <div>
                                <label class="block text-xs font-bold text-gray-500 mb-2">車種</label>
                                <div class="relative">
                                    <select id="select-model" disabled class="w-full bg-gray-50 border border-gray-200 text-gray-400 text-sm font-bold rounded-xl focus:ring-blue-500 focus:border-blue-500 block p-3.5 appearance-none disabled:opacity-50 disabled:cursor-not-allowed">
                                        <option value="">メーカーを選択してください</option>
                                    </select>
                                    <i data-lucide="chevron-down" class="absolute right-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none"></i>
                                </div>
                            </div>
                        </div>

                        {{-- 年式選択（任意） --}}
                        <div class="mb-8">
                            <label class="block text-xs font-bold text-gray-500 mb-2">年式 <span class="text-gray-400 font-normal ml-1">(任意)</span></label>
                            <div class="relative">
                                <select id="select-year" class="w-full bg-gray-50 border border-gray-200 text-gray-900 text-sm font-bold rounded-xl focus:ring-blue-500 focus:border-blue-500 block p-3.5 appearance-none">
                                    <option value="">こだわらない / 不明</option>
                                    @for($y = date('Y'); $y >= 1980; $y--)
                                        <option value="{{ $y }}">{{ $y }}年</option>
                                    @endfor
                                </select>
                                <i data-lucide="chevron-down" class="absolute right-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none"></i>
                            </div>
                        </div>

                        <button type="submit" id="btn-calculate" disabled class="w-full bg-blue-600 hover:bg-blue-500 disabled:bg-gray-300 text-white font-black text-lg py-4 rounded-xl shadow-lg shadow-blue-500/30 disabled:shadow-none transition-all transform active:scale-95 flex items-center justify-center gap-2">
                            <span>相場をチェックする</span>
                            <i data-lucide="calculator" class="w-5 h-5"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        {{-- 結果表示エリア --}}
        <div id="result-area" class="max-w-4xl mx-auto px-4 mt-24 hidden">
            <div class="bg-white rounded-3xl shadow-xl border border-blue-100 overflow-hidden">
                <div class="bg-blue-50 p-6 text-center border-b border-blue-100">
                    <p class="text-sm font-bold text-blue-600 mb-1" id="result-title">HONDA PCX (2020年式)</p>
                    <h2 class="text-xl font-black text-gray-900">買取相場シミュレーション結果</h2>
                </div>
                
                <div class="p-8 sm:p-12 text-center">
                    <p class="text-sm text-gray-500 font-bold mb-4">推定買取価格レンジ</p>
                    
                    <div class="flex items-end justify-center gap-2 mb-2">
                        <span class="text-4xl sm:text-6xl font-black text-gray-900 tracking-tighter" id="result-min">---</span>
                        <span class="text-xl font-bold text-gray-400 pb-2">〜</span>
                        <span class="text-4xl sm:text-6xl font-black text-red-600 tracking-tighter" id="result-max">---</span>
                        <span class="text-xl font-bold text-gray-400 pb-2">万円</span>
                    </div>

                    <p class="text-xs text-gray-400 mb-10">
                        市場平均販売価格: <span id="result-retail">---</span>万円 から算出<br>
                        <span id="result-note" class="text-orange-500 hidden">※指定年式のデータが少ないため、車種全体の平均を使用しています</span>
                    </p>

                    {{-- アフィリエイトボタン --}}
                    <div class="space-y-4 max-w-2xl mx-auto">
                        <p class="text-sm font-black text-gray-800 animate-bounce">
                            👇 今すぐ正確な金額を査定してみる 👇
                        </p>
                        
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            {{-- 1. バイクワン --}}
                            <div class="flex flex-col">
                                <div class="text-[10px] font-bold text-center text-blue-600 bg-blue-50 py-1 rounded-t-lg border-x border-t border-blue-100">
                                    カスタム車・改造車もOK！
                                </div>
                                <a href="https://px.a8.net/svt/ejp?a8mat=4AX6CG+5PEKHE+1BFI+61RIA" target="_blank" rel="nofollow" class="relative block w-full bg-gradient-to-br from-blue-500 to-blue-600 hover:from-blue-400 hover:to-blue-500 text-white font-black text-center py-4 rounded-b-xl shadow-md transition-all transform hover:-translate-y-1 flex items-center justify-center gap-2">
                                    <span>バイクワンで査定</span>
                                    <i data-lucide="external-link" class="w-4 h-4"></i>
                                </a>
                                {{-- 計測タグ --}}
                                <img border="0" width="1" height="1" src="https://www18.a8.net/0.gif?a8mat=4AX6CG+5PEKHE+1BFI+61RIA" alt="" class="hidden">
                            </div>

                            {{-- 2. バイクBOON --}}
                            <div class="flex flex-col">
                                <div class="text-[10px] font-bold text-center text-red-600 bg-red-50 py-1 rounded-t-lg border-x border-t border-red-100">
                                    旧車・ハーレー・大型車に強い！
                                </div>
                                <a href="https://px.a8.net/svt/ejp?a8mat=4AX6CG+5QLFOY+1T3W+62ENM" target="_blank" rel="nofollow" class="relative block w-full bg-gradient-to-br from-red-500 to-red-600 hover:from-red-400 hover:to-red-500 text-white font-black text-center py-4 rounded-b-xl shadow-md transition-all transform hover:-translate-y-1 flex items-center justify-center gap-2">
                                    <span>バイクBOONで査定</span>
                                    <i data-lucide="external-link" class="w-4 h-4"></i>
                                </a>
                                {{-- 計測タグ --}}
                                <img border="0" width="1" height="1" src="https://www18.a8.net/0.gif?a8mat=4AX6CG+5QLFOY+1T3W+62ENM" alt="" class="hidden">
                            </div>
                        </div>

                        <p class="text-[10px] text-gray-400 text-center font-bold mt-2">
                            提携: バイクワン / バイクBOON
                        </p>
                    </div>
                </div>
                
                <div class="bg-gray-50 p-4 text-center border-t border-gray-100">
                    <p class="text-[10px] text-gray-400 leading-relaxed">
                        ※この試算結果はMotoHub独自の市場データに基づく推計値です。<br>
                        実際の買取価格は、車両の状態（キズ・サビ・消耗品の劣化など）や時期、地域により変動します。<br>
                        正確な金額を知りたい場合は、上記の買取サービスにて実車査定をご依頼ください。
                    </p>
                </div>
            </div>
        </div>

    </div>

    {{-- JS読み込み --}}
    <script src="{{ asset('js/pages/sell.js') }}"></script>
</x-layout>