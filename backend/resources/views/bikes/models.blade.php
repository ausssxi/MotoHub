<x-layout>
    <x-slot:title>車種一覧から探す - MotoHub</x-slot:title>

    <x-slot:navigation>
        <x-navigation :totalListingsCount="$totalListingsCount" :showSearch="true" />
    </x-slot:navigation>

    <div class="bg-gray-50 min-h-screen py-12 sm:py-20">
        <div class="max-w-4xl mx-auto px-4">
            
            {{-- ヘッダー部分 --}}
            <div class="mb-10 text-center">
                <h1 class="text-2xl sm:text-3xl font-black text-black mb-2 tracking-tighter">
                    車種から探す
                </h1>
                <p class="text-gray-400 text-xs font-bold tracking-widest uppercase">
                    {{ number_format($totalModelsCount) }} MODELS AVAILABLE
                </p>
            </div>

            {{-- メーカー別アコーディオンリスト --}}
            <div class="space-y-4">
                @foreach($manufacturers as $manufacturer)
                    @if($manufacturer->bike_models_count > 0)
                    
                    {{-- PHPで車種をグループ分け（アルファベット, カタカナ, 数字） --}}
                    @php
                        // グループの初期化（表示順序を定義）
                        $groups = [];
                        
                        // 1. アルファベット A-Z グループ
                        foreach (range('A', 'Z') as $char) {
                            $groups[$char] = [];
                        }

                        // 2. かな行グループ
                        $kanaRows = ['あ行', 'か行', 'さ行', 'た行', 'な行', 'は行', 'ま行', 'や行', 'ら行', 'わ行'];
                        foreach ($kanaRows as $row) {
                            $groups[$row] = [];
                        }
                        
                        // 3. 数字グループ
                        $groups['0-9'] = [];
                        
                        // 4. その他
                        $groups['その他'] = [];
                        
                        foreach($manufacturer->bike_models as $bike) {
                            // 1文字目を取得して正規化
                            // K: 半角カナ->全角カナ, a: 全角英数->半角英数, C: 全角ひらがな->全角カナ
                            $initial = mb_convert_kana(mb_substr($bike->name, 0, 1), 'KaC');
                            
                            if (preg_match('/^[0-9]/', $initial)) {
                                $groups['0-9'][] = $bike;
                            } elseif (preg_match('/^[A-Za-z]/', $initial)) {
                                $key = strtoupper(substr($initial, 0, 1));
                                if (isset($groups[$key])) {
                                    $groups[$key][] = $bike;
                                } else {
                                    $groups['その他'][] = $bike;
                                }
                            } elseif (preg_match('/^[ア-オァ-ォヴ]/u', $initial)) {
                                $groups['あ行'][] = $bike;
                            } elseif (preg_match('/^[カ-コガ-ゴヵヶ]/u', $initial)) {
                                $groups['か行'][] = $bike;
                            } elseif (preg_match('/^[サ-ソザ-ゾ]/u', $initial)) {
                                $groups['さ行'][] = $bike;
                            } elseif (preg_match('/^[タ-トダ-ドッ]/u', $initial)) {
                                $groups['た行'][] = $bike;
                            } elseif (preg_match('/^[ナ-ノ]/u', $initial)) {
                                $groups['な行'][] = $bike;
                            } elseif (preg_match('/^[ハ-ホバ-ボパ-ポ]/u', $initial)) {
                                $groups['は行'][] = $bike;
                            } elseif (preg_match('/^[マ-モ]/u', $initial)) {
                                $groups['ま行'][] = $bike;
                            } elseif (preg_match('/^[ヤ-ヨャ-ョ]/u', $initial)) {
                                $groups['や行'][] = $bike;
                            } elseif (preg_match('/^[ラ-ロ]/u', $initial)) {
                                $groups['ら行'][] = $bike;
                            } elseif (preg_match('/^[ワ-ンヮ]/u', $initial)) {
                                $groups['わ行'][] = $bike;
                            } else {
                                $groups['その他'][] = $bike;
                            }
                        }
                    @endphp

                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden transition-all duration-300 hover:shadow-md" id="maker-section-{{ $manufacturer->id }}">
                        
                        {{-- アコーディオンヘッダー（クリックエリア） --}}
                        <button onclick="toggleMaker({{ $manufacturer->id }})" class="w-full flex items-center justify-between px-6 py-5 bg-white hover:bg-gray-50/50 transition-colors text-left group cursor-pointer focus:outline-none">
                            <div class="flex items-center gap-4">
                                {{-- メーカー名 --}}
                                <h2 class="text-lg sm:text-xl font-black text-gray-800 tracking-tight group-hover:text-blue-600 transition-colors">
                                    {{ $manufacturer->name }}
                                </h2>
                                {{-- モデル数バッジ --}}
                                <span class="text-[10px] font-bold text-gray-400 bg-gray-100 px-2 py-1 rounded-full group-hover:bg-blue-50 group-hover:text-blue-500 transition-colors">
                                    {{ $manufacturer->bike_models_count }} モデル
                                </span>
                            </div>
                            
                            {{-- 開閉アイコン --}}
                            <div class="w-8 h-8 flex items-center justify-center rounded-full bg-gray-50 text-gray-400 group-hover:bg-blue-50 group-hover:text-blue-600 transition-all duration-300 transform" id="maker-icon-{{ $manufacturer->id }}">
                                <i data-lucide="chevron-down" class="w-5 h-5"></i>
                            </div>
                        </button>

                        {{-- メーカー詳細エリア（初期状態は hidden で非表示） --}}
                        <div id="maker-list-{{ $manufacturer->id }}" class="hidden border-t border-gray-100 bg-gray-50/30">
                            <div class="p-2 sm:p-4 space-y-2">
                                
                                {{-- サブアコーディオン（A-Z、50音、数字） --}}
                                @foreach($groups as $label => $list)
                                    @if(count($list) > 0)
                                        @php $subId = $manufacturer->id . '-' . $loop->index; @endphp
                                        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                                            <button onclick="toggleSubGroup('{{ $subId }}')" class="w-full flex items-center justify-between px-4 py-3 bg-white hover:bg-gray-50 transition-colors text-left group/sub">
                                                <div class="flex items-center gap-3">
                                                    <span class="text-sm font-bold text-gray-700 min-w-[2rem]">{{ $label }}</span>
                                                    <span class="text-[10px] font-medium text-gray-400 bg-gray-100 px-1.5 py-0.5 rounded">{{ count($list) }}</span>
                                                </div>
                                                <i data-lucide="chevron-down" class="w-4 h-4 text-gray-300 group-hover/sub:text-blue-500 transition-transform duration-200" id="sub-icon-{{ $subId }}"></i>
                                            </button>

                                            <div id="sub-list-{{ $subId }}" class="hidden border-t border-gray-100 p-3 sm:p-4 bg-gray-50/50">
                                                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                                                    @foreach($list as $bike)
                                                    <a href="{{ route('bikes.search', ['keyword' => $bike->name]) }}"
                                                        class="group/item flex items-center p-3 bg-white rounded-xl border border-gray-200 hover:border-blue-400 hover:shadow-md hover:-translate-y-0.5 transition-all duration-300">
                                                        
                                                        {{-- 車種画像（小さく固定） --}}
                                                        <div class="w-12 h-12 sm:w-14 sm:h-14 rounded-lg bg-gray-50 overflow-hidden flex-shrink-0 mr-3 border border-gray-100 relative">
                                                            @if($bike->image_url)
                                                            <img src="{{ $bike->image_url }}" alt="{{ $bike->name }}" 
                                                                 class="w-full h-full object-cover transform group-hover/item:scale-110 transition-transform duration-500"
                                                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                                            <div class="hidden w-full h-full items-center justify-center text-gray-300">
                                                                <i data-lucide="bike" class="w-5 h-5"></i>
                                                            </div>
                                                            @else
                                                            <div class="w-full h-full flex items-center justify-center text-gray-300">
                                                                <i data-lucide="bike" class="w-5 h-5"></i>
                                                            </div>
                                                            @endif
                                                        </div>

                                                        {{-- 車種名と台数 --}}
                                                        <div class="flex-1 min-w-0">
                                                            <h3 class="text-xs font-bold text-gray-700 leading-tight group-hover/item:text-blue-600 transition-colors line-clamp-1 mb-1">
                                                                {{ $bike->name }}
                                                            </h3>
                                                            {{-- 台数バッジ --}}
                                                            <span class="inline-flex items-center text-[9px] font-medium text-gray-400 bg-gray-50 px-1.5 py-0.5 rounded border border-gray-100">
                                                                <span class="text-blue-500 font-bold mr-0.5">{{ number_format($bike->listings_count ?? 0) }}</span>台
                                                            </span>
                                                        </div>
                                                    </a>
                                                    @endforeach
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                @endforeach

                            </div>
                        </div>
                    </div>
                    @endif
                @endforeach
            </div>

            {{-- ページ下部バックリンク --}}
            <div class="mt-16 pt-8 border-t border-gray-200 text-center">
                <a href="{{ route('bikes.index') }}" class="inline-flex items-center gap-2 text-xs font-black text-gray-400 hover:text-black transition-colors uppercase tracking-widest">
                    <i data-lucide="arrow-left" class="w-4 h-4"></i> トップページに戻る
                </a>
            </div>
        </div>
    </div>

    {{-- アコーディオン制御スクリプト --}}
    <script>
        // メーカー（第1階層）の切り替え
        function toggleMaker(id) {
            const list = document.getElementById('maker-list-' + id);
            const icon = document.getElementById('maker-icon-' + id);
            const section = document.getElementById('maker-section-' + id);
            
            if (list.classList.contains('hidden')) {
                list.classList.remove('hidden');
                list.classList.add('animate-in', 'fade-in', 'slide-in-from-top-2');
                icon.style.transform = 'rotate(180deg)';
                section.classList.add('ring-2', 'ring-blue-100');
            } else {
                list.classList.add('hidden');
                list.classList.remove('animate-in', 'fade-in', 'slide-in-from-top-2');
                icon.style.transform = 'rotate(0deg)';
                section.classList.remove('ring-2', 'ring-blue-100');
            }
        }

        // 50音グループ（第2階層）の切り替え
        function toggleSubGroup(id) {
            const list = document.getElementById('sub-list-' + id);
            const icon = document.getElementById('sub-icon-' + id);
            
            if (list.classList.contains('hidden')) {
                list.classList.remove('hidden');
                list.classList.add('animate-in', 'fade-in', 'slide-in-from-top-1');
                icon.style.transform = 'rotate(180deg)';
            } else {
                list.classList.add('hidden');
                list.classList.remove('animate-in', 'fade-in', 'slide-in-from-top-1');
                icon.style.transform = 'rotate(0deg)';
            }
        }
    </script>
</x-layout>