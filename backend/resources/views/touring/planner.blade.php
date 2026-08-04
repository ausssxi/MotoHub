<x-layout>
    <x-slot:title>ツーリングプランナー | MotoHub</x-slot:title>
    <x-slot:metaDescription>出発地を入力するだけで、AI が周辺のツーリングスポット・道の駅を組み合わせた1日ツーリングプランを提案します。</x-slot:metaDescription>
    <x-slot:canonical>{{ url('/touring/planner') }}</x-slot:canonical>

    <x-slot:navigation>
        <x-navigation />
    </x-slot:navigation>

    <x-slot:styles>
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
        <style>
            #planner-map { height: 400px; width: 100%; border-radius: 0.75rem; }
            @media (max-width: 640px) { #planner-map { height: 300px; } }
        </style>
    </x-slot:styles>

    <div class="max-w-4xl mx-auto px-4 py-8" x-data="touringPlanner()" x-cloak>
        {{-- パンくずリスト --}}
        <nav class="text-sm text-gray-400 mb-6">
            <a href="{{ url('/') }}" class="hover:text-gray-600">ホーム</a>
            <span class="mx-1">/</span>
            <a href="{{ route('touring.index') }}" class="hover:text-gray-600">ツーリング</a>
            <span class="mx-1">/</span>
            <span class="text-gray-600">プランナー</span>
        </nav>

        <h1 class="text-2xl font-bold mb-2">ツーリングプランナー</h1>
        <p class="text-gray-500 text-sm mb-8">出発地を入力すると、AIが周辺のスポットを組み合わせて1日ツーリングプランを提案します。</p>

        {{-- 入力フォーム --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5 mb-8">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                {{-- 出発地 --}}
                <div class="sm:col-span-2">
                    <label for="origin-input" class="block text-sm font-bold text-gray-700 mb-1">出発地</label>
                    <input type="text"
                           id="origin-input"
                           x-model="origin"
                           @keydown.enter.prevent="suggest()"
                           placeholder="地名・駅名・住所を入力（例: 東京駅、横浜市、浜松SA）"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                </div>

                {{-- 走行距離 --}}
                <div>
                    <label for="distance-select" class="block text-sm font-bold text-gray-700 mb-1">走行距離の目安</label>
                    <select id="distance-select"
                            x-model="distance"
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none bg-white">
                        <option value="100">100km（半日コース）</option>
                        <option value="200" selected>200km（1日コース）</option>
                        <option value="300">300km（ロングツーリング）</option>
                    </select>
                </div>

                {{-- 好み --}}
                <div>
                    <label for="preference-select" class="block text-sm font-bold text-gray-700 mb-1">好み</label>
                    <select id="preference-select"
                            x-model="preference"
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none bg-white">
                        <option value="any">バランス重視</option>
                        <option value="sea">海沿い・海岸線</option>
                        <option value="mountain">山・峠道</option>
                        <option value="onsen">温泉重視</option>
                    </select>
                </div>
            </div>

            <button @click="suggest()"
                    :disabled="loading || !origin.trim()"
                    class="w-full sm:w-auto px-6 py-2.5 bg-blue-600 text-white font-bold rounded-lg hover:bg-blue-700 transition disabled:opacity-50 disabled:cursor-not-allowed text-sm">
                <span x-show="!loading">ルートを提案してもらう</span>
                <span x-show="loading" class="inline-flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    AIがルートを考えています...
                </span>
            </button>
        </div>

        {{-- エラー表示 --}}
        <template x-if="error">
            <div class="bg-red-50 border border-red-200 text-red-700 px-5 py-4 rounded-xl mb-8 text-sm">
                <span x-text="error"></span>
            </div>
        </template>

        {{-- ローディング --}}
        <template x-if="loading">
            <div class="text-center py-12">
                <div class="inline-flex flex-col items-center gap-4">
                    <svg class="animate-spin h-10 w-10 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <p class="text-gray-500 text-sm font-bold">周辺のスポットを分析してルートを作成中...</p>
                    <p class="text-gray-400 text-xs">10秒ほどかかる場合があります</p>
                </div>
            </div>
        </template>

        {{-- 結果表示 --}}
        <template x-if="result && !loading">
            <div>
                {{-- ルート情報ヘッダー --}}
                <div class="bg-white rounded-xl border border-gray-200 p-5 mb-6">
                    <h2 class="text-xl font-bold text-gray-900 mb-2" x-text="result.title"></h2>
                    <p class="text-gray-600 text-sm mb-4" x-text="result.description"></p>
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="inline-flex items-center px-2.5 py-1 text-xs font-bold rounded-full bg-blue-100 text-blue-700">
                            <span x-text="result.total_distance_km + 'km'"></span>
                        </span>
                        <span class="inline-flex items-center px-2.5 py-1 text-xs font-bold rounded-full bg-green-100 text-green-700">
                            約<span x-text="result.estimated_hours"></span>時間
                        </span>
                        <span class="inline-flex items-center px-2.5 py-1 text-xs font-bold rounded-full bg-gray-100 text-gray-700">
                            <span x-text="result.stops.length"></span>箇所
                        </span>
                    </div>
                </div>

                {{-- マップ --}}
                <div class="mb-6 rounded-xl overflow-hidden border border-gray-200 shadow-sm">
                    <div id="planner-map"></div>
                </div>

                {{-- ストップ一覧 --}}
                <div class="bg-white rounded-xl border border-gray-200 overflow-hidden mb-6">
                    <h3 class="text-lg font-bold text-gray-900 px-5 py-3 bg-gray-50 border-b border-gray-200">ルート詳細</h3>
                    <div class="divide-y divide-gray-100">
                        <template x-for="(stop, idx) in result.stops" :key="idx">
                            <div class="flex items-start gap-4 px-5 py-4">
                                <div class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold text-white"
                                     :class="{
                                         'bg-blue-500': stop.type === 'touring_spot',
                                         'bg-green-500': stop.type === 'roadside_station',
                                         'bg-gray-400': stop.type === 'waypoint'
                                     }">
                                    <span x-text="idx === 0 ? 'S' : (idx === result.stops.length - 1 && stop.type === 'waypoint' ? 'G' : idx)"></span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 mb-0.5">
                                        <span class="font-bold text-gray-900 text-sm" x-text="stop.name"></span>
                                        <span class="text-[10px] px-1.5 py-0.5 rounded font-bold"
                                              :class="{
                                                  'bg-blue-100 text-blue-700': stop.type === 'touring_spot',
                                                  'bg-green-100 text-green-700': stop.type === 'roadside_station',
                                                  'bg-gray-100 text-gray-600': stop.type === 'waypoint'
                                              }"
                                              x-text="stop.type === 'touring_spot' ? 'スポット' : (stop.type === 'roadside_station' ? '道の駅' : '経由地')"></span>
                                    </div>
                                    <p class="text-xs text-gray-500" x-text="stop.note"></p>
                                    <template x-if="stop.stay_minutes > 0">
                                        <span class="text-xs text-gray-400 mt-1 inline-block">
                                            滞在目安: <span x-text="stop.stay_minutes"></span>分
                                        </span>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- アクションボタン --}}
                <div class="flex flex-wrap gap-3">
                    <a :href="googleMapsUrl"
                       target="_blank"
                       rel="noopener noreferrer"
                       class="inline-flex items-center gap-2 bg-blue-600 text-white px-5 py-2.5 rounded-lg hover:bg-blue-700 transition text-sm font-bold">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        Googleマップで開く
                    </a>
                    <button @click="suggest()"
                            :disabled="loading"
                            class="inline-flex items-center gap-2 bg-gray-100 text-gray-700 px-5 py-2.5 rounded-lg hover:bg-gray-200 transition text-sm font-bold">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        別のルートを提案
                    </button>
                </div>
            </div>
        </template>
    </div>

    <x-slot:scripts>
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
        <script src="{{ asset('js/touring/planner.js') }}?v={{ asset_buster(public_path('js/touring/planner.js')) }}" charset="utf-8"></script>
    </x-slot:scripts>
</x-layout>
