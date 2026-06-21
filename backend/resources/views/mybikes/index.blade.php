<x-layout>
    <x-slot:title>Myガレージ - MotoHub</x-slot:title>

    <x-slot:navigation>
        <x-navigation :showSearch="true" />
    </x-slot:navigation>

    <div class="bg-gray-50 min-h-screen py-10">
        <div class="max-w-4xl mx-auto px-4">
            
            <div class="flex items-center justify-between mb-8">
                <h1 class="text-2xl font-black text-gray-900 flex items-center gap-2">
                    <i data-lucide="warehouse" class="w-6 h-6"></i> Myガレージ
                </h1>

                {{-- 愛車追加ボタン --}}
                <button onclick="document.getElementById('add-bike-modal').showModal()" class="bg-black text-white px-4 py-2 rounded-xl text-xs font-bold flex items-center gap-2 hover:bg-gray-800 transition-colors shadow-lg">
                    <i data-lucide="plus" class="w-4 h-4"></i> 愛車を追加
                </button>
            </div>


            @if(session('success'))
                <div class="mb-6 p-4 bg-green-100 text-green-700 text-sm font-bold rounded-xl border border-green-200 flex items-center gap-2 animate-in fade-in slide-in-from-top-2">
                    <i data-lucide="check-circle" class="w-5 h-5"></i>
                    {{ session('success') }}
                </div>
            @endif

            @if(session('odometer_warning'))
                <div class="mb-6 p-4 bg-amber-50 text-amber-700 text-sm font-bold rounded-xl border border-amber-200 flex items-start gap-2 animate-in fade-in slide-in-from-top-2">
                    <i data-lucide="alert-triangle" class="w-5 h-5 shrink-0 mt-0.5"></i>
                    <span>{{ session('odometer_warning') }}</span>
                </div>
            @endif

            @if($myBikes->isEmpty())
                <div class="bg-white rounded-3xl p-12 text-center border border-gray-100 shadow-sm">
                    <div class="w-20 h-20 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-6">
                        <i data-lucide="bike" class="w-10 h-10 text-gray-300"></i>
                    </div>
                    <h2 class="text-lg font-black text-gray-800 mb-2">給油を記録するだけ。平均燃費が見える。</h2>
                    <p class="text-gray-500 text-sm font-bold mb-3">
                        愛車を登録して給油を記録するだけで、あなたのバイクの平均燃費が自動で見えるようになります。
                    </p>
                    <p class="text-gray-400 text-[11px] font-bold mb-8">
                        ＋ 維持費（整備・給油）が自動で貯まる／オイル交換などの時期に気づける
                    </p>
                    <button onclick="document.getElementById('add-bike-modal').showModal()" class="inline-block bg-blue-600 text-white px-8 py-3 rounded-xl font-bold hover:bg-blue-500 transition-colors shadow-md">
                        愛車を登録する
                    </button>
                </div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6"
                     x-data="{ open: false, action: '', name: '', odo: '', label: '',
                        openEdit(b) { const d = b.dataset; this.action = d.action; this.name = d.name; this.odo = d.odo; this.label = d.label; this.open = true; } }">
                    @foreach($myBikes as $bike)
                        <div class="relative group">
                        <a href="{{ route('mybikes.show', $bike->id) }}" class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md transition block">

                            <div class="relative h-48 bg-gray-900 overflow-hidden">
                                @if($bike->display_image)
                                    <img src="{{ $bike->display_image }}" class="w-full h-full object-cover opacity-70 group-hover:scale-105 transition-transform duration-700" loading="lazy" decoding="async">
                                @else
                                    <div class="flex items-center justify-center h-full text-white/30">
                                        <i data-lucide="bike" class="w-12 h-12"></i>
                                    </div>
                                @endif
                                <div class="absolute inset-0 bg-gradient-to-t from-black/80 to-transparent"></div>
                                <div class="absolute bottom-0 left-0 p-5 text-white w-full">
                                    <div class="text-[10px] font-bold opacity-80 mb-1">{{ $bike->bikeModel->manufacturer->name }}</div>
                                    <h2 class="text-2xl font-black leading-none truncate">{{ $bike->display_name }}</h2>
                                </div>
                            </div>

                            <div class="p-5">
                                <div class="flex justify-between items-center mb-4">
                                    <div>
                                        <p class="text-[10px] font-bold text-gray-400">総走行距離</p>
                                        <p class="text-lg font-black text-gray-900 font-mono">{{ number_format($bike->current_odometer, 1) }} <span class="text-xs">km</span></p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-[10px] font-bold text-gray-400">最新燃費</p>
                                            @if($bike->latest_efficiency)
                                                <p class="text-lg font-black text-blue-600">{{ number_format($bike->latest_efficiency, 1) }} <span class="text-xs">km/L</span></p>
                                            @else
                                                <p class="text-sm font-bold text-gray-300">-</p>
                                            @endif
                                        </div>
                                </div>
                                
                                <div class="flex items-center justify-center gap-2 bg-gray-50 text-gray-500 py-3 rounded-xl text-xs font-bold group-hover:bg-black group-hover:text-white transition-colors">
                                    <i data-lucide="edit-3" class="w-3.5 h-3.5"></i> 記録をつける・見る
                                </div>
                            </div>
                        </a>
                        {{-- 愛車情報の編集（愛称＋走行距離）。カードのリンクとは別ボタン＝aの入れ子を避ける --}}
                        <button type="button" @click="openEdit($el)"
                                data-action="{{ route('mybikes.update', $bike->id) }}"
                                data-name="{{ $bike->name }}"
                                data-odo="{{ $bike->initial_odometer }}"
                                data-label="{{ $bike->display_name }}"
                                class="absolute top-3 right-3 z-10 w-9 h-9 rounded-full bg-black/50 hover:bg-black/70 text-white flex items-center justify-center backdrop-blur-sm transition-colors"
                                title="愛車情報を編集" aria-label="愛車情報を編集">
                            <i data-lucide="pencil" class="w-4 h-4"></i>
                        </button>
                        </div>
                    @endforeach

                    {{-- 愛車情報 編集モーダル（共有・Alpine。@js等の属性埋め込みは使わずdatasetで渡す） --}}
                    <div x-show="open" x-cloak @keydown.escape.window="open = false" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="display: none;">
                        <div class="absolute inset-0 bg-black/50" @click="open = false"></div>
                        <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-md p-6">
                            <h2 class="text-lg font-black text-gray-900 mb-1 flex items-center gap-2"><i data-lucide="pencil" class="w-5 h-5 text-blue-600"></i> 愛車情報を編集</h2>
                            <p class="text-xs text-gray-400 font-bold mb-5" x-text="label"></p>
                            <form :action="action" method="POST" class="space-y-4">
                                @csrf
                                @method('PUT')
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 mb-1">愛称 <span class="text-[10px] font-normal text-gray-400">（未入力なら車種名）</span></label>
                                    <input type="text" name="name" x-model="name" maxlength="50" placeholder="例: 通勤号"
                                           class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 h-12 text-sm font-bold focus:border-blue-500 focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50 outline-none transition">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 mb-1">走行距離 (km)</label>
                                    <input type="number" step="0.1" min="0" name="initial_odometer" x-model="odo" placeholder="例: 12000"
                                           class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 h-12 text-sm font-bold focus:border-blue-500 focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50 outline-none transition">
                                    <p class="text-[10px] text-gray-400 mt-1">※km単価の計算に使われます。給油・整備の記録より小さい値が基本です。</p>
                                </div>
                                <div class="flex items-center gap-3 pt-2">
                                    <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-500 text-white font-black py-3 rounded-xl shadow-lg transition active:scale-95">更新する</button>
                                    <button type="button" @click="open = false" class="shrink-0 text-xs font-bold text-gray-500 hover:text-gray-700 px-4 py-3 rounded-xl border border-gray-200 hover:bg-gray-50 transition-colors">キャンセル</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- 愛車登録モーダル --}}
    <dialog id="add-bike-modal" class="modal rounded-3xl p-0 backdrop:bg-black/50 w-full max-w-md shadow-2xl">
        <div class="bg-white p-6 sm:p-8">
            <h3 class="text-xl font-black text-gray-900 mb-6 flex items-center gap-2">
                <i data-lucide="plus-circle" class="w-6 h-6 text-blue-600"></i> 愛車を登録する
            </h3>
            
            <form action="{{ route('mybikes.store') }}" method="POST">
                @csrf
                <div class="mb-6 relative">
                    <label class="block text-xs font-bold text-gray-500 mb-2">車種を検索</label>
                    <input type="text" id="model-search" class="w-full rounded-xl border-gray-200 font-bold p-3 focus:ring-blue-500 focus:border-blue-500" placeholder="PCX, CB400..." autocomplete="off">
                    <input type="hidden" name="bike_model_id" id="selected-model-id" required>
                    
                    {{-- 検索候補リスト --}}
                    <div id="model-suggestions" class="absolute left-0 right-0 top-full mt-1 bg-white border border-gray-100 shadow-xl rounded-xl z-50 max-h-60 overflow-y-auto hidden"></div>
                </div>

                <div class="mb-6">
                    <label class="block text-xs font-bold text-gray-500 mb-2">現在の走行距離 (km)</label>
                    <input type="number" name="initial_odometer" class="w-full rounded-xl border-gray-200 font-bold p-3 focus:ring-blue-500 focus:border-blue-500" placeholder="0">
                </div>
                
                <div class="mb-8">
                    <label class="block text-xs font-bold text-gray-500 mb-2">愛称（任意）</label>
                    <input type="text" name="name" class="w-full rounded-xl border-gray-200 font-bold p-3 focus:ring-blue-500 focus:border-blue-500" placeholder="マイPCX">
                </div>

                <div class="flex gap-3 justify-end pt-4 border-t border-gray-100">
                    <button type="button" onclick="document.getElementById('add-bike-modal').close()" class="px-5 py-3 text-gray-500 font-bold hover:bg-gray-100 rounded-xl transition-colors">キャンセル</button>
                    <button type="submit" class="px-8 py-3 bg-black text-white font-black rounded-xl hover:bg-gray-800 transition-colors shadow-lg active:scale-95">登録する</button>
                </div>
            </form>
        </div>
    </dialog>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const searchInput = document.getElementById('model-search');
            const suggestionsBox = document.getElementById('model-suggestions');
            const hiddenInput = document.getElementById('selected-model-id');
            // ダミー画像URL (Unsplash)
            const PLACEHOLDER_IMG = 'https://images.unsplash.com/photo-1558981403-c5f9899a28bc?q=80&w=200&auto=format&fit=crop';

            if (searchInput) {
                console.log('Search script loaded.');

                searchInput.addEventListener('input', async (e) => {
                    const val = e.target.value;
                    
                    // ★修正: 1文字以上で検索開始
                    if (val.length < 1) {
                        suggestionsBox.classList.add('hidden');
                        return;
                    }

                    try {
                        const res = await fetch(`/garage/api/search-models?q=${val}`);
                        
                        if (!res.ok) {
                            console.error('API Error:', res.status);
                            return;
                        }

                        const models = await res.json();

                        suggestionsBox.innerHTML = '';
                        if (models.length > 0) {
                            suggestionsBox.classList.remove('hidden');
                            models.forEach(model => {
                                const div = document.createElement('div');
                                div.className = 'p-3 hover:bg-blue-50 cursor-pointer text-sm font-bold text-gray-700 flex items-center gap-3 border-b border-gray-50 last:border-0 transition-colors';
                                
                                // 画像があれば表示、なければダミー画像
                                let imgTag = '';
                                const imgSrc = model.image ? model.image : PLACEHOLDER_IMG;
                                
                                imgTag = `<img src="${imgSrc}" class="w-10 h-10 object-cover rounded-lg bg-gray-100" onerror="this.onerror=null; this.src='${PLACEHOLDER_IMG}';">`;

                                div.innerHTML = `${imgTag}<span>${model.text}</span>`;
                                div.onclick = () => {
                                    searchInput.value = model.text;
                                    hiddenInput.value = model.id;
                                    suggestionsBox.classList.add('hidden');
                                };
                                suggestionsBox.appendChild(div);
                            });
                            if(window.lucide) lucide.createIcons();
                        } else {
                            suggestionsBox.classList.add('hidden');
                        }
                    } catch(e) {
                        console.error('Fetch error:', e);
                    }
                });
                
                // 外側クリックで閉じる
                document.addEventListener('click', (e) => {
                    if (!searchInput.contains(e.target) && !suggestionsBox.contains(e.target)) {
                        suggestionsBox.classList.add('hidden');
                    }
                });
            }

            {{-- ?bike_model_id= prefill（レビュー投稿後の導線など）: 該当車種を選択済みで登録モーダルを開く --}}
            @if($prefillModel ?? null)
            (function () {
                if (hiddenInput) hiddenInput.value = {{ (int) $prefillModel['id'] }};
                if (searchInput) searchInput.value = @json($prefillModel['name']);
                const modal = document.getElementById('add-bike-modal');
                if (modal && modal.showModal) modal.showModal();
            })();
            @endif
        });
        
        if (typeof lucide !== 'undefined') lucide.createIcons();
    </script>
</x-layout>