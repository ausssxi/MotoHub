<x-layout>
    <x-slot:title>{{ $myBike->name }} の記録 | MotoHub</x-slot:title>
    <x-slot:navigation><x-navigation :showSearch="true" /></x-slot:navigation>

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

            {{-- 公開設定（consent + identity）。デフォルト非公開・台ごとopt-in・本名は出さない --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-6">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="font-black text-gray-900 flex items-center gap-2"><i data-lucide="globe" class="w-4 h-4 text-blue-500"></i> 公開設定</h3>
                    @if($myBike->is_public)
                    <span class="text-xs font-black text-green-600 bg-green-50 px-2 py-1 rounded">公開中</span>
                    @else
                    <span class="text-xs font-black text-gray-500 bg-gray-100 px-2 py-1 rounded">非公開（自分のみ）</span>
                    @endif
                </div>

                @if($myBike->is_public)
                    <p class="text-xs text-gray-500 leading-relaxed mb-3">
                        このガレージは公開URLで誰でも閲覧できます。表示名は「<span class="font-bold text-gray-700">{{ auth()->user()->review_display_name ?? '名無しライダー' }}</span>」です（本名は表示されません）。
                    </p>
                    <div class="flex flex-wrap items-center gap-3">
                        <a href="{{ route('garage.public.show', $myBike->id) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 text-xs font-bold text-blue-600 hover:text-blue-800">
                            <i data-lucide="external-link" class="w-3.5 h-3.5"></i> 公開ページを見る
                        </a>
                        <form action="{{ route('mybikes.visibility', $myBike->id) }}" method="POST">
                            @csrf
                            <input type="hidden" name="is_public" value="0">
                            <button type="submit" class="inline-flex items-center gap-1.5 text-xs font-black text-gray-600 bg-gray-100 hover:bg-gray-200 px-4 py-2 rounded-lg transition-colors">
                                <i data-lucide="lock" class="w-3.5 h-3.5"></i> 非公開に戻す
                            </button>
                        </form>
                    </div>
                @else
                    <p class="text-xs text-gray-600 mb-2">公開すると、次の項目が公開URLで<strong>誰でも</strong>見られるようになります：</p>
                    <ul class="text-xs text-gray-500 list-disc list-inside mb-4 space-y-0.5">
                        <li>車種（モデル）・年式・総走行距離</li>
                        <li>整備・カスタム履歴（日付・内容・費用・走行距離）</li>
                        <li>給油記録（日付・燃費・費用・メモ）</li>
                        <li>公開表示名 @if(auth()->user()->review_display_name)「<span class="font-bold">{{ auth()->user()->review_display_name }}</span>」@else（下で設定）@endif（<strong>本名は表示されません</strong>）</li>
                    </ul>
                    <form action="{{ route('mybikes.visibility', $myBike->id) }}" method="POST" class="space-y-3" onsubmit="return confirm('上記の項目を公開URLで誰でも閲覧できるようにします。よろしいですか？');">
                        @csrf
                        <input type="hidden" name="is_public" value="1">
                        @if(empty(auth()->user()->review_display_name))
                        <div>
                            <label class="block text-xs font-bold text-gray-500 mb-1">公開表示名 <span class="text-[10px] font-normal text-gray-400">（公開されます・設定後は変更できません・本名で prefill しません）</span></label>
                            <input type="text" name="review_handle" value="{{ old('review_handle') }}" required maxlength="30" placeholder="公開用の表示名（例: rider_x）"
                                   class="w-full bg-white border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
                            @error('review_handle')<p class="text-red-500 text-xs font-bold mt-1">{{ $message }}</p>@enderror
                        </div>
                        @endif
                        <button type="submit" class="inline-flex items-center gap-1.5 text-sm font-black text-white bg-blue-600 hover:bg-blue-700 px-5 py-2.5 rounded-lg transition-colors">
                            <i data-lucide="globe" class="w-4 h-4"></i> このガレージを公開する
                        </button>
                    </form>
                @endif
            </div>

            {{-- retention核ダッシュボード（private・本人のみ） --}}
            <div class="space-y-6 mb-6">
                {{-- 維持費 --}}
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-black text-gray-900 flex items-center gap-2"><i data-lucide="wallet" class="w-4 h-4 text-green-500"></i> 維持費</h3>
                        <a href="{{ route('mybikes.export', $myBike->id) }}" class="inline-flex items-center gap-1.5 text-xs font-bold text-gray-600 bg-gray-100 hover:bg-gray-200 px-3 py-1.5 rounded-lg transition-colors">
                            <i data-lucide="download" class="w-3.5 h-3.5"></i> CSV出力
                        </a>
                    </div>
                    <div class="grid grid-cols-2 gap-3 mb-4">
                        <div class="bg-green-50 rounded-xl p-4">
                            <div class="text-[10px] font-black text-green-600 uppercase tracking-widest mb-1">この1年</div>
                            <div class="text-xl font-black text-gray-900">{{ number_format($dashboard['cost']['last12']) }}<span class="text-xs text-gray-400 ml-0.5">円</span></div>
                        </div>
                        <div class="bg-gray-50 rounded-xl p-4">
                            <div class="text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1">累計</div>
                            <div class="text-xl font-black text-gray-900">{{ number_format($dashboard['cost']['total']) }}<span class="text-xs text-gray-400 ml-0.5">円</span></div>
                            <div class="text-[10px] font-bold text-gray-400 mt-1">整備 {{ number_format($dashboard['cost']['maintenance_total']) }}円 ／ 給油 {{ number_format($dashboard['cost']['fuel_total']) }}円</div>
                        </div>
                    </div>
                    @if(!empty($dashboard['cost']['by_year']))
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs">
                            <thead><tr class="border-b border-gray-100 text-gray-400 font-bold"><th class="text-left py-1.5">年</th><th class="text-right py-1.5">整備</th><th class="text-right py-1.5">給油</th><th class="text-right py-1.5">合計</th></tr></thead>
                            <tbody class="divide-y divide-gray-50">
                                @foreach($dashboard['cost']['by_year'] as $year => $row)
                                <tr><td class="py-1.5 font-bold text-gray-700">{{ $year }}年</td><td class="py-1.5 text-right text-gray-500">{{ number_format($row['maintenance']) }}</td><td class="py-1.5 text-right text-gray-500">{{ number_format($row['fuel']) }}</td><td class="py-1.5 text-right font-black text-gray-900">{{ number_format($row['total']) }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @endif
                </div>

                {{-- 燃費グラフ --}}
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-black text-gray-900 flex items-center gap-2"><i data-lucide="trending-up" class="w-4 h-4 text-blue-500"></i> 燃費の推移</h3>
                        @if($dashboard['fuelChart']['average'] !== null)
                        <span class="text-xs font-black text-blue-600">平均 {{ $dashboard['fuelChart']['average'] }} km/L</span>
                        @endif
                    </div>
                    @if(count($dashboard['fuelChart']['data']) > 0)
                    <div class="relative h-56"><canvas id="fuelChart"></canvas></div>
                    @else
                    <p class="text-xs text-gray-400 font-bold text-center py-8">満タン給油を2回以上記録すると燃費グラフが表示されます。</p>
                    @endif
                </div>

                {{-- 整備リマインダー（距離ベース） --}}
                @if(!empty($dashboard['reminders']))
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                    <h3 class="font-black text-gray-900 flex items-center gap-2 mb-4"><i data-lucide="bell" class="w-4 h-4 text-orange-500"></i> 整備リマインダー</h3>
                    <div class="space-y-2">
                        @foreach($dashboard['reminders'] as $r)
                        <div class="flex items-center justify-between bg-gray-50 rounded-xl px-4 py-2.5">
                            <div class="min-w-0">
                                <span class="text-sm font-bold text-gray-800">{{ $r['title'] }}</span>
                                @if($r['over'])<span class="ml-2 text-[10px] font-black text-orange-600 bg-orange-100 px-1.5 py-0.5 rounded">目安超過</span>@endif
                            </div>
                            <div class="text-right shrink-0 ml-2">
                                <div class="text-sm font-black text-gray-900">前回から {{ number_format($r['distance']) }} km</div>
                                @if($r['guideline'])<div class="text-[10px] font-bold text-gray-400">目安 {{ number_format($r['guideline']) }} km</div>@endif
                            </div>
                        </div>
                        @endforeach
                    </div>
                    <p class="text-[10px] text-gray-400 mt-3">※「目安」は一般的な交換時期の参考値です。実際の交換時期は車種・使用状況により異なります。</p>
                </div>
                @endif
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
                                    {{-- h-12を削除し、py-3に変更してテキストを垂直中央に配置 --}}
                                    <input type="date" name="filled_at" value="{{ old('filled_at', date('Y-m-d')) }}" required 
                                        class="appearance-none block w-full py-3 bg-gray-50 border border-gray-200 rounded-xl px-4 text-sm font-bold focus:border-blue-500 focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50 outline-none transition">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-400 mb-1 ml-1">総走行距離 (km)</label>
                                    <input type="number" step="0.1" name="odometer" value="{{ old('odometer') }}" placeholder="例: {{ $myBike->current_odometer }}" required 
                                        class="appearance-none block w-full h-12 bg-gray-50 border border-gray-200 rounded-xl px-4 text-sm font-bold focus:border-blue-500 focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50 outline-none transition">
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-gray-400 mb-1 ml-1">給油量 (L)</label>
                                    <input type="number" step="0.01" name="quantity" value="{{ old('quantity') }}" placeholder="例: 5.5" required 
                                        class="appearance-none block w-full h-12 bg-gray-50 border border-gray-200 rounded-xl px-4 text-sm font-bold focus:border-blue-500 focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50 outline-none transition">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-400 mb-1 ml-1">金額 (円) <span class="font-normal text-gray-300">任意</span></label>
                                    <input type="number" name="cost" value="{{ old('cost') }}" placeholder="例: 1000" 
                                        class="appearance-none block w-full h-12 bg-gray-50 border border-gray-200 rounded-xl px-4 text-sm font-bold focus:border-blue-500 focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50 outline-none transition">
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-gray-400 mb-1 ml-1">メモ <span class="font-normal text-gray-300">任意</span></label>
                                <input type="text" name="memo" value="{{ old('memo') }}" placeholder="例: 環七のGSで給油" 
                                    class="appearance-none block w-full h-12 bg-gray-50 border border-gray-200 rounded-xl px-4 text-sm font-bold focus:border-blue-500 focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50 outline-none transition">
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

                            {{-- スマホでは縦並び(flex-col)、PCでは横並び(sm:flex-row)に変更してレイアウト崩れを防止 --}}
                            <div class="pt-2 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                <div>
                                    <label class="flex items-center gap-2 cursor-pointer select-none">
                                        <input type="hidden" name="is_full_tank" value="0">
                                        <input type="checkbox" name="is_full_tank" value="1" {{ old('is_full_tank', '1') == '1' ? 'checked' : '' }} class="w-5 h-5 rounded text-blue-600 focus:ring-blue-500 border-gray-300">
                                        <span class="text-xs font-bold text-gray-600">満タン給油</span>
                                    </label>
                                    {{-- ml-7をml-0にしてインデントを削除（縦並びなので左寄せでOK） --}}
                                    <p class="text-[9px] text-gray-400 mt-1 ml-0.5">
                                        ※チェックを入れると燃費が計算されます
                                    </p>
                                </div>
                                <button type="submit" class="w-full sm:w-auto bg-blue-600 hover:bg-blue-500 text-white font-black px-8 py-3 rounded-xl shadow-lg transition transform active:scale-95 text-center">
                                    記録する
                                </button>
                            </div>
                        </form>
                    </div>

                    {{-- 履歴リスト --}}
                    <div class="space-y-3">
                        @foreach($myBike->fuelLogs as $log)
                        <div class="bg-white rounded-xl p-4 border border-gray-100">
                            {{-- 上段: メイン情報 --}}
                            <div class="flex items-center justify-between">
                                <div>
                                    <div class="text-[10px] font-bold text-gray-400 mb-0.5">{{ $log->filled_at->format('Y/m/d') }}</div>
                                    <div class="text-sm font-black text-gray-800">{{ number_format($log->odometer) }} km</div>
                                </div>
                                <div class="text-right">
                                    <div class="text-lg font-black text-blue-600">
                                        {{ $log->efficiency ? $log->efficiency . ' km/L' : '-' }}
                                    </div>
                                    <div class="text--[10px] font-bold text-gray-400">
                                        {{ $log->quantity }}L / {{ number_format($log->cost) }}円
                                    </div>
                                </div>
                            </div>
                            
                            {{-- 下段: メモ --}}
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
                                        class="appearance-none block w-full h-12 bg-gray-50 border border-gray-200 rounded-xl px-4 text-sm font-bold focus:border-orange-500 focus:ring-2 focus:ring-orange-500 focus:ring-opacity-50 outline-none transition">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-400 mb-1 ml-1">内容</label>
                                    <input type="text" name="title" value="{{ old('title') }}" placeholder="例: オイル交換" required 
                                        class="appearance-none block w-full h-12 bg-gray-50 border border-gray-200 rounded-xl px-4 text-sm font-bold focus:border-orange-500 focus:ring-2 focus:ring-orange-500 focus:ring-opacity-50 outline-none transition">
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-gray-400 mb-1 ml-1">費用 (円) <span class="font-normal text-gray-300">任意</span></label>
                                    <input type="number" name="cost" value="{{ old('cost') }}" placeholder="例: 3000" 
                                        class="appearance-none block w-full h-12 bg-gray-50 border border-gray-200 rounded-xl px-4 text-sm font-bold focus:border-orange-500 focus:ring-2 focus:ring-orange-500 focus:ring-opacity-50 outline-none transition">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-400 mb-1 ml-1">時走行距離 (km) <span class="font-normal text-gray-300">任意</span></label>
                                    <input type="number" name="odometer" value="{{ old('odometer') }}" placeholder="例: {{ $myBike->current_odometer }}" 
                                        class="appearance-none block w-full h-12 bg-gray-50 border border-gray-200 rounded-xl px-4 text-sm font-bold focus:border-orange-500 focus:ring-2 focus:ring-orange-500 focus:ring-opacity-50 outline-none transition">
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-gray-400 mb-1 ml-1">詳細メモ <span class="font-normal text-gray-400">任意</span></label>
                                <textarea name="note" placeholder="使用オイル: ホンダG2 10W-40" rows="2" 
                                    class="appearance-none block w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm font-bold focus:border-orange-500 focus:ring-2 focus:ring-orange-500 focus:ring-opacity-50 outline-none transition">{{ old('note') }}</textarea>
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
                                <button type="submit" class="w-full bg-orange-600 hover:bg-orange-500 text-white font-black py-3 rounded-xl shadow-lg transition transform active:scale-95">
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
    
    @if(count($dashboard['fuelChart']['data']) > 0)
    {{-- 燃費グラフ（Chart.js CDN・データがある時だけ読込） --}}
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.js"></script>
    <script>
        (function () {
            var el = document.getElementById('fuelChart');
            if (!el || typeof Chart === 'undefined') return;
            new Chart(el, {
                type: 'line',
                data: {
                    labels: @json($dashboard['fuelChart']['labels']),
                    datasets: [{
                        label: '燃費 (km/L)',
                        data: @json($dashboard['fuelChart']['data']),
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59,130,246,0.1)',
                        fill: true, tension: 0.3, pointRadius: 3,
                    }],
                },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: false } } },
            });
        })();
    </script>
    @endif

    <script>
        if (typeof lucide !== 'undefined') lucide.createIcons();
    </script>
</x-layout>