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
                    @php
                        $gShareUrl = route('garage.public.show', $myBike->id);
                        $gAvg = $dashboard['fuelChart']['average'] ?? null;
                        $gShareText = (auth()->user()->review_display_name ?? '名無しライダー').'の'.$myBike->display_name.'｜走行'.number_format($myBike->current_odometer).'km・累計維持費¥'.number_format($dashboard['cost']['total'] ?? 0).($gAvg !== null ? '・平均燃費'.$gAvg.'km/L' : '').' #MotoHub 愛車ガレージ';
                        $gXIntent = 'https://twitter.com/intent/tweet?text='.rawurlencode($gShareText).'&url='.rawurlencode($gShareUrl);
                    @endphp
                    <div class="flex flex-wrap items-center gap-3" x-data="{ copied: false, copy() { navigator.clipboard?.writeText(@js($gShareUrl)).then(() => { this.copied = true; setTimeout(() => this.copied = false, 1800); }); } }">
                        <a href="{{ $gXIntent }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 text-xs font-bold text-white bg-gray-900 hover:bg-gray-700 px-4 py-2 rounded-lg transition-colors">
                            <i data-lucide="twitter" class="w-3.5 h-3.5"></i> Xでシェア
                        </a>
                        <button type="button" @click="copy()" class="inline-flex items-center gap-1.5 text-xs font-bold text-gray-700 bg-gray-100 hover:bg-gray-200 px-4 py-2 rounded-lg transition-colors">
                            <i data-lucide="link" class="w-3.5 h-3.5"></i> <span x-text="copied ? 'コピーしました' : 'リンクをコピー'">リンクをコピー</span>
                        </button>
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
                        <li>カバー写真（ギャラリーの1枚目）</li>
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

            {{-- 写真ギャラリー（private・本人のみ）。公開面には一切出さない。 --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-6"
                 x-data="{ lightbox: null }">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-black text-gray-900 flex items-center gap-2">
                        <i data-lucide="image" class="w-4 h-4 text-blue-500"></i> 写真ギャラリー
                    </h3>
                    <span class="text-[11px] font-bold text-gray-400">{{ $myBike->images->count() }} / {{ (int) config('garage.max_images') }} 枚</span>
                </div>

                @error('image')
                    <div class="mb-4 p-3 bg-red-50 text-red-600 text-xs font-bold rounded-lg border border-red-100">{{ $message }}</div>
                @enderror

                @if($myBike->images->isEmpty())
                    <p class="text-xs text-gray-500 mb-4">愛車の写真を追加できます（あなただけが見られます）。</p>
                @else
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mb-4">
                        @foreach($myBike->images as $image)
                            <div class="group relative">
                                <button type="button"
                                        @click="lightbox = '{{ route('mybikes.images.show', [$myBike->id, $image->id]) }}'"
                                        class="block w-full aspect-square rounded-xl overflow-hidden bg-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <img src="{{ route('mybikes.images.show', [$myBike->id, $image->id]) }}"
                                         alt="{{ $image->caption ?? '愛車の写真' }}"
                                         class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                                         loading="lazy" decoding="async">
                                </button>

                                {{-- 削除（owner-only） --}}
                                <form action="{{ route('mybikes.images.destroy', [$myBike->id, $image->id]) }}" method="POST"
                                      onsubmit="return confirm('この写真を削除しますか？');"
                                      class="absolute top-1.5 right-1.5 opacity-0 group-hover:opacity-100 transition-opacity">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="w-7 h-7 bg-black/60 hover:bg-red-600 text-white rounded-full flex items-center justify-center backdrop-blur-sm" aria-label="写真を削除">
                                        <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                    </button>
                                </form>

                                {{-- キャプション（インライン編集・owner-only） --}}
                                <form action="{{ route('mybikes.images.caption', [$myBike->id, $image->id]) }}" method="POST" class="mt-1.5">
                                    @csrf
                                    @method('PATCH')
                                    <input type="text" name="caption" value="{{ $image->caption }}" maxlength="100"
                                           placeholder="キャプションを追加"
                                           onchange="this.form.requestSubmit()"
                                           class="w-full bg-gray-50 border border-transparent hover:border-gray-200 focus:border-blue-500 focus:bg-white rounded-lg px-2 py-1 text-[11px] font-bold text-gray-700 focus:ring-1 focus:ring-blue-500 focus:outline-none transition-colors">
                                </form>
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- アップロード（上限未満のときのみ） --}}
                @if($myBike->images->count() < (int) config('garage.max_images'))
                    <form action="{{ route('mybikes.images.store', $myBike->id) }}" method="POST" enctype="multipart/form-data"
                          class="flex flex-col sm:flex-row sm:items-center gap-3 pt-4 border-t border-gray-100">
                        @csrf
                        <input type="file" name="image" accept="image/jpeg,image/png,image/webp" required
                               class="block w-full text-xs text-gray-600 font-bold file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-gray-900 file:text-white hover:file:bg-gray-700 file:cursor-pointer">
                        <input type="text" name="caption" maxlength="100" placeholder="キャプション（任意）"
                               class="w-full sm:w-48 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-xs font-bold focus:ring-2 focus:ring-blue-500 focus:outline-none">
                        <button type="submit" class="shrink-0 inline-flex items-center justify-center gap-1.5 text-xs font-black text-white bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded-lg transition-colors">
                            <i data-lucide="upload" class="w-3.5 h-3.5"></i> 追加
                        </button>
                    </form>
                @else
                    <p class="text-[11px] font-bold text-gray-400 pt-4 border-t border-gray-100">写真は上限（{{ (int) config('garage.max_images') }}枚）に達しています。追加するには既存の写真を削除してください。</p>
                @endif

                {{-- ライトボックス --}}
                <div x-show="lightbox" x-cloak @keydown.escape.window="lightbox = null"
                     @click="lightbox = null"
                     class="fixed inset-0 z-[100] bg-black/90 flex items-center justify-center p-4" style="display:none;">
                    <img :src="lightbox" alt="愛車の写真" class="max-w-full max-h-full rounded-lg object-contain">
                    <button type="button" @click="lightbox = null" class="absolute top-4 right-4 w-10 h-10 bg-white/10 hover:bg-white/20 text-white rounded-full flex items-center justify-center" aria-label="閉じる">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>
            </div>

            {{-- 初回オンボーディング: ログ0件のとき「最初の給油」へ誘導（hook=燃費） --}}
            @if($myBike->fuelLogs->isEmpty() && $myBike->maintenanceLogs->isEmpty())
            <div class="bg-blue-50 border border-blue-100 rounded-2xl p-5 mb-6 flex items-start gap-3">
                <i data-lucide="fuel" class="w-6 h-6 text-blue-500 shrink-0 mt-0.5"></i>
                <div>
                    <p class="text-sm font-black text-gray-900 mb-1">最初の給油を記録してみよう</p>
                    <p class="text-xs text-gray-600 leading-relaxed">給油を記録するだけで、この{{ $myBike->bikeModel->name ?? '愛車' }}の平均燃費が見えるようになります。下の「給油を記録」から、いつもの給油を1回入力してみましょう。</p>
                </div>
            </div>
            @endif

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
                    @elseif($myBike->fuelLogs->isNotEmpty())
                    {{-- 1件は記録済み・燃費計算は2回目から（報酬予告コピー） --}}
                    <p class="text-xs text-blue-600 font-bold text-center py-8">あと<strong>もう1回</strong>給油を記録すると、平均燃費が表示されます。🏍️</p>
                    @else
                    <p class="text-xs text-gray-400 font-bold text-center py-8">満タン給油を2回記録すると、平均燃費の推移が表示されます。</p>
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
                <div class="bg-white p-1 rounded-xl inline-flex flex-wrap border border-gray-100 shadow-sm">
                    <button @click="tab = 'fuel'" :class="tab === 'fuel' ? 'bg-blue-100 text-blue-700' : 'text-gray-500 hover:text-gray-700'" class="px-5 py-2 rounded-lg text-xs font-black transition-colors flex items-center gap-2">
                        <i data-lucide="fuel" class="w-4 h-4"></i> 給油・燃費
                    </button>
                    <button @click="tab = 'maintenance'" :class="tab === 'maintenance' ? 'bg-orange-100 text-orange-700' : 'text-gray-500 hover:text-gray-700'" class="px-5 py-2 rounded-lg text-xs font-black transition-colors flex items-center gap-2">
                        <i data-lucide="wrench" class="w-4 h-4"></i> 整備・カスタム
                    </button>
                    <button @click="tab = 'ledger'; $nextTick(() => window.drawLedgerCharts && window.drawLedgerCharts())" :class="tab === 'ledger' ? 'bg-emerald-100 text-emerald-700' : 'text-gray-500 hover:text-gray-700'" class="px-5 py-2 rounded-lg text-xs font-black transition-colors flex items-center gap-2">
                        <i data-lucide="wallet" class="w-4 h-4"></i> 会計簿
                    </button>
                </div>

                {{-- 1. 給油記録セクション --}}
                <div x-show="tab === 'fuel'" class="animate-in fade-in slide-in-from-bottom-2" style="display: none;">
                    {{-- 入力フォーム --}}
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-6"
                         x-data="{
                            editId: null,
                            odoUnknown: false,
                            editFuel(b) { const d = b.dataset; this.editId = d.id; const f = this.$refs.fuelForm;
                                const set = (n, v) => { const el = f.querySelector('[name=' + n + ']'); if (el) el.value = (v ?? ''); };
                                set('filled_at', d.date); set('odometer', d.odometer); set('quantity', d.quantity); set('cost', d.cost); set('memo', d.memo);
                                this.odoUnknown = !d.odometer; {{-- 距離なし記録の編集は「距離不明」トグルON --}}
                                const chk = f.querySelector('input[type=checkbox][name=is_full_tank]'); if (chk) chk.checked = (d.full === '1');
                                f.scrollIntoView({ behavior: 'smooth', block: 'start' }); },
                            onToggleOdo(el) { if (this.odoUnknown) { const inp = el.closest('form').querySelector('[name=odometer]'); if (inp) inp.value = ''; } },
                            cancelEdit() { this.editId = null; this.odoUnknown = false; this.$refs.fuelForm.reset(); },
                         }">
                        <h3 class="font-black text-gray-900 mb-4 flex items-center gap-2"><i data-lucide="plus-circle" class="w-4 h-4 text-blue-500"></i> <span x-text="editId ? '給油記録を編集' : '給油を記録'">給油を記録</span></h3>

                        <form x-ref="fuelForm" :action="editId ? '{{ url('garage/'.$myBike->id.'/fuel') }}/' + editId : '{{ route('mybikes.fuel.store', $myBike->id) }}'" :data-edit-id="editId" method="POST" class="space-y-4"
                              data-last-odometer="{{ $myBike->current_odometer }}"
                              data-odo-multiplier="{{ config('garage.odometer_jump_multiplier', 5) }}"
                              onsubmit="return (function(f){
                                  if (f.dataset.editId) return true; {{-- 編集時は時系列をサーバ側で検証（running-max 確認はスキップ） --}}
                                  var L = parseFloat(f.dataset.lastOdometer || '0');
                                  var m = parseFloat(f.dataset.odoMultiplier || '5');
                                  var el = f.querySelector('[name=odometer]');
                                  var n = parseFloat(el && el.value || '');
                                  if (!isFinite(n) || !(L > 0)) return true;
                                  if (n < L || n > L * m) {
                                      var note = (n >= L * 9.5 && n <= L * 10.5) ? '\n（末尾に端数桁(0.1km)が混ざっていませんか？）' : '';
                                      return confirm('前回 ' + L + ' km → 今回 ' + n + ' km。' + (n < L ? '前回より小さくなっています。' : '前回より大幅に増えています。') + note + '\nこのまま記録しますか？');
                                  }
                                  return true;
                              })(this)">
                            @csrf
                            <input type="hidden" name="_method" :value="editId ? 'PUT' : 'POST'">

                            {{-- OCR入力補完: レシート/メーターを撮影→抽出→フォームに充填（★自動保存しない・確認して保存） --}}
                            @if(config('garage.ocr_enabled'))
                            <div x-data="{
                                    loading: false, msg: '', err: '', warn: '',
                                    async run(type, input) {
                                        const file = input.files[0];
                                        if (!file) return;
                                        this.msg = ''; this.err = ''; this.warn = ''; this.loading = true;
                                        try {
                                            const form = input.closest('form');
                                            const fd = new FormData();
                                            fd.append('image', file);
                                            fd.append('type', type);
                                            fd.append('_token', form.querySelector('input[name=_token]').value);
                                            const res = await fetch('{{ route('mybikes.ocr.fuel', $myBike->id) }}', { method: 'POST', body: fd, headers: { 'Accept': 'application/json' } });
                                            if (res.status === 429) { this.err = '本日の解析回数が上限に達しました。手入力で記録できます。'; return; }
                                            const data = await res.json();
                                            if (!res.ok) { this.err = (data && data.error) || '解析に失敗しました。手入力で記録できます。'; return; }
                                            const v = (data && data.values) || {};
                                            const labels = { filled_at: '給油日', odometer: '走行距離', quantity: '給油量', cost: '金額' };
                                            const filled = [];
                                            for (const k in labels) {
                                                if (v[k] !== undefined && v[k] !== null && v[k] !== '') {
                                                    const el = form.querySelector('[name=' + k + ']');
                                                    if (el) { el.value = v[k]; el.classList.add('ring-2', 'ring-blue-400'); filled.push(labels[k]); }
                                                }
                                            }
                                            // 店舗名はメモ欄へ（空のときだけ＝ユーザー入力を尊重して上書きしない）
                                            if (v.store_name) {
                                                const memo = form.querySelector('[name=memo]');
                                                if (memo && !memo.value) { memo.value = v.store_name; memo.classList.add('ring-2', 'ring-blue-400'); filled.push('店舗(メモ)'); }
                                            }
                                            if (filled.length) { this.msg = '読み取り（確度: ' + (data.confidence || '-') + '）→ ' + filled.join('・') + 'を入力しました。内容を確認して保存してください。'; }
                                            else { this.err = '読み取れませんでした。手入力で記録できます。'; }
                                            this.warn = (data && data.odometer_warning) || '';
                                        } catch (e) { this.err = '解析に失敗しました。手入力で記録できます。'; }
                                        finally { this.loading = false; input.value = ''; }
                                    }
                                }" class="mb-5 p-3 bg-blue-50/60 border border-blue-100 rounded-xl">
                                <p class="text-xs font-black text-gray-700 mb-2 flex items-center gap-1.5"><i data-lucide="sparkles" class="w-3.5 h-3.5 text-blue-500"></i> 撮影して自動入力</p>
                                <div class="flex flex-wrap gap-2">
                                    <button type="button" :disabled="loading" @click="$refs.receipt.click()" class="inline-flex items-center gap-1.5 text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-50 px-3 py-2 rounded-lg transition-colors">
                                        <i data-lucide="receipt" class="w-3.5 h-3.5"></i> レシートを撮る
                                    </button>
                                    <button type="button" :disabled="loading" @click="$refs.odometer.click()" class="inline-flex items-center gap-1.5 text-xs font-bold text-white bg-gray-800 hover:bg-gray-900 disabled:opacity-50 px-3 py-2 rounded-lg transition-colors">
                                        <i data-lucide="gauge" class="w-3.5 h-3.5"></i> メーターを撮る
                                    </button>
                                    <span x-show="loading" x-cloak class="inline-flex items-center gap-1.5 text-xs font-bold text-blue-600"><i data-lucide="loader" class="w-3.5 h-3.5 animate-spin"></i> 解析中…</span>
                                </div>
                                {{-- name無し＝給油フォーム送信には含まれない（JSからのみ読む） --}}
                                <input type="file" x-ref="receipt" accept="image/*" capture="environment" class="hidden" @change="run('receipt', $event.target)">
                                <input type="file" x-ref="odometer" accept="image/*" capture="environment" class="hidden" @change="run('odometer', $event.target)">
                                <p x-show="msg" x-cloak class="text-[11px] font-bold text-blue-700 mt-2" x-text="msg"></p>
                                <p x-show="warn" x-cloak class="text-[11px] font-bold text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-2 py-1.5 mt-2 flex items-start gap-1.5"><i data-lucide="alert-triangle" class="w-3.5 h-3.5 shrink-0 mt-0.5"></i><span x-text="warn"></span></p>
                                <p x-show="err" x-cloak class="text-[11px] font-bold text-red-600 mt-2" x-text="err"></p>
                                <p class="text-[10px] text-gray-400 mt-2">撮影画像はAI解析のため送信されます（位置情報は除去）。読み取り結果は自動保存されません。</p>
                            </div>
                            @endif

                            {{-- 音声入力補完: 喋る→文字化(ブラウザ)→Haikuパース→充填（★自動保存しない・確認して保存） --}}
                            @if(config('garage.voice_enabled'))
                            <div x-data="{
                                    supported: !!(window.SpeechRecognition || window.webkitSpeechRecognition),
                                    rec: null, recording: false, loading: false, msg: '', err: '', warn: '', interim: '', finalText: '',
                                    init() {
                                        if (!this.supported) return;
                                        const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
                                        this.rec = new SR();
                                        this.rec.lang = 'ja-JP';
                                        this.rec.interimResults = true;
                                        this.rec.continuous = false;
                                        this.rec.onresult = (e) => {
                                            let t = '';
                                            for (let i = 0; i < e.results.length; i++) { t += e.results[i][0].transcript; }
                                            this.interim = t;
                                            if (e.results[e.results.length - 1].isFinal) { this.finalText = t; }
                                        };
                                        this.rec.onerror = () => { this.err = '音声を認識できませんでした。手入力で記録できます。'; this.recording = false; };
                                        this.rec.onend = () => {
                                            this.recording = false;
                                            const t = (this.finalText || this.interim || '').trim();
                                            if (t) this.submit(t);
                                        };
                                    },
                                    toggle() {
                                        if (!this.rec) return;
                                        if (this.recording) { this.rec.stop(); return; }
                                        this.msg = ''; this.err = ''; this.interim = ''; this.finalText = '';
                                        try { this.rec.start(); this.recording = true; } catch (e) { /* already started */ }
                                    },
                                    async submit(transcript) {
                                        this.loading = true; this.warn = '';
                                        try {
                                            const form = $el.closest('form');
                                            const fd = new FormData();
                                            fd.append('transcript', transcript);
                                            fd.append('_token', form.querySelector('input[name=_token]').value);
                                            const res = await fetch('{{ route('mybikes.voice.fuel', $myBike->id) }}', { method: 'POST', body: fd, headers: { 'Accept': 'application/json' } });
                                            if (res.status === 429) { this.err = '本日の音声入力の上限に達しました。手入力で記録できます。'; return; }
                                            const data = await res.json();
                                            if (!res.ok) { this.err = (data && data.error) || '解析に失敗しました。手入力で記録できます。'; return; }
                                            const v = (data && data.values) || {};
                                            const labels = { odometer: '走行距離', quantity: '給油量', cost: '金額' };
                                            const filled = [];
                                            for (const k in labels) {
                                                if (v[k] !== undefined && v[k] !== null && v[k] !== '') {
                                                    const el = form.querySelector('[name=' + k + ']');
                                                    if (el) { el.value = v[k]; el.classList.add('ring-2', 'ring-blue-400'); filled.push(labels[k]); }
                                                }
                                            }
                                            if (filled.length) { this.msg = '「' + transcript + '」→ ' + filled.join('・') + 'を入力しました。内容を確認して保存してください。'; }
                                            else { this.err = '「' + transcript + '」から数値を読み取れませんでした。手入力で記録できます。'; }
                                            this.warn = (data && data.odometer_warning) || '';
                                        } catch (e) { this.err = '解析に失敗しました。手入力で記録できます。'; }
                                        finally { this.loading = false; }
                                    }
                                }" x-show="supported" x-cloak class="mb-5 p-3 bg-indigo-50/60 border border-indigo-100 rounded-xl">
                                <p class="text-xs font-black text-gray-700 mb-2 flex items-center gap-1.5"><i data-lucide="mic" class="w-3.5 h-3.5 text-indigo-500"></i> 音声で入力</p>
                                <div class="flex flex-wrap items-center gap-2">
                                    <button type="button" :disabled="loading" @click="toggle()"
                                        :class="recording ? 'bg-red-600 hover:bg-red-700' : 'bg-indigo-600 hover:bg-indigo-700'"
                                        class="inline-flex items-center gap-1.5 text-xs font-bold text-white disabled:opacity-50 px-3 py-2 rounded-lg transition-colors">
                                        <i data-lucide="mic" class="w-3.5 h-3.5"></i>
                                        <span x-text="recording ? '停止' : '音声で記録'"></span>
                                    </button>
                                    <span x-show="recording" x-cloak class="inline-flex items-center gap-1.5 text-xs font-bold text-red-600">
                                        <span class="w-2 h-2 rounded-full bg-red-500 animate-pulse"></span> 聞き取り中…
                                    </span>
                                    <span x-show="loading" x-cloak class="inline-flex items-center gap-1.5 text-xs font-bold text-indigo-600"><i data-lucide="loader" class="w-3.5 h-3.5 animate-spin"></i> 解析中…</span>
                                </div>
                                <p x-show="recording && interim" x-cloak class="text-[11px] text-gray-500 mt-2" x-text="interim"></p>
                                <p x-show="msg" x-cloak class="text-[11px] font-bold text-indigo-700 mt-2" x-text="msg"></p>
                                <p x-show="warn" x-cloak class="text-[11px] font-bold text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-2 py-1.5 mt-2 flex items-start gap-1.5"><i data-lucide="alert-triangle" class="w-3.5 h-3.5 shrink-0 mt-0.5"></i><span x-text="warn"></span></p>
                                <p x-show="err" x-cloak class="text-[11px] font-bold text-red-600 mt-2" x-text="err"></p>
                                <p class="text-[10px] text-gray-400 mt-2">「走行距離・給油量・金額」を読み上げてください（例: 6万キロ 10リットル 1500円）。音声はお使いのブラウザの音声認識（例: Google）で文字化され、その文字をAI解析に送信します（音声自体は当サイトに送信されません）。結果は自動保存されません。</p>
                            </div>
                            @endif

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-gray-400 mb-1 ml-1">給油日</label>
                                    {{-- h-12を削除し、py-3に変更してテキストを垂直中央に配置 --}}
                                    <input type="date" name="filled_at" value="{{ old('filled_at', date('Y-m-d')) }}" required 
                                        class="appearance-none block w-full py-3 bg-gray-50 border border-gray-200 rounded-xl px-4 text-sm font-bold focus:border-blue-500 focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50 outline-none transition">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-400 mb-1 ml-1">総走行距離 (km)</label>
                                    {{-- ソフト必須：基本は入力。距離不明トグルON時のみ無効化＋空送信（hidden で odometer_unknown=1） --}}
                                    <input type="hidden" name="odometer_unknown" :value="odoUnknown ? '1' : '0'">
                                    <input type="number" step="0.1" name="odometer" value="{{ old('odometer') }}" placeholder="例: {{ $myBike->current_odometer }}"
                                        :required="!odoUnknown" :disabled="odoUnknown"
                                        :class="odoUnknown ? 'opacity-40 cursor-not-allowed' : ''"
                                        class="appearance-none block w-full h-12 bg-gray-50 border border-gray-200 rounded-xl px-4 text-sm font-bold focus:border-blue-500 focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50 outline-none transition">
                                    <label class="flex items-center gap-1.5 mt-1.5 ml-1 cursor-pointer select-none">
                                        <input type="checkbox" x-model="odoUnknown" @change="onToggleOdo($el)" class="w-3.5 h-3.5 rounded text-blue-600 focus:ring-blue-500 border-gray-300">
                                        <span class="text-[10px] font-bold text-gray-400">距離が分からない（後で編集で入力できます）</span>
                                    </label>
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
                                <div class="w-full sm:w-auto flex items-center gap-3">
                                    <button type="submit" class="flex-1 sm:flex-none bg-blue-600 hover:bg-blue-500 text-white font-black px-8 py-3 rounded-xl shadow-lg transition transform active:scale-95 text-center"><span x-text="editId ? '更新する' : '記録する'">記録する</span></button>
                                    <button type="button" x-show="editId" @click="cancelEdit()" class="shrink-0 text-xs font-bold text-gray-500 hover:text-gray-700 px-4 py-3 rounded-xl border border-gray-200 hover:bg-gray-50 transition-colors">編集をやめる</button>
                                </div>
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
                                    @if($log->odometer !== null)
                                        <div class="text-sm font-black text-gray-800">{{ number_format($log->odometer) }} km</div>
                                    @else
                                        <div class="inline-flex items-center gap-1 text-[10px] font-bold text-amber-600 bg-amber-50 border border-amber-200 rounded px-1.5 py-0.5"><i data-lucide="help-circle" class="w-3 h-3"></i>距離未入力</div>
                                    @endif
                                </div>
                                <div class="flex items-center gap-3">
                                    <div class="text-right">
                                        <div class="text-lg font-black text-blue-600">
                                            {{ $log->efficiency ? $log->efficiency . ' km/L' : '-' }}
                                        </div>
                                        <div class="text--[10px] font-bold text-gray-400">
                                            {{ $log->quantity }}L / {{ number_format($log->cost) }}円
                                        </div>
                                    </div>
                                    {{-- 編集（owner-only・更新後に燃費/総走行距離を再計算） --}}
                                    <button type="button" @click="editFuel($el)" data-id="{{ $log->id }}" data-date="{{ $log->filled_at->format('Y-m-d') }}" data-odometer="{{ $log->odometer }}" data-quantity="{{ $log->quantity }}" data-cost="{{ $log->cost }}" data-memo="{{ $log->memo }}" data-full="{{ $log->is_full_tank ? '1' : '0' }}" class="w-7 h-7 text-gray-300 hover:text-blue-600 hover:bg-blue-50 rounded-lg flex items-center justify-center transition-colors" aria-label="給油記録を編集" title="編集"><i data-lucide="pencil" class="w-4 h-4"></i></button>
                                    {{-- 削除（owner-only・ハード削除・燃費/総走行距離を再計算） --}}
                                    <form action="{{ route('mybikes.fuel.destroy', [$myBike->id, $log->id]) }}" method="POST"
                                          onsubmit="return confirm('この給油記録を削除しますか？\n燃費と総走行距離が再計算されます。');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="w-7 h-7 text-gray-300 hover:text-red-600 hover:bg-red-50 rounded-lg flex items-center justify-center transition-colors" aria-label="給油記録を削除">
                                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                                        </button>
                                    </form>
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

                {{-- 2. 整備・カスタム記録セクション --}}
                @php
                    $remByTitle = collect($dashboard['reminders'] ?? [])->keyBy('title');
                    // 走行距離の時系列ガード（クライアント即時フィードバック用）。給油+整備+カスタムの {date, odo}。
                    $odoRecords = collect()
                        ->concat($myBike->fuelLogs->map(fn ($l) => ['date' => optional($l->filled_at)->format('Y-m-d'), 'odo' => $l->odometer !== null ? (float) $l->odometer : null]))
                        ->concat($myBike->maintenanceLogs->map(fn ($l) => ['date' => optional($l->maintained_at)->format('Y-m-d'), 'odo' => $l->odometer !== null ? (float) $l->odometer : null]))
                        ->concat($myBike->customRecords->map(fn ($l) => ['date' => optional($l->maintained_at)->format('Y-m-d'), 'odo' => $l->odometer !== null ? (float) $l->odometer : null]))
                        ->filter(fn ($r) => $r['date'] !== null && $r['odo'] !== null)
                        ->values();
                @endphp
                <div x-show="tab === 'maintenance'" style="display: none;" class="animate-in fade-in slide-in-from-bottom-2"
                     x-data="{
                        mode: 'maintenance', title: '', category: '',
                        L: {{ (float) $myBike->current_odometer }}, mult: {{ (float) config('garage.odometer_jump_multiplier', 5) }}, odoWarn: '',
                        records: @js($odoRecords),
                        {{-- 入力日の時系列文脈でのみ警告（過去日付に小さい値は正常＝警告しない）。サーバ側 odometerPlausibilityWarning と同じ判定。 --}}
                        checkOdo(v) {
                            const n = parseFloat(v);
                            if (!isFinite(n)) { this.odoWarn = ''; return; }
                            const date = document.getElementById(this.mode === 'custom' ? 'c_maintained_at' : 'm_maintained_at')?.value || '';
                            if (!date) { this.odoWarn = ''; return; }
                            let maxBefore = null, minAfter = null;
                            for (const r of this.records) {
                                if (r.date < date) { if (maxBefore === null || r.odo > maxBefore) maxBefore = r.odo; }
                                else if (r.date > date) { if (minAfter === null || r.odo < minAfter) minAfter = r.odo; }
                            }
                            if (minAfter !== null && n > minAfter) { this.odoWarn = '入力日より後の記録（' + minAfter + ' km）より大きい値です。確認してください。'; return; }
                            if (maxBefore !== null && n < maxBefore) { this.odoWarn = '入力日より前の記録（' + maxBefore + ' km）より小さい値です。確認してください。'; return; }
                            if (maxBefore !== null && maxBefore > 0 && n > maxBefore * this.mult) {
                                const note = (n >= maxBefore * 9.5 && n <= maxBefore * 10.5) ? '（末尾に端数桁(0.1km)が混ざっていませんか？）' : '';
                                this.odoWarn = '前回 ' + maxBefore + ' km → 今回 ' + n + ' km。大きく増えています。' + note; return;
                            }
                            this.odoWarn = '';
                        },
                        pickPreset(name) { this.title = (name === 'その他') ? '' : name; if (name === 'その他') this.$nextTick(() => document.getElementById('m_title')?.focus()); },
                        setV(id, val) { const el = document.getElementById(id); if (el) el.value = (val ?? ''); },
                        scrollTop() { this.$refs.formTop?.scrollIntoView({ behavior: 'smooth', block: 'start' }); },
                        copyMaint(b) { const d = b.dataset; this.mode = 'maintenance'; this.editId = null; this.title = d.title;
                            this.setV('m_title', d.title); this.setV('m_cost', d.cost); this.setV('m_vendor', d.vendor); this.setV('m_note', d.note);
                            this.setV('m_odometer', this.L); this.setV('m_maintained_at', '{{ date('Y-m-d') }}'); this.checkOdo(this.L); this.scrollTop(); },
                        copyCustom(b) { const d = b.dataset; this.mode = 'custom'; this.editId = null; this.category = d.category || '';
                            this.setV('c_part', d.part); this.setV('c_brand', d.brand); this.setV('c_cost', d.cost); this.setV('c_vendor', d.vendor); this.setV('c_note', d.note);
                            this.setV('c_odometer', this.L); this.setV('c_maintained_at', '{{ date('Y-m-d') }}'); this.checkOdo(this.L); this.scrollTop(); },
                        {{-- 編集: 既存記録をフォームに prefill し、PUT で更新（editId が action/_method を切替） --}}
                        editId: null,
                        editMaint(b) { const d = b.dataset; this.mode = 'maintenance'; this.editId = d.id; this.title = d.title;
                            this.setV('m_title', d.title); this.setV('m_cost', d.cost); this.setV('m_vendor', d.vendor); this.setV('m_note', d.note);
                            this.setV('m_odometer', d.odometer); this.setV('m_maintained_at', d.date); this.checkOdo(d.odometer); this.scrollTop(); },
                        editCustom(b) { const d = b.dataset; this.mode = 'custom'; this.editId = d.id; this.category = d.category || '';
                            this.setV('c_part', d.part); this.setV('c_brand', d.brand); this.setV('c_cost', d.cost); this.setV('c_vendor', d.vendor); this.setV('c_note', d.note);
                            this.setV('c_odometer', d.odometer); this.setV('c_maintained_at', d.date);
                            const ci = document.getElementById('c_installed'); if (ci) ci.checked = (d.installed === '1');
                            this.checkOdo(d.odometer); this.scrollTop(); },
                        cancelEdit() { this.editId = null; },
                        {{-- OCR/voice 入力補完（給油と同じ流儀・type別） --}}
                        aiLoading: false, aiMsg: '', aiErr: '',
                        voiceOn: false, voiceRec: null,
                        voiceSupported: !!(window.SpeechRecognition || window.webkitSpeechRecognition),
                        recordLabels(type) {
                            return type === 'custom'
                                ? { maintained_at: '日付', odometer: '走行距離', cost: '費用', vendor: '場所', part_name: 'パーツ名', brand: 'ブランド' }
                                : { maintained_at: '日付', odometer: '走行距離', cost: '費用', vendor: '場所', title: '内容' };
                        },
                        fillForm(form, data) {
                            const v = (data && data.values) || {}; const labels = this.recordLabels(this.mode); const filled = [];
                            for (const k in labels) {
                                if (v[k] !== undefined && v[k] !== null && v[k] !== '') {
                                    const el = form.querySelector('[name=' + k + ']');
                                    if (el) { el.value = v[k]; el.dispatchEvent(new Event('input', { bubbles: true })); el.classList.add('ring-2', 'ring-orange-400'); filled.push(labels[k]); }
                                }
                            }
                            if (data && data.odometer_warning) this.odoWarn = data.odometer_warning;
                            return filled;
                        },
                        async runOcr(type, input) {
                            const file = input.files[0]; if (!file) return;
                            this.aiMsg = ''; this.aiErr = ''; this.aiLoading = true;
                            try {
                                const form = input.closest('form'); const fd = new FormData();
                                fd.append('image', file); fd.append('type', type); fd.append('_token', form.querySelector('input[name=_token]').value);
                                const res = await fetch('{{ route('mybikes.ocr.record', $myBike->id) }}', { method: 'POST', body: fd, headers: { 'Accept': 'application/json' } });
                                if (res.status === 429) { this.aiErr = '本日の解析回数が上限に達しました。手入力で記録できます。'; return; }
                                const data = await res.json();
                                if (!res.ok) { this.aiErr = (data && data.error) || '解析に失敗しました。手入力で記録できます。'; return; }
                                const filled = this.fillForm(form, data);
                                if (filled.length) { this.aiMsg = '読み取り→ ' + filled.join('・') + 'を入力しました。確認して保存してください。'; }
                                else { this.aiErr = '読み取れませんでした。手入力で記録できます。'; }
                            } catch (e) { this.aiErr = '解析に失敗しました。手入力で記録できます。'; }
                            finally { this.aiLoading = false; input.value = ''; }
                        },
                        startVoice(type) {
                            if (this.voiceOn) { this.voiceRec?.stop(); return; }
                            const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
                            if (!SR) { this.aiErr = 'このブラウザは音声入力に対応していません。'; return; }
                            this.aiMsg = ''; this.aiErr = '';
                            this.voiceRec = new SR(); this.voiceRec.lang = 'ja-JP'; this.voiceRec.interimResults = false; this.voiceRec.continuous = false;
                            let finalText = '';
                            this.voiceRec.onresult = (e) => { for (let i = 0; i < e.results.length; i++) { if (e.results[i].isFinal) finalText += e.results[i][0].transcript; } };
                            this.voiceRec.onerror = () => { this.aiErr = '音声を認識できませんでした。手入力で記録できます。'; this.voiceOn = false; };
                            this.voiceRec.onend = () => { this.voiceOn = false; const t = finalText.trim(); if (t) this.submitVoice(type, t); };
                            try { this.voiceRec.start(); this.voiceOn = true; } catch (e) { /* already started */ }
                        },
                        async submitVoice(type, transcript) {
                            this.aiLoading = true;
                            try {
                                const form = document.getElementById(type === 'custom' ? 'c_form' : 'm_form'); const fd = new FormData();
                                fd.append('transcript', transcript); fd.append('type', type); fd.append('_token', form.querySelector('input[name=_token]').value);
                                const res = await fetch('{{ route('mybikes.voice.record', $myBike->id) }}', { method: 'POST', body: fd, headers: { 'Accept': 'application/json' } });
                                if (res.status === 429) { this.aiErr = '本日の音声入力が上限に達しました。手入力で記録できます。'; return; }
                                const data = await res.json();
                                if (!res.ok) { this.aiErr = (data && data.error) || '解析に失敗しました。手入力で記録できます。'; return; }
                                const filled = this.fillForm(form, data);
                                if (filled.length) { this.aiMsg = '「' + transcript + '」→ ' + filled.join('・') + 'を入力しました。確認して保存してください。'; }
                                else { this.aiErr = '「' + transcript + '」から読み取れませんでした。手入力で記録できます。'; }
                            } catch (e) { this.aiErr = '解析に失敗しました。手入力で記録できます。'; }
                            finally { this.aiLoading = false; }
                        }
                     }">
                    <div x-ref="formTop"></div>

                    {{-- 整備 / カスタム トグル --}}
                    <div class="bg-white p-1 rounded-xl inline-flex border border-gray-100 shadow-sm mb-4">
                        <button type="button" @click="mode = 'maintenance'; odoWarn=''; editId=null" :class="mode === 'maintenance' ? 'bg-orange-100 text-orange-700' : 'text-gray-500'" class="px-5 py-2 rounded-lg text-xs font-black transition-colors flex items-center gap-1.5"><i data-lucide="wrench" class="w-3.5 h-3.5"></i> 整備</button>
                        <button type="button" @click="mode = 'custom'; odoWarn=''; editId=null" :class="mode === 'custom' ? 'bg-purple-100 text-purple-700' : 'text-gray-500'" class="px-5 py-2 rounded-lg text-xs font-black transition-colors flex items-center gap-1.5"><i data-lucide="sparkles" class="w-3.5 h-3.5"></i> カスタム</button>
                    </div>

                    {{-- 整備フォーム --}}
                    <div x-show="mode === 'maintenance'" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-6">
                        <h3 class="font-black text-gray-900 mb-3 flex items-center gap-2"><i data-lucide="plus-circle" class="w-4 h-4 text-orange-500"></i> <span x-text="editId ? '整備記録を編集' : '整備を記録'">整備を記録</span></h3>
                        {{-- プリセット（タップ選択） --}}
                        <div class="flex flex-wrap gap-1.5 mb-4">
                            @foreach(config('garage.maintenance_presets', []) as $preset)
                            <button type="button" @click="pickPreset(@js($preset))" :class="title === @js($preset) ? 'bg-orange-600 text-white border-orange-600' : 'bg-gray-50 text-gray-600 border-gray-200 hover:border-orange-300'" class="text-[11px] font-bold px-2.5 py-1 rounded-lg border transition-colors">{{ $preset }}</button>
                            @endforeach
                        </div>
                        <form id="m_form" :action="editId ? '{{ url('garage/'.$myBike->id.'/maintenance') }}/' + editId : '{{ route('mybikes.maintenance.store', $myBike->id) }}'" method="POST" enctype="multipart/form-data" class="space-y-4">
                            @csrf
                            <input type="hidden" name="_method" :value="editId ? 'PUT' : 'POST'">
                            @if(config('garage.ocr_enabled') || config('garage.voice_enabled'))
                            <div class="p-3 bg-orange-50/60 border border-orange-100 rounded-xl">
                                <p class="text-xs font-black text-gray-700 mb-2 flex items-center gap-1.5"><i data-lucide="sparkles" class="w-3.5 h-3.5 text-orange-500"></i> 撮影・音声で自動入力</p>
                                <div class="flex flex-wrap items-center gap-2">
                                    @if(config('garage.ocr_enabled'))
                                    <button type="button" :disabled="aiLoading" @click="$refs.m_receipt.click()" class="inline-flex items-center gap-1.5 text-xs font-bold text-white bg-orange-600 hover:bg-orange-700 disabled:opacity-50 px-3 py-2 rounded-lg transition-colors"><i data-lucide="receipt" class="w-3.5 h-3.5"></i> 伝票を撮る</button>
                                    @endif
                                    @if(config('garage.voice_enabled'))
                                    <button type="button" x-show="voiceSupported" :disabled="aiLoading" @click="startVoice('maintenance')" :class="voiceOn ? 'bg-red-600 hover:bg-red-700' : 'bg-gray-800 hover:bg-gray-900'" class="inline-flex items-center gap-1.5 text-xs font-bold text-white disabled:opacity-50 px-3 py-2 rounded-lg transition-colors"><i data-lucide="mic" class="w-3.5 h-3.5"></i><span x-text="voiceOn ? '停止' : '音声で入力'"></span></button>
                                    @endif
                                    <span x-show="aiLoading" x-cloak class="inline-flex items-center gap-1.5 text-xs font-bold text-orange-600"><i data-lucide="loader" class="w-3.5 h-3.5 animate-spin"></i> 解析中…</span>
                                </div>
                                <input type="file" x-ref="m_receipt" accept="image/*" capture="environment" class="hidden" @change="runOcr('maintenance', $event.target)">
                                <p x-show="aiMsg" x-cloak class="text-[11px] font-bold text-orange-700 mt-2" x-text="aiMsg"></p>
                                <p x-show="aiErr" x-cloak class="text-[11px] font-bold text-red-600 mt-2" x-text="aiErr"></p>
                                <p class="text-[10px] text-gray-400 mt-2">画像/音声はAI解析に送信されます（位置情報除去・保存しません）。結果は自動保存されません。</p>
                            </div>
                            @endif
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-gray-400 mb-1 ml-1">内容</label>
                                    <input type="text" id="m_title" name="title" x-model="title" value="{{ old('title') }}" placeholder="プリセット選択 or 自由入力" required
                                        class="appearance-none block w-full h-12 bg-gray-50 border border-gray-200 rounded-xl px-4 text-sm font-bold focus:border-orange-500 focus:ring-2 focus:ring-orange-500 focus:ring-opacity-50 outline-none transition">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-400 mb-1 ml-1">整備日</label>
                                    <input type="date" id="m_maintained_at" name="maintained_at" value="{{ old('maintained_at', date('Y-m-d')) }}" required @change="checkOdo(document.getElementById('m_odometer')?.value)"
                                        class="appearance-none block w-full h-12 bg-gray-50 border border-gray-200 rounded-xl px-4 text-sm font-bold focus:border-orange-500 focus:ring-2 focus:ring-orange-500 focus:ring-opacity-50 outline-none transition">
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-gray-400 mb-1 ml-1">時走行距離 (km) <span class="font-normal text-gray-300">任意</span></label>
                                    <input type="number" step="0.1" id="m_odometer" name="odometer" value="{{ old('odometer', $myBike->current_odometer) }}" @input="checkOdo($event.target.value)"
                                        class="appearance-none block w-full h-12 bg-gray-50 border border-gray-200 rounded-xl px-4 text-sm font-bold focus:border-orange-500 focus:ring-2 focus:ring-orange-500 focus:ring-opacity-50 outline-none transition">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-400 mb-1 ml-1">費用 (円) <span class="font-normal text-gray-300">任意</span></label>
                                    <input type="number" id="m_cost" name="cost" value="{{ old('cost') }}" placeholder="例: 3000"
                                        class="appearance-none block w-full h-12 bg-gray-50 border border-gray-200 rounded-xl px-4 text-sm font-bold focus:border-orange-500 focus:ring-2 focus:ring-orange-500 focus:ring-opacity-50 outline-none transition">
                                </div>
                            </div>
                            <p x-show="odoWarn" x-cloak class="text-[11px] font-bold text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-2 py-1.5 flex items-start gap-1.5"><i data-lucide="alert-triangle" class="w-3.5 h-3.5 shrink-0 mt-0.5"></i><span x-text="odoWarn"></span></p>
                            <div>
                                <label class="block text-xs font-bold text-gray-400 mb-1 ml-1">場所 <span class="font-normal text-gray-300">任意（店名 or DIY）</span></label>
                                <input type="text" id="m_vendor" name="vendor" value="{{ old('vendor') }}" placeholder="例: ナップス / DIY"
                                    class="appearance-none block w-full h-12 bg-gray-50 border border-gray-200 rounded-xl px-4 text-sm font-bold focus:border-orange-500 focus:ring-2 focus:ring-orange-500 focus:ring-opacity-50 outline-none transition">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-400 mb-1 ml-1">詳細メモ <span class="font-normal text-gray-400">任意</span></label>
                                <textarea id="m_note" name="note" placeholder="使用オイル: ホンダG2 10W-40" rows="2"
                                    class="appearance-none block w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm font-bold focus:border-orange-500 focus:ring-2 focus:ring-orange-500 focus:ring-opacity-50 outline-none transition">{{ old('note') }}</textarea>
                            </div>
                            @if(config('garage.record_photos_enabled'))
                            <div>
                                <label class="block text-xs font-bold text-gray-400 mb-1 ml-1">写真 <span class="font-normal text-gray-300">任意・複数（JPEG/PNG/WebP）</span></label>
                                <input type="file" name="images[]" multiple accept="image/jpeg,image/png,image/webp"
                                    class="block w-full text-xs text-gray-600 font-bold file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-gray-800 file:text-white hover:file:bg-gray-700 file:cursor-pointer">
                            </div>
                            @endif
                            @if ($errors->any() && $errors->has('maintained_at'))
                                <div class="p-3 bg-red-50 text-red-600 text-xs font-bold rounded-xl border border-red-100"><ul class="list-disc list-inside">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
                            @endif
                            <div class="flex items-center gap-3">
                                <button type="submit" class="flex-1 bg-orange-600 hover:bg-orange-500 text-white font-black py-3 rounded-xl shadow-lg transition transform active:scale-95"><span x-text="editId ? '更新する' : '記録する'">記録する</span></button>
                                <button type="button" x-show="editId" @click="cancelEdit()" class="shrink-0 text-xs font-bold text-gray-500 hover:text-gray-700 px-4 py-3 rounded-xl border border-gray-200 hover:bg-gray-50 transition-colors">編集をやめる</button>
                            </div>
                        </form>
                    </div>

                    {{-- カスタムフォーム --}}
                    <div x-show="mode === 'custom'" x-cloak class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-6">
                        <h3 class="font-black text-gray-900 mb-3 flex items-center gap-2"><i data-lucide="plus-circle" class="w-4 h-4 text-purple-500"></i> <span x-text="editId ? 'カスタム記録を編集' : 'カスタムを記録'">カスタムを記録</span></h3>
                        <form id="c_form" :action="editId ? '{{ url('garage/'.$myBike->id.'/custom') }}/' + editId : '{{ route('mybikes.custom.store', $myBike->id) }}'" method="POST" enctype="multipart/form-data" class="space-y-4">
                            @csrf
                            <input type="hidden" name="_method" :value="editId ? 'PUT' : 'POST'">
                            @if(config('garage.ocr_enabled') || config('garage.voice_enabled'))
                            <div class="p-3 bg-purple-50/60 border border-purple-100 rounded-xl">
                                <p class="text-xs font-black text-gray-700 mb-2 flex items-center gap-1.5"><i data-lucide="sparkles" class="w-3.5 h-3.5 text-purple-500"></i> 撮影・音声で自動入力</p>
                                <div class="flex flex-wrap items-center gap-2">
                                    @if(config('garage.ocr_enabled'))
                                    <button type="button" :disabled="aiLoading" @click="$refs.c_receipt.click()" class="inline-flex items-center gap-1.5 text-xs font-bold text-white bg-purple-600 hover:bg-purple-700 disabled:opacity-50 px-3 py-2 rounded-lg transition-colors"><i data-lucide="receipt" class="w-3.5 h-3.5"></i> レシートを撮る</button>
                                    @endif
                                    @if(config('garage.voice_enabled'))
                                    <button type="button" x-show="voiceSupported" :disabled="aiLoading" @click="startVoice('custom')" :class="voiceOn ? 'bg-red-600 hover:bg-red-700' : 'bg-gray-800 hover:bg-gray-900'" class="inline-flex items-center gap-1.5 text-xs font-bold text-white disabled:opacity-50 px-3 py-2 rounded-lg transition-colors"><i data-lucide="mic" class="w-3.5 h-3.5"></i><span x-text="voiceOn ? '停止' : '音声で入力'"></span></button>
                                    @endif
                                    <span x-show="aiLoading" x-cloak class="inline-flex items-center gap-1.5 text-xs font-bold text-purple-600"><i data-lucide="loader" class="w-3.5 h-3.5 animate-spin"></i> 解析中…</span>
                                </div>
                                <input type="file" x-ref="c_receipt" accept="image/*" capture="environment" class="hidden" @change="runOcr('custom', $event.target)">
                                <p x-show="aiMsg" x-cloak class="text-[11px] font-bold text-purple-700 mt-2" x-text="aiMsg"></p>
                                <p x-show="aiErr" x-cloak class="text-[11px] font-bold text-red-600 mt-2" x-text="aiErr"></p>
                                <p class="text-[10px] text-gray-400 mt-2">画像/音声はAI解析に送信されます（位置情報除去・保存しません）。結果は自動保存されません。</p>
                            </div>
                            @endif
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-gray-400 mb-1 ml-1">パーツ名</label>
                                    <input type="text" id="c_part" name="part_name" value="{{ old('part_name') }}" placeholder="例: ヨシムラ マフラー" required
                                        class="appearance-none block w-full h-12 bg-gray-50 border border-gray-200 rounded-xl px-4 text-sm font-bold focus:border-purple-500 focus:ring-2 focus:ring-purple-500 focus:ring-opacity-50 outline-none transition">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-400 mb-1 ml-1">ブランド <span class="font-normal text-gray-300">任意</span></label>
                                    <input type="text" id="c_brand" name="brand" value="{{ old('brand') }}" placeholder="例: YOSHIMURA"
                                        class="appearance-none block w-full h-12 bg-gray-50 border border-gray-200 rounded-xl px-4 text-sm font-bold focus:border-purple-500 focus:ring-2 focus:ring-purple-500 focus:ring-opacity-50 outline-none transition">
                                </div>
                            </div>
                            {{-- カテゴリ（タップ選択） --}}
                            <div>
                                <label class="block text-xs font-bold text-gray-400 mb-1 ml-1">カテゴリ <span class="font-normal text-gray-300">任意</span></label>
                                <input type="hidden" name="category" x-model="category">
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach(config('garage.custom_categories', []) as $cat)
                                    <button type="button" @click="category = (category === @js($cat) ? '' : @js($cat))" :class="category === @js($cat) ? 'bg-purple-600 text-white border-purple-600' : 'bg-gray-50 text-gray-600 border-gray-200 hover:border-purple-300'" class="text-[11px] font-bold px-2.5 py-1 rounded-lg border transition-colors">{{ $cat }}</button>
                                    @endforeach
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-gray-400 mb-1 ml-1">装着日</label>
                                    <input type="date" id="c_maintained_at" name="maintained_at" value="{{ old('maintained_at', date('Y-m-d')) }}" required @change="checkOdo(document.getElementById('c_odometer')?.value)"
                                        class="appearance-none block w-full h-12 bg-gray-50 border border-gray-200 rounded-xl px-4 text-sm font-bold focus:border-purple-500 focus:ring-2 focus:ring-purple-500 focus:ring-opacity-50 outline-none transition">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-400 mb-1 ml-1">時走行距離 (km) <span class="font-normal text-gray-300">任意</span></label>
                                    <input type="number" step="0.1" id="c_odometer" name="odometer" value="{{ old('odometer', $myBike->current_odometer) }}" @input="checkOdo($event.target.value)"
                                        class="appearance-none block w-full h-12 bg-gray-50 border border-gray-200 rounded-xl px-4 text-sm font-bold focus:border-purple-500 focus:ring-2 focus:ring-purple-500 focus:ring-opacity-50 outline-none transition">
                                </div>
                            </div>
                            <p x-show="odoWarn" x-cloak class="text-[11px] font-bold text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-2 py-1.5 flex items-start gap-1.5"><i data-lucide="alert-triangle" class="w-3.5 h-3.5 shrink-0 mt-0.5"></i><span x-text="odoWarn"></span></p>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-gray-400 mb-1 ml-1">費用 (円) <span class="font-normal text-gray-300">任意</span></label>
                                    <input type="number" id="c_cost" name="cost" value="{{ old('cost') }}" placeholder="例: 80000"
                                        class="appearance-none block w-full h-12 bg-gray-50 border border-gray-200 rounded-xl px-4 text-sm font-bold focus:border-purple-500 focus:ring-2 focus:ring-purple-500 focus:ring-opacity-50 outline-none transition">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-400 mb-1 ml-1">場所 <span class="font-normal text-gray-300">任意</span></label>
                                    <input type="text" id="c_vendor" name="vendor" value="{{ old('vendor') }}" placeholder="例: 2りんかん / DIY"
                                        class="appearance-none block w-full h-12 bg-gray-50 border border-gray-200 rounded-xl px-4 text-sm font-bold focus:border-purple-500 focus:ring-2 focus:ring-purple-500 focus:ring-opacity-50 outline-none transition">
                                </div>
                            </div>
                            <label class="flex items-center gap-2 cursor-pointer select-none">
                                <input type="hidden" name="is_installed" value="0">
                                <input type="checkbox" id="c_installed" name="is_installed" value="1" checked class="w-5 h-5 rounded text-purple-600 focus:ring-purple-500 border-gray-300">
                                <span class="text-xs font-bold text-gray-600">現在も装着中</span>
                            </label>
                            <div>
                                <label class="block text-xs font-bold text-gray-400 mb-1 ml-1">メモ <span class="font-normal text-gray-400">任意</span></label>
                                <textarea id="c_note" name="note" placeholder="例: バッフル外し / 純正は保管" rows="2"
                                    class="appearance-none block w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm font-bold focus:border-purple-500 focus:ring-2 focus:ring-purple-500 focus:ring-opacity-50 outline-none transition">{{ old('note') }}</textarea>
                            </div>
                            @if(config('garage.record_photos_enabled'))
                            <div>
                                <label class="block text-xs font-bold text-gray-400 mb-1 ml-1">写真 <span class="font-normal text-gray-300">任意・複数（JPEG/PNG/WebP）</span></label>
                                <input type="file" name="images[]" multiple accept="image/jpeg,image/png,image/webp"
                                    class="block w-full text-xs text-gray-600 font-bold file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-gray-800 file:text-white hover:file:bg-gray-700 file:cursor-pointer">
                            </div>
                            @endif
                            <div class="flex items-center gap-3">
                                <button type="submit" class="flex-1 bg-purple-600 hover:bg-purple-500 text-white font-black py-3 rounded-xl shadow-lg transition transform active:scale-95"><span x-text="editId ? '更新する' : '記録する'">記録する</span></button>
                                <button type="button" x-show="editId" @click="cancelEdit()" class="shrink-0 text-xs font-bold text-gray-500 hover:text-gray-700 px-4 py-3 rounded-xl border border-gray-200 hover:bg-gray-50 transition-colors">編集をやめる</button>
                            </div>
                        </form>
                    </div>

                    {{-- 今ついてる装備（custom・装着中） --}}
                    @if(!empty($dashboard['installed_parts']) && count($dashboard['installed_parts']) > 0)
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 mb-6">
                        <h4 class="text-xs font-black text-gray-700 mb-3 flex items-center gap-1.5"><i data-lucide="sparkles" class="w-3.5 h-3.5 text-purple-500"></i> 今ついてる装備</h4>
                        <div class="flex flex-wrap gap-2">
                            @foreach($dashboard['installed_parts'] as $part)
                            <span class="inline-flex items-center gap-1.5 bg-purple-50 text-purple-700 text-[11px] font-bold px-2.5 py-1 rounded-lg border border-purple-100">
                                @if($part->category)<span class="text-purple-400">{{ $part->category }}</span>@endif
                                {{ $part->part_name }}@if($part->brand)<span class="text-purple-400">/ {{ $part->brand }}</span>@endif
                            </span>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    {{-- 整備履歴 --}}
                    <div class="space-y-3 mb-6">
                        @forelse($myBike->maintenanceLogs as $log)
                        @php $rem = $remByTitle->get($log->title); @endphp
                        <div class="bg-white rounded-xl p-4 border border-gray-100">
                            <div class="flex justify-between items-start gap-3">
                                <div class="min-w-0">
                                    <div class="text-[10px] font-bold text-gray-400 mb-0.5">{{ $log->maintained_at->format('Y/m/d') }}@if($log->odometer) ・ {{ number_format($log->odometer) }}km @endif</div>
                                    <div class="text-sm font-black text-gray-900">{{ $log->title }}</div>
                                    @if($rem && $rem['guideline'])
                                        @php $remain = (int) round($rem['guideline'] - $rem['distance']); @endphp
                                        <div class="text-[10px] font-bold mt-0.5 {{ $remain > 0 ? 'text-gray-400' : 'text-red-500' }}">{{ $remain > 0 ? '次回まで あと'.number_format($remain).'km' : number_format(abs($remain)).'km 超過' }}</div>
                                    @endif
                                    @if($log->vendor)<div class="text-[10px] text-gray-400 mt-0.5">{{ $log->vendor }}</div>@endif
                                </div>
                                <div class="flex items-center gap-2 shrink-0">
                                    @if($log->cost)<div class="text-sm font-black text-orange-600">{{ number_format($log->cost) }}円</div>@endif
                                    <button type="button" @click="editMaint($el)" data-id="{{ $log->id }}" data-title="{{ $log->title }}" data-cost="{{ $log->cost }}" data-vendor="{{ $log->vendor }}" data-note="{{ $log->note }}" data-odometer="{{ $log->odometer }}" data-date="{{ $log->maintained_at->format('Y-m-d') }}" class="text-gray-300 hover:text-blue-600 hover:bg-blue-50 w-7 h-7 rounded-lg flex items-center justify-center transition-colors" title="編集"><i data-lucide="pencil" class="w-3.5 h-3.5"></i></button>
                                    <button type="button" @click="copyMaint($el)" data-title="{{ $log->title }}" data-cost="{{ $log->cost }}" data-vendor="{{ $log->vendor }}" data-note="{{ $log->note }}" class="text-gray-300 hover:text-orange-600 hover:bg-orange-50 w-7 h-7 rounded-lg flex items-center justify-center transition-colors" title="前回と同じで記録"><i data-lucide="copy" class="w-3.5 h-3.5"></i></button>
                                    <form action="{{ route('mybikes.records.destroy', [$myBike->id, $log->id]) }}" method="POST" onsubmit="return confirm('この整備記録を削除しますか？\n総走行距離が再計算されます。');">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-gray-300 hover:text-red-600 hover:bg-red-50 w-7 h-7 rounded-lg flex items-center justify-center transition-colors" aria-label="削除"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i></button>
                                    </form>
                                </div>
                            </div>
                            @if($log->note)<div class="text-xs text-gray-500 bg-gray-50 p-2 rounded-lg mt-2 break-words">{{ $log->note }}</div>@endif
                            @include('mybikes._record-photos', ['myBike' => $myBike, 'record' => $log])
                        </div>
                        @empty
                        <div class="text-center py-8 border-2 border-dashed border-gray-200 rounded-2xl text-gray-400 font-bold text-sm">まだ整備記録がありません。上のプリセットから1タップで記録できます。</div>
                        @endforelse
                    </div>

                    {{-- カスタム履歴 --}}
                    @if($myBike->customRecords->isNotEmpty())
                    <div class="space-y-3">
                        <h4 class="text-xs font-black text-gray-500 px-1">カスタム履歴</h4>
                        @foreach($myBike->customRecords as $c)
                        <div class="bg-white rounded-xl p-4 border border-gray-100">
                            <div class="flex justify-between items-start gap-3">
                                <div class="min-w-0">
                                    <div class="text-[10px] font-bold text-gray-400 mb-0.5">{{ $c->maintained_at->format('Y/m/d') }}@if($c->category) ・ {{ $c->category }}@endif @if(!$c->is_installed)<span class="text-gray-300">(取外し済)</span>@endif</div>
                                    <div class="text-sm font-black text-gray-900">{{ $c->part_name }}@if($c->brand)<span class="text-gray-400 font-bold"> / {{ $c->brand }}</span>@endif</div>
                                    @if($c->vendor)<div class="text-[10px] text-gray-400 mt-0.5">{{ $c->vendor }}</div>@endif
                                </div>
                                <div class="flex items-center gap-2 shrink-0">
                                    @if($c->cost)<div class="text-sm font-black text-purple-600">{{ number_format($c->cost) }}円</div>@endif
                                    <button type="button" @click="editCustom($el)" data-id="{{ $c->id }}" data-part="{{ $c->part_name }}" data-brand="{{ $c->brand }}" data-category="{{ $c->category }}" data-cost="{{ $c->cost }}" data-vendor="{{ $c->vendor }}" data-note="{{ $c->note }}" data-odometer="{{ $c->odometer }}" data-date="{{ $c->maintained_at->format('Y-m-d') }}" data-installed="{{ $c->is_installed ? '1' : '0' }}" class="text-gray-300 hover:text-blue-600 hover:bg-blue-50 w-7 h-7 rounded-lg flex items-center justify-center transition-colors" title="編集"><i data-lucide="pencil" class="w-3.5 h-3.5"></i></button>
                                    <button type="button" @click="copyCustom($el)" data-part="{{ $c->part_name }}" data-brand="{{ $c->brand }}" data-category="{{ $c->category }}" data-cost="{{ $c->cost }}" data-vendor="{{ $c->vendor }}" data-note="{{ $c->note }}" class="text-gray-300 hover:text-purple-600 hover:bg-purple-50 w-7 h-7 rounded-lg flex items-center justify-center transition-colors" title="前回と同じで記録"><i data-lucide="copy" class="w-3.5 h-3.5"></i></button>
                                    <form action="{{ route('mybikes.records.destroy', [$myBike->id, $c->id]) }}" method="POST" onsubmit="return confirm('このカスタム記録を削除しますか？');">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-gray-300 hover:text-red-600 hover:bg-red-50 w-7 h-7 rounded-lg flex items-center justify-center transition-colors" aria-label="削除"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i></button>
                                    </form>
                                </div>
                            </div>
                            @if($c->note)<div class="text-xs text-gray-500 bg-gray-50 p-2 rounded-lg mt-2 break-words">{{ $c->note }}</div>@endif
                            @include('mybikes._record-photos', ['myBike' => $myBike, 'record' => $c])
                        </div>
                        @endforeach
                    </div>
                    @endif
                </div>

                {{-- 3. 会計簿セクション（維持費の数値レポート・private） --}}
                <div x-show="tab === 'ledger'" x-cloak class="animate-in fade-in slide-in-from-bottom-2">
                    @if($ledger['record_count'] === 0)
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-10 text-center">
                        <i data-lucide="wallet" class="w-10 h-10 text-gray-200 mx-auto mb-3"></i>
                        <p class="text-sm font-black text-gray-700 mb-1">まだ記録がありません</p>
                        <p class="text-xs text-gray-500">給油・整備・カスタムを記録すると、維持費の合計やkm単価が自動で集計されます。</p>
                    </div>
                    @else
                    {{-- サマリー --}}
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mb-6">
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 col-span-2 sm:col-span-1">
                            <p class="text-[10px] font-bold text-gray-400 mb-1">総維持費</p>
                            <p class="text-2xl font-black text-emerald-600">{{ number_format($ledger['total']) }}<span class="text-sm">円</span></p>
                            <p class="text-[10px] font-bold text-gray-400 mt-1">
                                @if($ledger['from'])対象: {{ $ledger['from']->format('Y/m/d') }} 〜 {{ optional($ledger['to'])->format('Y/m/d') }}@endif
                            </p>
                        </div>
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
                            <p class="text-[10px] font-bold text-gray-400 mb-1">km単価</p>
                            @if($ledger['per_km'] !== null)
                            <p class="text-2xl font-black text-gray-900">{{ number_format($ledger['per_km'], 1) }}<span class="text-sm">円/km</span></p>
                            <p class="text-[10px] font-bold text-gray-400 mt-1">走行 {{ number_format($ledger['distance']) }} km</p>
                            @else
                            <p class="text-2xl font-black text-gray-300">—</p>
                            <p class="text-[10px] font-bold text-gray-400 mt-1">走行距離の記録待ち</p>
                            @endif
                        </div>
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
                            <p class="text-[10px] font-bold text-gray-400 mb-1">内訳</p>
                            <div class="text-[11px] font-bold text-gray-600 space-y-0.5">
                                <div class="flex justify-between"><span>整備</span><span>{{ number_format($ledger['maintenance_total']) }}円</span></div>
                                <div class="flex justify-between"><span>カスタム</span><span>{{ number_format($ledger['custom_total']) }}円</span></div>
                                <div class="flex justify-between"><span>燃料</span><span>{{ number_format($ledger['fuel_total']) }}円</span></div>
                            </div>
                        </div>
                    </div>

                    {{-- ===== タイプ別 詳細レポート（Drivvo型・3c）===== --}}
                    {{-- 総括 --}}
                    <div class="bg-gradient-to-br from-gray-900 to-gray-800 rounded-2xl shadow-sm p-5 mb-4 text-white">
                        <h4 class="text-xs font-black text-gray-300 mb-3 flex items-center gap-1.5"><i data-lucide="gauge" class="w-3.5 h-3.5 text-emerald-400"></i> 総括</h4>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                            <div>
                                <p class="text-[10px] font-bold text-gray-400 mb-0.5">所有期間</p>
                                <p class="text-lg font-black">{{ $ledgerReport['summary']['months_owned'] ? number_format($ledgerReport['summary']['months_owned']).'ヶ月' : '—' }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold text-gray-400 mb-0.5">総支出</p>
                                <p class="text-lg font-black text-emerald-400">¥{{ number_format($ledgerReport['summary']['total']) }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold text-gray-400 mb-0.5">月平均維持費</p>
                                <p class="text-lg font-black">{{ $ledgerReport['summary']['monthly_avg'] !== null ? '¥'.number_format($ledgerReport['summary']['monthly_avg']) : '—' }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold text-gray-400 mb-0.5">km単価</p>
                                <p class="text-lg font-black">{{ $ledgerReport['summary']['per_km'] !== null ? number_format($ledgerReport['summary']['per_km'], 1).'円' : '—' }}</p>
                            </div>
                        </div>
                    </div>

                    {{-- タイプ別カード（折りたたみ・モバイルファースト。data-* で渡す＝属性エスケープ崩れ回避） --}}
                    <div class="space-y-3 mb-6" x-data="{ open: 'fuel' }">
                        {{-- 燃料 --}}
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                            <button type="button" @click="open = (open === 'fuel' ? '' : 'fuel')" class="w-full flex items-center justify-between p-4 text-left">
                                <span class="text-sm font-black text-gray-800 flex items-center gap-2"><i data-lucide="fuel" class="w-4 h-4 text-blue-500"></i> 燃料</span>
                                <span class="flex items-center gap-2">
                                    <span class="text-xs font-bold text-gray-500">¥{{ number_format($ledgerReport['fuel']['total']) }} ・ {{ $ledgerReport['fuel']['count'] }}回</span>
                                    <i data-lucide="chevron-down" class="w-4 h-4 text-gray-400 transition-transform" ::class="open === 'fuel' && 'rotate-180'"></i>
                                </span>
                            </button>
                            <div x-show="open === 'fuel'" x-cloak x-transition.opacity>
                                <div class="px-4 pb-4">
                                    @if($ledgerReport['fuel']['count'] === 0)
                                    <p class="text-xs text-gray-400 font-bold py-3 text-center">給油記録がありません</p>
                                    @else
                                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mb-3">
                                        <div class="bg-gray-50 rounded-xl p-3"><p class="text-[10px] font-bold text-gray-400">平均燃費</p><p class="text-base font-black text-blue-600">{{ $ledgerReport['fuel']['avg_efficiency'] !== null ? $ledgerReport['fuel']['avg_efficiency'].' km/L' : '—' }}</p></div>
                                        <div class="bg-gray-50 rounded-xl p-3"><p class="text-[10px] font-bold text-gray-400">平均給油量</p><p class="text-base font-black text-gray-800">{{ $ledgerReport['fuel']['avg_quantity'] !== null ? $ledgerReport['fuel']['avg_quantity'].' L' : '—' }}</p></div>
                                        <div class="bg-gray-50 rounded-xl p-3"><p class="text-[10px] font-bold text-gray-400">平均給油額</p><p class="text-base font-black text-gray-800">{{ $ledgerReport['fuel']['avg_cost'] !== null ? '¥'.number_format($ledgerReport['fuel']['avg_cost']) : '—' }}</p></div>
                                        <div class="bg-gray-50 rounded-xl p-3"><p class="text-[10px] font-bold text-gray-400">給油間隔</p><p class="text-base font-black text-gray-800">{{ $ledgerReport['fuel']['days_per_fill'] !== null ? $ledgerReport['fuel']['days_per_fill'].'日' : '—' }}</p></div>
                                        <div class="bg-gray-50 rounded-xl p-3"><p class="text-[10px] font-bold text-gray-400">給油あたり走行</p><p class="text-base font-black text-gray-800">{{ $ledgerReport['fuel']['km_per_fill'] !== null ? number_format($ledgerReport['fuel']['km_per_fill']).' km' : '—' }}</p></div>
                                        <div class="bg-gray-50 rounded-xl p-3"><p class="text-[10px] font-bold text-gray-400">最終給油</p><p class="text-base font-black text-gray-800">{{ $ledgerReport['fuel']['last_at'] ? $ledgerReport['fuel']['last_at']->format('Y/m/d') : '—' }}</p></div>
                                    </div>
                                    @if(count($dashboard['fuelChart']['data']) > 0)
                                    <p class="text-[10px] font-bold text-gray-400 mb-1">燃費推移</p>
                                    <div class="relative h-40"><canvas id="reportFuelChart"></canvas></div>
                                    @endif
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- 整備（費目別） --}}
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                            <button type="button" @click="open = (open === 'maint' ? '' : 'maint')" class="w-full flex items-center justify-between p-4 text-left">
                                <span class="text-sm font-black text-gray-800 flex items-center gap-2"><i data-lucide="wrench" class="w-4 h-4 text-orange-500"></i> 整備</span>
                                <span class="flex items-center gap-2">
                                    <span class="text-xs font-bold text-gray-500">¥{{ number_format($ledgerReport['maintenance']['total']) }} ・ {{ $ledgerReport['maintenance']['count'] }}回</span>
                                    <i data-lucide="chevron-down" class="w-4 h-4 text-gray-400 transition-transform" ::class="open === 'maint' && 'rotate-180'"></i>
                                </span>
                            </button>
                            <div x-show="open === 'maint'" x-cloak x-transition.opacity>
                                <div class="px-4 pb-4">
                                    @if($ledgerReport['maintenance']['count'] === 0)
                                    <p class="text-xs text-gray-400 font-bold py-3 text-center">整備記録がありません</p>
                                    @else
                                    {{-- 費目別 --}}
                                    <div class="space-y-2 mb-4">
                                        @foreach($ledgerReport['maintenance']['categories'] as $cat)
                                        <div class="flex items-center justify-between bg-gray-50 rounded-xl px-3 py-2">
                                            <div>
                                                <span class="text-xs font-black text-gray-700">{{ $cat['label'] }}</span>
                                                <span class="text-[10px] text-gray-400 font-bold ml-1">{{ $cat['count'] }}回@if($cat['last_at']) ・ 最終{{ $cat['last_at']->format('Y/m/d') }}@endif</span>
                                            </div>
                                            <span class="text-xs font-black text-orange-600">¥{{ number_format($cat['total']) }}</span>
                                        </div>
                                        @endforeach
                                    </div>
                                    {{-- 次回予定（リマインダー統合） --}}
                                    @if(!empty($ledgerReport['maintenance']['reminders']))
                                    <p class="text-[10px] font-bold text-gray-400 mb-1.5">次回予定（目安）</p>
                                    <div class="space-y-1.5">
                                        @foreach($ledgerReport['maintenance']['reminders'] as $rem)
                                        @if($rem['guideline'])
                                        @php $remain = (int) round($rem['guideline'] - $rem['distance']); @endphp
                                        <div class="flex items-center justify-between text-[11px] font-bold">
                                            <span class="text-gray-600">{{ $rem['title'] }}</span>
                                            <span class="{{ $remain > 0 ? 'text-gray-400' : 'text-red-500' }}">{{ $remain > 0 ? 'あと'.number_format($remain).'km' : number_format(abs($remain)).'km 超過' }}</span>
                                        </div>
                                        @endif
                                        @endforeach
                                    </div>
                                    @endif
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- カスタム --}}
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                            <button type="button" @click="open = (open === 'custom' ? '' : 'custom')" class="w-full flex items-center justify-between p-4 text-left">
                                <span class="text-sm font-black text-gray-800 flex items-center gap-2"><i data-lucide="sparkles" class="w-4 h-4 text-purple-500"></i> カスタム</span>
                                <span class="flex items-center gap-2">
                                    <span class="text-xs font-bold text-gray-500">¥{{ number_format($ledgerReport['custom']['total']) }} ・ {{ $ledgerReport['custom']['count'] }}回</span>
                                    <i data-lucide="chevron-down" class="w-4 h-4 text-gray-400 transition-transform" ::class="open === 'custom' && 'rotate-180'"></i>
                                </span>
                            </button>
                            <div x-show="open === 'custom'" x-cloak x-transition.opacity>
                                <div class="px-4 pb-4">
                                    @if($ledgerReport['custom']['count'] === 0)
                                    <p class="text-xs text-gray-400 font-bold py-3 text-center">カスタム記録がありません</p>
                                    @else
                                    <p class="text-[10px] font-bold text-gray-400 mb-2">累計カスタム費 <span class="text-purple-600 font-black">¥{{ number_format($ledgerReport['custom']['total']) }}</span> ／ 装着中 {{ $ledgerReport['custom']['installed_count'] }}点</p>
                                    @if($ledgerReport['custom']['installed_count'] > 0)
                                    <div class="flex flex-wrap gap-2">
                                        @foreach($ledgerReport['custom']['installed'] as $part)
                                        <span class="inline-flex items-center gap-1.5 bg-purple-50 text-purple-700 text-[11px] font-bold px-2.5 py-1 rounded-lg border border-purple-100">
                                            @if($part->category)<span class="text-purple-400">{{ $part->category }}</span>@endif
                                            {{ $part->part_name }}@if($part->brand)<span class="text-purple-400">/ {{ $part->brand }}</span>@endif
                                        </span>
                                        @endforeach
                                    </div>
                                    @else
                                    <p class="text-xs text-gray-400 font-bold">現在装着中のカスタムはありません</p>
                                    @endif
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- 月次推移グラフ --}}
                    @if(count($ledgerChart['monthly']['labels']) > 0)
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 mb-6">
                        <h4 class="text-xs font-black text-gray-700 mb-3 flex items-center gap-1.5"><i data-lucide="bar-chart-3" class="w-3.5 h-3.5 text-emerald-500"></i> 月次推移</h4>
                        <div class="relative h-48"><canvas id="ledgerMonthChart"></canvas></div>
                    </div>
                    @endif

                    {{-- 費目別内訳 --}}
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 mb-6">
                        <h4 class="text-xs font-black text-gray-700 mb-4 flex items-center gap-1.5"><i data-lucide="pie-chart" class="w-3.5 h-3.5 text-emerald-500"></i> 費目別内訳</h4>
                        @if(count($ledgerChart['category']['labels']) > 0)
                        <div class="relative h-56 mb-4"><canvas id="ledgerCategoryChart"></canvas></div>
                        @endif
                        <div class="space-y-3">
                            @foreach($ledger['categories'] as $cat)
                            <div>
                                <div class="flex justify-between items-baseline mb-1">
                                    <span class="text-xs font-black text-gray-700">{{ $cat['label'] }}</span>
                                    <span class="text-xs font-bold text-gray-500">{{ number_format($cat['amount']) }}円 <span class="text-[10px] text-gray-400">({{ $cat['percent'] }}%)</span></span>
                                </div>
                                <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-emerald-400 rounded-full" style="width: {{ max(2, $cat['percent']) }}%"></div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- 月別（直近12ヶ月） --}}
                    @if(!empty($ledger['by_month']))
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 mb-6">
                        <h4 class="text-xs font-black text-gray-700 mb-3 flex items-center gap-1.5"><i data-lucide="calendar" class="w-3.5 h-3.5 text-emerald-500"></i> 月別（直近12ヶ月）</h4>
                        <div class="space-y-1.5">
                            @foreach($ledger['by_month'] as $month => $row)
                            <div class="flex items-center justify-between text-[11px] font-bold py-1 border-b border-gray-50 last:border-0">
                                <span class="text-gray-500">{{ $month }}</span>
                                <span class="text-gray-400">整備{{ number_format($row['maintenance']) }} / カスタム{{ number_format($row['custom']) }} / 燃料{{ number_format($row['fuel']) }}</span>
                                <span class="text-gray-900 font-black w-20 text-right">{{ number_format($row['total']) }}円</span>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    {{-- 年別 --}}
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                        <h4 class="text-xs font-black text-gray-700 mb-3 flex items-center gap-1.5"><i data-lucide="calendar-range" class="w-3.5 h-3.5 text-emerald-500"></i> 年別</h4>
                        @if(count($ledgerChart['yearly']['labels']) > 0)
                        <div class="relative h-44 mb-4"><canvas id="ledgerYearChart"></canvas></div>
                        @endif
                        <div class="space-y-1.5">
                            @foreach($ledger['by_year'] as $year => $row)
                            <div class="flex items-center justify-between text-[11px] font-bold py-1 border-b border-gray-50 last:border-0">
                                <span class="text-gray-500">{{ $year }}年</span>
                                <span class="text-gray-400">整備{{ number_format($row['maintenance']) }} / カスタム{{ number_format($row['custom']) }} / 燃料{{ number_format($row['fuel']) }}</span>
                                <span class="text-gray-900 font-black w-20 text-right">{{ number_format($row['total']) }}円</span>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endif
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
    
    {{-- Chart.js（燃費グラフ or 会計簿にデータがある時だけ CDN 読込） --}}
    @php $needChartJs = count($dashboard['fuelChart']['data']) > 0 || $ledger['record_count'] > 0; @endphp
    @if($needChartJs)
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.js"></script>
    @endif

    @if(count($dashboard['fuelChart']['data']) > 0)
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

    @if($ledger['record_count'] > 0)
    {{-- 会計簿グラフ（タブが隠れているため、会計簿タブを開いた時に遅延描画＝サイズ崩れ防止） --}}
    <script>
        (function () {
            var data = @json($ledgerChart);
            var fuelEff = { labels: @json($dashboard['fuelChart']['labels']), data: @json($dashboard['fuelChart']['data']) }; {{-- 3c: タイプ別レポートの燃費推移（既存fuelChart流用） --}}
            var drawn = false;
            var yen = function (v) { return '¥' + Number(v).toLocaleString('ja-JP'); };
            var palette = ['#10b981', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6', '#14b8a6', '#9ca3af', '#f97316'];
            window.drawLedgerCharts = function () {
                if (drawn || typeof Chart === 'undefined') return;
                drawn = true;
                var m = document.getElementById('ledgerMonthChart');
                if (m && data.monthly.labels.length) {
                    new Chart(m, { type: 'bar',
                        data: { labels: data.monthly.labels, datasets: [{ label: '維持費', data: data.monthly.values, backgroundColor: '#34d399' }] },
                        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { callbacks: { label: function (c) { return yen(c.parsed.y); } } } }, scales: { y: { beginAtZero: true, ticks: { callback: function (v) { return Number(v).toLocaleString('ja-JP'); } } } } } });
                }
                var c = document.getElementById('ledgerCategoryChart');
                if (c && data.category.labels.length) {
                    new Chart(c, { type: 'doughnut',
                        data: { labels: data.category.labels, datasets: [{ data: data.category.values, backgroundColor: palette }] },
                        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } }, tooltip: { callbacks: { label: function (c2) { return c2.label + ': ' + yen(c2.parsed); } } } } } });
                }
                var y = document.getElementById('ledgerYearChart');
                if (y && data.yearly.labels.length) {
                    new Chart(y, { type: 'bar',
                        data: { labels: data.yearly.labels, datasets: [{ label: '維持費', data: data.yearly.values, backgroundColor: '#60a5fa' }] },
                        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { callbacks: { label: function (c3) { return yen(c3.parsed.y); } } } }, scales: { y: { beginAtZero: true } } } });
                }
                {{-- 3c: タイプ別レポート内の燃費推移（既存 fuelChart データを流用） --}}
                var rf = document.getElementById('reportFuelChart');
                if (rf && fuelEff.data.length) {
                    new Chart(rf, { type: 'line',
                        data: { labels: fuelEff.labels, datasets: [{ label: '燃費 (km/L)', data: fuelEff.data, borderColor: '#3b82f6', backgroundColor: 'rgba(59,130,246,0.1)', fill: true, tension: 0.3, pointRadius: 2 }] },
                        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { callbacks: { label: function (c4) { return c4.parsed.y + ' km/L'; } } } }, scales: { y: { beginAtZero: false } } } });
                }
            };
        })();
    </script>
    @endif

    <script>
        if (typeof lucide !== 'undefined') lucide.createIcons();
    </script>
</x-layout>