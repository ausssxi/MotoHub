<x-layout>
    <x-slot:title>バイクショップの掲載リクエスト（未掲載店の投稿）｜MotoHub</x-slot:title>
    <x-slot:metaDescription>MotoHubに掲載されていないバイクショップ（販売店・整備/修理店）を教えてください。投稿は運営の確認後に掲載されます。評価・点数の投稿はできません。</x-slot:metaDescription>
    <x-slot:robotsMeta>noindex, follow</x-slot:robotsMeta>

    <x-slot:navigation>
        <x-navigation :showSearch="true" />
    </x-slot:navigation>

    <x-slot:scripts>
        <script>
        // 送信前の重複チェック（ソフト警告・ブロックしない・JS無効でも通常送信可）
        (function () {
            const checkUrl = @js(route('shops.submit.check'));
            const nameEl = document.querySelector('input[name="shop_name"]');
            const prefEl = document.querySelector('select[name="prefecture"]');
            const cityEl = document.querySelector('input[name="city"]');
            const phoneEl = document.querySelector('input[name="phone"]');
            const box = document.getElementById('dup-warning');
            const list = document.getElementById('dup-list');
            if (!nameEl || !box) return;

            let timer = null;
            function debounce(fn) { clearTimeout(timer); timer = setTimeout(fn, 250); }

            function esc(s) { const d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }

            function render(cands) {
                if (!cands || cands.length === 0) { box.classList.add('hidden'); list.innerHTML = ''; return; }
                list.innerHTML = cands.map(c =>
                    `<li class="text-xs bg-white rounded-lg border border-amber-100 px-3 py-2">
                        <span class="font-bold text-gray-800">${esc(c.name)}</span>
                        <span class="text-gray-400"> — ${esc(c.address)}</span>
                        <a href="${esc(c.detail_url)}" target="_blank" rel="noopener" class="block text-blue-600 font-bold mt-0.5">店舗ページを見る →</a>
                    </li>`
                ).join('');
                box.classList.remove('hidden');
                if (typeof lucide !== 'undefined') lucide.createIcons();
            }

            function check() {
                const name = nameEl.value.trim();
                const pref = prefEl ? prefEl.value.trim() : '';
                const city = cityEl ? cityEl.value.trim() : '';
                if (!name || !pref || !city) return;
                const params = new URLSearchParams({ shop_name: name, prefecture: pref, city: city });
                if (phoneEl && phoneEl.value.trim()) params.set('phone', phoneEl.value.trim());
                fetch(checkUrl + '?' + params.toString(), { headers: { 'Accept': 'application/json' } })
                    .then(r => r.ok ? r.json() : { candidates: [] })
                    .then(d => render(d.candidates))
                    .catch(() => {});
            }

            nameEl.addEventListener('blur', () => debounce(check));
            if (phoneEl) phoneEl.addEventListener('blur', () => debounce(check));
        })();

        // 都道府県選択時に既存の市区町村を datalist にサジェスト
        (function () {
            const citiesUrl = @js(route('shops.submit.cities'));
            const prefEl = document.querySelector('select[name="prefecture"]');
            const dl = document.getElementById('city-list');
            if (!prefEl || !dl) return;
            function load() {
                const pref = prefEl.value.trim();
                if (!pref) { dl.innerHTML = ''; return; }
                fetch(citiesUrl + '?pref=' + encodeURIComponent(pref), { headers: { 'Accept': 'application/json' } })
                    .then(r => r.ok ? r.json() : { cities: [] })
                    .then(d => { dl.innerHTML = (d.cities || []).map(c => '<option value="' + c.replace(/"/g, '&quot;') + '">').join(''); })
                    .catch(() => {});
            }
            prefEl.addEventListener('change', load);
            if (prefEl.value) load(); // プリフィル時
        })();
        </script>
    </x-slot:scripts>

    <div class="bg-gray-50 min-h-screen py-8">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">

            <nav class="text-xs font-bold text-gray-400 mb-6" aria-label="Breadcrumb">
                <ol class="flex items-center space-x-2">
                    <li><a href="/" class="hover:text-gray-600">HOME</a></li>
                    <li><span class="text-gray-300">＞</span></li>
                    <li><a href="{{ route('shops.area.index') }}" class="hover:text-gray-600">バイクショップ</a></li>
                    <li><span class="text-gray-300">＞</span></li>
                    <li><span class="text-gray-800">掲載リクエスト</span></li>
                </ol>
            </nav>

            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8">
                <h1 class="text-2xl font-black text-gray-900 mb-2">掲載されていないバイクショップを教える</h1>
                <p class="text-sm text-gray-500 font-bold mb-6">
                    MotoHubにまだ載っていないバイクショップ（販売店・整備/修理店）を投稿できます。運営の確認後に掲載されます。
                </p>

                @if(session('submission_success'))
                <div class="mb-6 text-sm font-bold text-green-700 bg-green-50 border border-green-200 rounded-xl px-4 py-3">
                    ありがとうございます。投稿を受け付けました。運営の確認後に掲載されます。
                </div>
                @endif

                @if($errors->any())
                <div class="mb-6 text-xs font-bold text-rose-700 bg-rose-50 border border-rose-200 rounded-xl px-4 py-3">
                    <ul class="list-disc list-inside space-y-0.5">
                        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
                @endif

                <form method="POST" action="{{ route('shops.submit.store') }}" class="space-y-5" enctype="multipart/form-data">
                    @csrf

                    <div>
                        <label class="block text-xs font-black text-gray-600 mb-1">店名 <span class="text-rose-500">*</span></label>
                        <input type="text" name="shop_name" value="{{ old('shop_name') }}" maxlength="100" required
                               placeholder="例: 〇〇モータース"
                               class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
                    </div>

                    {{-- 店種（承認時に shops.shop_type へ反映） --}}
                    <div>
                        <label class="block text-xs font-black text-gray-600 mb-2">お店の種類</label>
                        <div class="space-y-2">
                            @foreach($shopTypeOptions as $key => $label)
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="shop_type" value="{{ $key }}" class="accent-blue-600"
                                       @checked(old('shop_type', $prefillType) === $key)>
                                <span class="text-sm font-bold text-gray-700">{{ $label }}</span>
                            </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-black text-gray-600 mb-1">都道府県 <span class="text-rose-500">*</span></label>
                            <select name="prefecture" required
                                    class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
                                <option value="">選択</option>
                                @foreach($prefectures as $pref)
                                <option value="{{ $pref }}" @selected(old('prefecture', $prefillPref) === $pref)>{{ $pref }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-black text-gray-600 mb-1">市区町村 <span class="text-rose-500">*</span></label>
                            <input type="text" name="city" value="{{ old('city', $prefillCity) }}" maxlength="50" required
                                   list="city-list" autocomplete="off"
                                   placeholder="例: 世田谷区 / 横浜市都筑区"
                                   class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
                            <datalist id="city-list"></datalist>
                            <p class="text-[10px] text-gray-400 mt-1">※ 政令市は「横浜市都筑区」のように市＋区で入力してください。</p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-black text-gray-600 mb-1">住所（任意）</label>
                        <input type="text" name="address" value="{{ old('address') }}" maxlength="200"
                               placeholder="荏田南4-27-50（市区町村は含めない）"
                               class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-black text-gray-600 mb-1">電話番号（任意）</label>
                            <input type="text" name="phone" value="{{ old('phone') }}" maxlength="20"
                                   placeholder="03-1234-5678"
                                   class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-black text-gray-600 mb-1">サイトURL（任意）</label>
                            <input type="url" name="website_url" value="{{ old('website_url') }}" maxlength="200"
                                   placeholder="https://..."
                                   class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-black text-gray-600 mb-2">対応サービス（任意）</label>
                        <div class="flex flex-wrap gap-2">
                            @foreach($serviceTagOptions as $tag)
                            <label class="flex items-center gap-1.5 cursor-pointer bg-gray-50 border border-gray-200 rounded-lg px-2.5 py-1.5">
                                <input type="checkbox" name="service_tags[]" value="{{ $tag }}" class="accent-green-600"
                                       @checked(in_array($tag, old('service_tags', []), true))>
                                <span class="text-xs font-bold text-gray-700">{{ $tag }}</span>
                            </label>
                            @endforeach
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-black text-gray-600 mb-2">受け入れ情報（任意）</label>
                        <div class="flex flex-wrap gap-2">
                            @foreach($acceptanceFlags as $key => $label)
                            <label class="flex items-center gap-1.5 cursor-pointer bg-gray-50 border border-gray-200 rounded-lg px-2.5 py-1.5">
                                <input type="checkbox" name="acceptance_flags[]" value="{{ $key }}" class="accent-blue-600"
                                       @checked(in_array($key, old('acceptance_flags', []), true))>
                                <span class="text-xs font-bold text-gray-700">{{ $label }}</span>
                            </label>
                            @endforeach
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-black text-gray-600 mb-1">店舗の写真（任意・1枚）</label>
                        <input type="file" name="image" accept="image/*"
                               class="w-full text-sm text-gray-600 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                        <p class="text-[10px] text-gray-400 mt-1">※ JPEG/PNG/WebP・5MBまで。位置情報（EXIF）は保存前に自動で削除されます。承認後に掲載されます。</p>
                    </div>

                    <div>
                        <label class="block text-xs font-black text-gray-600 mb-1">コメント（任意）</label>
                        <textarea name="comment" rows="3" maxlength="1000"
                                  placeholder="どんなお店か、どんな対応をしてもらえたか等"
                                  class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">{{ old('comment') }}</textarea>
                    </div>

                    {{-- 投稿者名（Reviewパターン: ログイン時は公開ハンドル・匿名時は入力） --}}
                    @auth
                        @if(empty(auth()->user()->review_display_name))
                        <div>
                            <label class="block text-xs font-black text-gray-600 mb-1">公開表示名（初回のみ・以降固定）</label>
                            <input type="text" name="submitter_name" value="{{ old('submitter_name') }}" maxlength="30"
                                   class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
                        </div>
                        @else
                        <p class="text-[11px] text-gray-500 font-bold">「{{ auth()->user()->review_display_name }}」として投稿します。</p>
                        @endif
                    @else
                        <div>
                            <label class="block text-xs font-black text-gray-600 mb-1">お名前（任意・未入力なら「名無しライダー」）</label>
                            <input type="text" name="submitter_name" value="{{ old('submitter_name') }}" maxlength="50"
                                   class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
                        </div>
                    @endauth

                    {{-- ハニーポット（人間には非表示・ボット除け。実在の website_url とは別名） --}}
                    <input type="text" name="fax_number" tabindex="-1" autocomplete="off" aria-hidden="true"
                           class="hidden" style="display:none">

                    {{-- 重複警告（JSで送信前に表示。ブロックはしない） --}}
                    <div id="dup-warning" class="hidden bg-amber-50 border border-amber-200 rounded-xl p-4">
                        <p class="text-sm font-black text-amber-800 mb-2 flex items-center gap-1.5">
                            <i data-lucide="alert-triangle" class="w-4 h-4"></i>
                            この店はすでに掲載されている可能性があります
                        </p>
                        <ul id="dup-list" class="space-y-2 mb-2"></ul>
                        <p class="text-[11px] text-amber-700 leading-relaxed">
                            上記の店の受け入れ情報や公式サイトURLをお持ちの場合は、このまま投稿してください。承認後、既存の店舗ページに反映されます。
                        </p>
                    </div>

                    <button type="submit"
                            class="w-full bg-blue-600 hover:bg-blue-700 text-white font-black text-sm px-4 py-3 rounded-xl transition active:scale-[0.99]">
                        この店を投稿する
                    </button>
                    <p class="text-[10px] text-gray-400 leading-relaxed">
                        ※ 評価・点数の投稿はできません。投稿は運営の確認後に掲載されます。
                    </p>
                </form>
            </div>
        </div>
    </div>
</x-layout>
