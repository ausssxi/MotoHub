<x-layout>
    <x-slot:title>{{ $myBike->name }} の記録 | MotoHub</x-slot:title>
    <x-slot:navigation><x-navigation :showSearch="false" /></x-slot:navigation>

    <div class="bg-gray-50 min-h-screen py-8">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            
            {{-- ヘッダー --}}
            <div class="flex items-center gap-4 mb-8">
                <a href="{{ route('mybikes.index') }}" class="w-10 h-10 bg-white rounded-full flex items-center justify-center shadow-sm border border-gray-100 text-gray-400 hover:text-black transition-colors">
                    <i data-lucide="arrow-left" class="w-5 h-5"></i>
                </a>
                <div>
                    <h1 class="text-2xl font-black text-gray-900">{{ $myBike->name }}</h1>
                    <div class="text-xs font-bold text-gray-500 flex items-center gap-2">
                        <i data-lucide="gauge" class="w-3 h-3"></i>
                        総走行距離: {{ number_format($myBike->current_odometer) }} km
                    </div>
                </div>
            </div>

            <div x-data="{ tab: '{{ $errors->has('maintained_at') || $errors->has('title') ? 'maintenance' : 'fuel' }}' }" class="space-y-6">
                
                {{-- タブ切り替え --}}
                <div class="bg-white p-1 rounded-xl inline-flex border border-gray-100 shadow-sm">
                    <button @click="tab = 'fuel'" :class="tab === 'fuel' ? 'bg-blue-100 text-blue-700' : 'text-gray-500 hover:text-gray-700'" class="px-6 py-2 rounded-lg text-xs font-black transition-colors flex items-center gap-2">
                        <i data-lucide="fuel" class="w-4 h-4"></i> 給油・燃費
                    </button>
                    <button @click="tab = 'maintenance'" :class="tab === 'maintenance' ? 'bg-orange-100 text-orange-700' : 'text-gray-500 hover:text-gray-700'" class="px-6 py-2 rounded-lg text-xs font-black transition-colors flex items-center gap-2">
                        <i data-lucide="wrench" class="w-4 h-4"></i> 整備・カスタム
                    </button>
                </div>

                {{-- 1. 給油記録セクション --}}
                <div x-show="tab === 'fuel'" class="animate-in fade-in slide-in-from-bottom-2" style="display: none;">
                    {{-- 入力フォーム --}}
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-6">
                        <h3 class="font-black text-gray-900 mb-4 flex items-center gap-2"><i data-lucide="plus-circle" class="w-4 h-4 text-blue-500"></i> 給油を記録</h3>
                        
                        <form action="{{ route('mybikes.fuel.store', $myBike->id) }}" method="POST" class="space-y-4">
                            @csrf
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-gray-400 mb-1 ml-1">給油日</label>
                                    <input type="date" name="filled_at" value="{{ old('filled_at', date('Y-m-d')) }}" required 
                                        class="block w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm font-bold focus:border-blue-500 focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50 outline-none transition-all">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-400 mb-1 ml-1">総走行距離 (km)</label>
                                    <input type="number" step="0.1" name="odometer" value="{{ old('odometer') }}" placeholder="例: {{ $myBike->current_odometer }}" required 
                                        class="block w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm font-bold focus:border-blue-500 focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50 outline-none transition-all">
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-gray-400 mb-1 ml-1">給油量 (L)</label>
                                    <input type="number" step="0.01" name="quantity" value="{{ old('quantity') }}" placeholder="例: 5.5" required 
                                        class="block w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm font-bold focus:border-blue-500 focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50 outline-none transition-all">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-400 mb-1 ml-1">金額 (円) <span class="font-normal text-gray-300">任意</span></label>
                                    <input type="number" name="cost" value="{{ old('cost') }}" placeholder="例: 1000" 
                                        class="block w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm font-bold focus:border-blue-500 focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50 outline-none transition-all">
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-gray-400 mb-1 ml-1">メモ <span class="font-normal text-gray-300">任意</span></label>
                                <input type="text" name="memo" value="{{ old('memo') }}" placeholder="例: 環七のGSで給油" 
                                    class="block w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm font-bold focus:border-blue-500 focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50 outline-none transition-all">
                            </div>

                            @if ($errors->any() && !$errors->has('maintained_at'))
                                <div class="p-3 bg-red-50 text-red-600 text-xs font-bold rounded-xl border border-red-100">
                                    <ul class="list-disc list-inside">
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            <div class="pt-2 flex items-center justify-between">
                                <div>
                                    <label class="flex items-center gap-2 cursor-pointer select-none">
                                        <input type="hidden" name="is_full_tank" value="0">
                                        <input type="checkbox" name="is_full_tank" value="1" {{ old('is_full_tank', '1') == '1' ? 'checked' : '' }} class="w-5 h-5 rounded text-blue-600 focus:ring-blue-500 border-gray-300">
                                        <span class="text-xs font-bold text-gray-600">満タン給油</span>
                                    </label>
                                    <p class="text-[9px] text-gray-400 mt-1 ml-7">
                                        ※チェックを入れると燃費が計算されます
                                    </p>
                                </div>
                                <button type="submit" class="w-full sm:w-auto bg-blue-600 hover:bg-blue-500 text-white font-black px-8 py-3 rounded-xl shadow-lg transition-all transform active:scale-95">
                                    記録する
                                </button>
                            </div>
                        </form>
                    </div>

                    {{-- 履歴リスト --}}
                    <div class="space-y-3">
                        @foreach($myBike->fuelLogs as $log)
                        {{-- ★修正: flexを外してブロック要素にし、内部でレイアウトを分ける --}}
                        <div class="bg-white rounded-xl p-4 border border-gray-100">
                            {{-- 上段: メイン情報（常に横並び） --}}
                            <div class="flex items-center justify-between">
                                <div>
                                    <div class="text-[10px] font-bold text-gray-400 mb-0.5">{{ $log->filled_at->format('Y/m/d') }}</div>
                                    <div class="text-sm font-black text-gray-800">{{ number_format($log->odometer) }} km</div>
                                </div>
                                <div class="text-right">
                                    <div class="text-lg font-black text-blue-600">
                                        {{ $log->efficiency ? $log->efficiency . ' km/L' : '-' }}
                                    </div>
                                    <div class="text-[10px] font-bold text-gray-400">
                                        {{ $log->quantity }}L / {{ number_format($log->cost) }}円
                                    </div>
                                </div>
                            </div>
                            
                            {{-- 下段: メモ（存在する場合のみ表示） --}}
                            @if($log->memo)
                                <div class="mt-2 pt-2 border-t border-gray-50 text-xs text-gray-500 bg-gray-50 p-2 rounded-lg break-words">
                                    {{ $log->memo }}
                                </div>
                            @endif
                        </div>
                        @endforeach
                    </div>
                </div>

                {{-- 2. 整備記録セクション --}}
                <div x-show="tab === 'maintenance'" class="hidden animate-in fade-in slide-in-from-bottom-2">
                    {{-- 入力フォーム --}}
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-6">
                        <h3 class="font-black text-gray-900 mb-4 flex items-center gap-2"><i data-lucide="plus-circle" class="w-4 h-4 text-orange-500"></i> 整備を記録</h3>
                        
                        <form action="{{ route('mybikes.maintenance.store', $myBike->id) }}" method="POST" class="space-y-4">
                            @csrf
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-gray-400 mb-1 ml-1">整備日</label>
                                    <input type="date" name="maintained_at" value="{{ old('maintained_at', date('Y-m-d')) }}" required 
                                        class="block w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm font-bold focus:border-orange-500 focus:ring-2 focus:ring-orange-500 focus:ring-opacity-50 outline-none transition-all">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-400 mb-1 ml-1">内容</label>
                                    <input type="text" name="title" value="{{ old('title') }}" placeholder="例: オイル交換" required 
                                        class="block w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm font-bold focus:border-orange-500 focus:ring-2 focus:ring-orange-500 focus:ring-opacity-50 outline-none transition-all">
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-gray-400 mb-1 ml-1">費用 (円) <span class="font-normal text-gray-300">任意</span></label>
                                    <input type="number" name="cost" value="{{ old('cost') }}" placeholder="例: 3000" 
                                        class="block w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm font-bold focus:border-orange-500 focus:ring-2 focus:ring-orange-500 focus:ring-opacity-50 outline-none transition-all">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-400 mb-1 ml-1">時走行距離 (km) <span class="font-normal text-gray-300">任意</span></label>
                                    <input type="number" name="odometer" value="{{ old('odometer') }}" placeholder="例: {{ $myBike->current_odometer }}" 
                                        class="block w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm font-bold focus:border-orange-500 focus:ring-2 focus:ring-orange-500 focus:ring-opacity-50 outline-none transition-all">
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-gray-400 mb-1 ml-1">詳細メモ <span class="font-normal text-gray-400">任意</span></label>
                                <textarea name="note" placeholder="使用オイル: ホンダG2 10W-40" rows="2" 
                                    class="block w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm font-bold focus:border-orange-500 focus:ring-2 focus:ring-orange-500 focus:ring-opacity-50 outline-none transition-all">{{ old('note') }}</textarea>
                            </div>

                            @if ($errors->any() && $errors->has('maintained_at'))
                                <div class="p-3 bg-red-50 text-red-600 text-xs font-bold rounded-xl border border-red-100">
                                    <ul class="list-disc list-inside">
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            <div class="pt-2 text-right">
                                <button type="submit" class="w-full bg-orange-600 hover:bg-orange-500 text-white font-black py-3 rounded-xl shadow-lg transition-all transform active:scale-95">
                                    記録する
                                </button>
                            </div>
                        </form>
                    </div>

                    {{-- 履歴リスト --}}
                    <div class="space-y-3">
                        @forelse($myBike->maintenanceLogs as $log)
                        <div class="bg-white rounded-xl p-4 border border-gray-100">
                            <div class="flex justify-between items-start mb-2">
                                <div>
                                    <div class="text-[10px] font-bold text-gray-400 mb-0.5">{{ $log->maintained_at->format('Y/m/d') }}</div>
                                    <div class="text-sm font-black text-gray-900">{{ $log->title }}</div>
                                </div>
                                <div class="text-right">
                                    <div class="text-sm font-black text-orange-600">{{ number_format($log->cost) }}円</div>
                                </div>
                            </div>
                            @if($log->note)
                            <div class="text-xs text-gray-500 bg-gray-50 p-2 rounded-lg">
                                {{ $log->note }}
                            </div>
                            @endif
                        </div>
                        @empty
                        <div class="text-center py-10 border-2 border-dashed border-gray-200 rounded-2xl text-gray-400 font-bold text-sm">
                            まだ整備記録がありません
                        </div>
                        @endforelse
                    </div>
                </div>

                <div class="mt-16 pt-10 border-t border-gray-200">
                    <div class="text-center">
                        <p class="text-xs font-bold text-gray-400 mb-4">
                            この愛車を削除しますか？<br>
                            <span class="text-[10px]">※記録した給油・整備データもすべて削除されます。</span>
                        </p>
                        <form action="{{ route('mybikes.destroy', $myBike->id) }}" method="POST" onsubmit="return confirm('本当に「{{ $myBike->name }}」を削除しますか？\nこの操作は取り消せません。');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-xs font-bold text-red-500 hover:text-red-700 hover:bg-red-50 px-4 py-2 rounded-lg transition-colors inline-flex items-center gap-1">
                                <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                この愛車を削除する
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        if (typeof lucide !== 'undefined') lucide.createIcons();
    </script>
</x-layout>