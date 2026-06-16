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
                        
                        <form action="{{ route('mybikes.fuel.store', $myBike->id) }}" method="POST" class="space-y-4"
                              data-last-odometer="{{ $myBike->current_odometer }}"
                              data-odo-multiplier="{{ config('garage.odometer_jump_multiplier', 5) }}"
                              onsubmit="return (function(f){
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
                                <div class="flex items-center gap-3">
                                    <div class="text-right">
                                        <div class="text-lg font-black text-blue-600">
                                            {{ $log->efficiency ? $log->efficiency . ' km/L' : '-' }}
                                        </div>
                                        <div class="text--[10px] font-bold text-gray-400">
                                            {{ $log->quantity }}L / {{ number_format($log->cost) }}円
                                        </div>
                                    </div>
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