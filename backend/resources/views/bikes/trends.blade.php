<x-layout>
    <x-slot:title>バイク相場・価格変動ランキング | MotoHub</x-slot:title>
    <x-slot:metaDescription>中古バイクの「値下がり」「価格高騰」ランキングを毎日更新！過去30日間の相場変動から、今が買い時のバイクやプレミア化しているバイクが一目で分かります。</x-slot:metaDescription>

    <x-slot:scripts>
        <script src="{{ asset('js/bikes/trends.js') }}"></script>
    </x-slot:scripts>

    <x-slot:navigation>
        <x-navigation :showSearch="true" />
    </x-slot:navigation>

    <div class="bg-gray-50 min-h-screen py-10 sm:py-16">
        <div class="max-w-5xl mx-auto px-4">
            {{-- ヘッダー --}}
            <div class="text-center mb-12">
                <div class="inline-block bg-blue-100 text-blue-600 text-[10px] font-black px-3 py-1.5 rounded-full mb-4 tracking-widest uppercase shadow-sm">
                    Market Trends
                </div>
                <h1 class="text-3xl sm:text-4xl font-black text-gray-900 mb-4 tracking-tighter">
                    バイク相場・価格変動ランキング
                </h1>
                <p class="text-gray-500 font-bold text-sm leading-relaxed">
                    過去{{ $trends['period']['days'] ?? 30 }}日間（{{ $trends['period']['from'] ?? '' }} 〜 {{ $trends['period']['to'] ?? '' }}）の市場データを分析。<br class="hidden sm:block">今が買い時の値下がり車種や、プレミア化している高騰車種をチェック！
                </p>
            </div>

            {{-- タブ --}}
            <div class="flex justify-center mb-10">
                <div class="bg-white p-1.5 rounded-2xl shadow-sm border border-gray-100 inline-flex">
                    <button id="tab-drop" class="tab-btn active w-36 sm:w-48 py-3 rounded-xl text-sm font-black transition-all bg-blue-600 text-white shadow-md flex items-center justify-center gap-2">
                        <i data-lucide="trending-down" class="w-4 h-4"></i>
                        値下がり <span class="hidden sm:inline">(買い時)</span>
                    </button>
                    <button id="tab-rise" class="tab-btn w-36 sm:w-48 py-3 rounded-xl text-sm font-black text-gray-400 hover:text-gray-800 transition-all flex items-center justify-center gap-2">
                        <i data-lucide="trending-up" class="w-4 h-4"></i>
                        価格高騰 <span class="hidden sm:inline">(プレミア)</span>
                    </button>
                </div>
            </div>

            {{-- 値下がりランキング --}}
            <div id="content-drop" class="tab-content block">
                @if(empty($trends['drop']))
                    <div class="text-center py-20 bg-white rounded-3xl border border-gray-100">
                        <i data-lucide="bar-chart-2" class="w-12 h-12 text-gray-200 mx-auto mb-4"></i>
                        <p class="text-gray-400 font-bold text-sm">データがまだ十分に蓄積されていません。</p>
                    </div>
                @else
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                        @foreach($trends['drop'] as $index => $item)
                            <x-trend-card :item="$item" :index="$index" type="drop" />
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- 高騰ランキング --}}
            <div id="content-rise" class="tab-content hidden">
                 @if(empty($trends['rise']))
                    <div class="text-center py-20 bg-white rounded-3xl border border-gray-100">
                        <i data-lucide="bar-chart-2" class="w-12 h-12 text-gray-200 mx-auto mb-4"></i>
                        <p class="text-gray-400 font-bold text-sm">データがまだ十分に蓄積されていません。</p>
                    </div>
                @else
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                        @foreach($trends['rise'] as $index => $item)
                            <x-trend-card :item="$item" :index="$index" type="rise" />
                        @endforeach
                    </div>
                @endif
            </div>
            
            <div class="mt-12 text-center text-[10px] text-gray-400 font-bold">
                ※ランキングはMotoHub内で販売されている車両の平均価格をもとに算出しています。<br>
                極端な異常値（1億円などの入力ミス）は除外していますが、参考価格としてご覧ください。
            </div>
        </div>
    </div>
</x-layout>