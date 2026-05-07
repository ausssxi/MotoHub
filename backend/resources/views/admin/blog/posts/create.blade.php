<x-layout>
    <x-slot:title>新規記事作成 | MotoHub Blog</x-slot:title>

    <x-slot:navigation>
        <x-navigation :showSearch="false" />
    </x-slot:navigation>

    <x-slot:styles>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/highlight.js@11/styles/github.min.css">
        <style>
            .editor-container { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; min-height: 500px; }
            .editor-pane textarea { width: 100%; height: 100%; min-height: 500px; font-family: 'JetBrains Mono', 'Fira Code', monospace; font-size: 0.875rem; line-height: 1.7; padding: 1rem; border: 1px solid #d1d5db; border-radius: 0.5rem; resize: vertical; }
            .preview-pane { padding: 1rem; border: 1px solid #d1d5db; border-radius: 0.5rem; overflow-y: auto; background: #fff; }
            .preview-pane h2 { font-size: 1.5rem; font-weight: 700; margin: 1.5rem 0 0.75rem; padding-bottom: 0.5rem; border-bottom: 2px solid #e5e7eb; }
            .preview-pane h3 { font-size: 1.25rem; font-weight: 600; margin: 1.25rem 0 0.5rem; }
            .preview-pane p { margin-bottom: 1rem; line-height: 1.8; }
            .preview-pane ul, .preview-pane ol { margin: 1rem 0; padding-left: 1.5rem; }
            .preview-pane li { margin-bottom: 0.25rem; }
            .preview-pane code { background: #f3f4f6; padding: 0.125rem 0.375rem; border-radius: 0.25rem; font-size: 0.875rem; }
            .preview-pane pre { background: #1e293b; color: #e2e8f0; padding: 1rem; border-radius: 0.5rem; overflow-x: auto; margin: 1rem 0; }
            .preview-pane pre code { background: transparent; color: inherit; padding: 0; }
            .preview-pane blockquote { border-left: 4px solid #3b82f6; padding-left: 1rem; margin: 1rem 0; color: #4b5563; }
            .preview-pane img { max-width: 100%; border-radius: 0.5rem; }
            .preview-pane table { width: 100%; border-collapse: collapse; margin: 1rem 0; }
            .preview-pane th, .preview-pane td { border: 1px solid #d1d5db; padding: 0.5rem; }
            .preview-pane th { background: #f9fafb; font-weight: 600; }
            @media (max-width: 1024px) { .editor-container { grid-template-columns: 1fr; } }
            .drop-zone-active { border-color: #3b82f6 !important; background: #eff6ff !important; }

            .tag-input-wrap { position: relative; }
            .tag-input-container { display: flex; flex-wrap: wrap; gap: 0.375rem; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.5rem; min-height: 2.5rem; align-items: center; cursor: text; background: #fff; }
            .tag-input-container:focus-within { border-color: #3b82f6; box-shadow: 0 0 0 1px #3b82f6; }
            .tag-chip { display: inline-flex; align-items: center; gap: 0.25rem; padding: 0.25rem 0.625rem; background: #dbeafe; color: #1e40af; border-radius: 9999px; font-size: 0.8125rem; white-space: nowrap; }
            .tag-chip button { background: none; border: none; cursor: pointer; color: #3b82f6; font-size: 1rem; line-height: 1; padding: 0 0.125rem; }
            .tag-chip button:hover { color: #1e3a8a; }
            .tag-input { border: none; outline: none; flex: 1; min-width: 120px; font-size: 0.875rem; background: transparent; }
            .tag-suggestions { position: absolute; top: 100%; left: 0; right: 0; background: #fff; border: 1px solid #d1d5db; border-radius: 0.5rem; box-shadow: 0 4px 6px rgba(0,0,0,0.1); z-index: 50; max-height: 200px; overflow-y: auto; margin-top: 0.25rem; }
            .tag-suggestions div { padding: 0.5rem 0.75rem; cursor: pointer; font-size: 0.875rem; }
            .tag-suggestions div:hover, .tag-suggestions div.active { background: #eff6ff; color: #1d4ed8; }
        </style>
    </x-slot:styles>

    <div class="max-w-7xl mx-auto px-4 py-8">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold">新規記事作成</h1>
            <a href="{{ route('admin.blog.posts.index') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; 記事一覧に戻る</a>
        </div>

        @if(request('lat') && request('lng'))
            <div class="mb-4 p-4 bg-blue-50 border border-blue-200 rounded-lg flex items-start gap-3">
                <svg class="w-5 h-5 text-blue-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <div>
                    <p class="text-sm font-medium text-blue-800">ライダーズマップから記事を作成中です</p>
                    <p class="text-xs text-blue-600 mt-0.5">テンプレートを参考に、スポットの魅力を自由に書いてください。位置情報は自動で設定済みです。</p>
                </div>
            </div>
        @endif

        @if($errors->any())
            <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg">
                <ul class="list-disc list-inside">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.blog.posts.store') }}" enctype="multipart/form-data" id="postForm">
            @csrf

            {{-- タイトル --}}
            <div class="mb-4">
                <input type="text" name="title" value="{{ old('title') }}"
                       placeholder="{{ request('lat') && request('lng') ? '例: 箱根ターンパイクの絶景ポイント' : '記事タイトル' }}"
                       class="w-full text-2xl font-bold border-0 border-b-2 border-gray-200 focus:border-blue-500 focus:ring-0 px-0 py-2"
                       required>
            </div>

            {{-- 位置情報プレビュー --}}
            @if(request('lat') && request('lng'))
                <div id="location-preview-bar" class="mb-4 px-4 py-3 bg-cyan-50 border border-cyan-200 rounded-lg flex items-center gap-3">
                    <span class="text-lg">&#x1F4CD;</span>
                    <div class="flex-1 min-w-0">
                        <span class="location-address text-sm font-medium text-cyan-900">住所を取得中...</span>
                        <span class="text-xs text-cyan-600 ml-2">（{{ request('lat') }}, {{ request('lng') }}）</span>
                    </div>
                    <span class="text-xs text-cyan-500 shrink-0">位置情報は自動設定済み</span>
                </div>
            @endif

            {{-- Markdownエディタ + プレビュー --}}
            <div class="mb-6">
                <div class="flex items-center justify-between mb-2">
                    <label class="text-sm font-medium text-gray-600">本文（Markdown）</label>
                    <span class="text-xs text-gray-400">
                        <span id="word-count">0 文字</span> ・ <span id="reading-time">約 0 分で読了</span>
                    </span>
                </div>
                <div class="flex items-center gap-1 sticky top-16 z-10 bg-white py-2 -mt-2 border-b border-gray-100">
                    <button type="button" id="imageUploadBtn" class="p-1.5 rounded hover:bg-gray-100 text-gray-500 hover:text-gray-700 transition" title="画像を挿入">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0022.5 18.75V5.25A2.25 2.25 0 0020.25 3H3.75A2.25 2.25 0 001.5 5.25v13.5A2.25 2.25 0 003.75 21z" />
                        </svg>
                    </button>
                    <input type="file" id="imageFileInput" accept="image/*" class="hidden" multiple>
                    <span class="text-xs text-gray-400 ml-1">ドラッグ＆ドロップ・ペーストでも挿入可能</span>
                </div>
                <div class="editor-container">
                    <div class="editor-pane">
                        <textarea name="body" id="markdownEditor" placeholder="Markdownで記事を書く...">{{ old('body') }}</textarea>
                    </div>
                    <div class="preview-pane" id="markdownPreview">
                        <p class="text-gray-400">プレビューがここに表示されます</p>
                    </div>
                </div>
            </div>

            {{-- タグ --}}
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-600 mb-2">タグ</label>
                <div class="tag-input-wrap">
                    <div class="tag-input-container" id="tagContainer">
                        <input type="text" id="tagInput" class="tag-input" placeholder="タグを入力してEnter..." autocomplete="off">
                    </div>
                    <div class="tag-suggestions hidden" id="tagSuggestions"></div>
                </div>
            </div>

            {{-- シリーズ --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1">シリーズ（連載）</label>
                    <p class="text-xs text-gray-400 mb-1.5">複数の記事をまとめて連載として読ませる機能です</p>
                    <select name="series_id" id="seriesSelect" class="w-full rounded-lg border-gray-300 text-sm">
                        <option value="">なし</option>
                        @foreach($series as $s)
                            <option value="{{ $s->id }}" @selected(old('series_id') == $s->id)>{{ $s->title }}</option>
                        @endforeach
                    </select>
                    <a href="{{ route('admin.blog.series.create') }}" target="_blank"
                       id="newSeriesLink" class="inline-block mt-1.5 text-xs text-blue-600 hover:text-blue-800 hover:underline">新しいシリーズを作成 &rarr;</a>
                </div>
                <div id="seriesOrderWrapper">
                    <label class="block text-sm font-medium text-gray-600 mb-1">この記事は第何回？</label>
                    <input type="number" name="series_order" value="{{ old('series_order') }}" min="1"
                           class="w-full rounded-lg border-gray-300 text-sm">
                </div>
            </div>

            {{-- カバー画像 + 紹介文 --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1">カバー画像</label>
                    <p class="text-xs text-gray-400 mb-1.5">記事一覧やSNSシェア時に表示されるメイン画像です</p>
                    <input type="file" name="eyecatch_image" accept="image/*"
                           class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1">記事の紹介文（空欄で自動生成）</label>
                    <p class="text-xs text-gray-400 mb-1.5">検索結果やSNSシェア時に表示される説明文です</p>
                    <textarea name="excerpt" rows="3" class="w-full rounded-lg border-gray-300 text-sm">{{ old('excerpt') }}</textarea>
                </div>
            </div>

            {{-- 位置情報 --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1">緯度</label>
                    <p class="text-xs text-gray-400 mb-1.5">ライダーズマップ「記事」レイヤーに表示。[riders-map]がある場合は空欄で自動設定</p>
                    <input type="number" name="latitude" id="latitudeInput" value="{{ old('latitude', request('lat')) }}" step="0.0000001" min="-90" max="90"
                           class="w-full rounded-lg border-gray-300 text-sm" placeholder="35.6812362">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1">経度</label>
                    <p class="text-xs text-gray-400 mb-1.5">&nbsp;</p>
                    <input type="number" name="longitude" id="longitudeInput" value="{{ old('longitude', request('lng')) }}" step="0.0000001" min="-180" max="180"
                           class="w-full rounded-lg border-gray-300 text-sm" placeholder="139.7671248">
                </div>
            </div>

            {{-- ステータス + 公開日時 --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1">ステータス</label>
                    <select name="status" id="statusSelect" class="w-full rounded-lg border-gray-300 text-sm">
                        <option value="draft" @selected(old('status', 'draft')==='draft')>下書き</option>
                        <option value="published" @selected(old('status')==='published')>公開</option>
                        <option value="scheduled" @selected(old('status')==='scheduled')>予約</option>
                    </select>
                </div>
                <div id="publishedAtWrapper">
                    <label class="block text-sm font-medium text-gray-600 mb-1">公開日時</label>
                    <input type="datetime-local" name="published_at" value="{{ old('published_at') }}"
                           class="w-full rounded-lg border-gray-300 text-sm">
                </div>
            </div>

            {{-- SEO設定 --}}
            <details class="mb-6">
                <summary class="text-sm font-medium text-gray-600 cursor-pointer hover:text-gray-800">SEO設定（オプション）</summary>
                <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm text-gray-500 mb-1">メタタイトル</label>
                        <input type="text" name="meta_title" value="{{ old('meta_title') }}" maxlength="255"
                               class="w-full rounded-lg border-gray-300 text-sm" placeholder="空欄でタイトルを使用">
                    </div>
                    <div>
                        <label class="block text-sm text-gray-500 mb-1">メタディスクリプション</label>
                        <textarea name="meta_description" rows="2" maxlength="300"
                                  class="w-full rounded-lg border-gray-300 text-sm" placeholder="空欄で抜粋を使用">{{ old('meta_description') }}</textarea>
                    </div>
                </div>
            </details>

            {{-- Slug --}}
            <details class="mb-6">
                <summary class="text-sm font-medium text-gray-600 cursor-pointer hover:text-gray-800">Slug（空欄で自動生成）</summary>
                <div class="mt-4">
                    <input type="text" name="slug" value="{{ old('slug') }}" placeholder="custom-slug-here"
                           class="w-full rounded-lg border-gray-300 text-sm">
                </div>
            </details>

            {{-- 送信ボタン --}}
            <div class="flex gap-4">
                <button type="submit" class="px-6 py-3 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition">
                    記事を保存
                </button>
                <a href="{{ route('admin.blog.posts.index') }}" class="px-6 py-3 bg-gray-100 text-gray-700 font-medium rounded-lg hover:bg-gray-200 transition">
                    キャンセル
                </a>
            </div>
        </form>
    </div>

    <x-slot:scripts>
        <script src="https://cdn.jsdelivr.net/npm/marked@12/marked.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/highlight.js@11/highlight.min.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const editor = document.getElementById('markdownEditor');
                const preview = document.getElementById('markdownPreview');
                const statusSelect = document.getElementById('statusSelect');
                const publishedAtWrapper = document.getElementById('publishedAtWrapper');

                // marked.js 設定
                marked.setOptions({
                    highlight: function(code, lang) {
                        if (lang && hljs.getLanguage(lang)) {
                            return hljs.highlight(code, { language: lang }).value;
                        }
                        return hljs.highlightAuto(code).value;
                    },
                    breaks: true,
                    gfm: true
                });

                const wordCountEl = document.getElementById('word-count');
                const readingTimeEl = document.getElementById('reading-time');

                function updateStats() {
                    const text = editor.value;
                    const charCount = text.replace(/\s/g, '').length;
                    const minutes = Math.max(1, Math.ceil(charCount / 500));
                    wordCountEl.textContent = charCount.toLocaleString() + ' 文字';
                    readingTimeEl.textContent = '約 ' + minutes + ' 分で読了';
                }

                // リアルタイムプレビュー
                function updatePreview() {
                    const md = editor.value;
                    if (md.trim()) {
                        preview.innerHTML = marked.parse(md);
                    } else {
                        preview.innerHTML = '<p class="text-gray-400">プレビューがここに表示されます</p>';
                    }
                    updateStats();
                }

                editor.addEventListener('input', updatePreview);

                updatePreview();

                // 位置情報プレビューバーの住所取得
                @if(request('lat') && request('lng'))
                (function() {
                    var previewBar = document.getElementById('location-preview-bar');
                    if (!previewBar) return;
                    var lat = {{ (float) request('lat') }};
                    var lng = {{ (float) request('lng') }};
                    var PREF_NAMES = [
                        '','北海道','青森県','岩手県','宮城県','秋田県','山形県','福島県',
                        '茨城県','栃木県','群馬県','埼玉県','千葉県','東京都','神奈川県',
                        '新潟県','富山県','石川県','福井県','山梨県','長野県','岐阜県',
                        '静岡県','愛知県','三重県','滋賀県','京都府','大阪府','兵庫県',
                        '奈良県','和歌山県','鳥取県','島根県','岡山県','広島県','山口県',
                        '徳島県','香川県','愛媛県','高知県','福岡県','佐賀県','長崎県',
                        '熊本県','大分県','宮崎県','鹿児島県','沖縄県'
                    ];
                    fetch('https://mreversegeocoder.gsi.go.jp/reverse-geocoder/LonLatToAddress?lat=' + lat + '&lon=' + lng)
                        .then(function(r) { return r.json(); })
                        .then(function(data) {
                            if (data && data.results && data.results.muniCd) {
                                var prefCode = parseInt(data.results.muniCd.substring(0, 2), 10);
                                var pref = PREF_NAMES[prefCode] || '';
                                var local = data.results.lv01Nm || '';
                                var address = pref + local;
                                if (address) {
                                    previewBar.querySelector('.location-address').textContent = address;
                                }
                            }
                        })
                        .catch(function() {});
                })();
                @endif

                // ステータスに応じて公開日時フィールドの表示切替
                function togglePublishedAt() {
                    publishedAtWrapper.style.display = statusSelect.value === 'draft' ? 'none' : 'block';
                }
                statusSelect.addEventListener('change', togglePublishedAt);
                togglePublishedAt();

                // シリーズ選択に応じて順番フィールドの表示切替
                const seriesSelect = document.getElementById('seriesSelect');
                const seriesOrderWrapper = document.getElementById('seriesOrderWrapper');
                const newSeriesLink = document.getElementById('newSeriesLink');
                function toggleSeriesOrder() {
                    const hasSeries = seriesSelect.value !== '';
                    seriesOrderWrapper.style.display = hasSeries ? 'block' : 'none';
                    newSeriesLink.style.display = hasSeries ? 'none' : 'inline-block';
                }
                seriesSelect.addEventListener('change', toggleSeriesOrder);
                toggleSeriesOrder();

                // === タグ入力 ===
                const tagContainer = document.getElementById('tagContainer');
                const tagInput = document.getElementById('tagInput');
                const tagSuggestions = document.getElementById('tagSuggestions');
                const tags = new Set();
                let suggestTimer = null;
                let activeSuggestion = -1;

                function addTag(name) {
                    name = name.trim();
                    if (!name || tags.has(name)) return;
                    tags.add(name);
                    renderTags();
                }

                function removeTag(name) {
                    tags.delete(name);
                    renderTags();
                }

                function renderTags() {
                    tagContainer.querySelectorAll('.tag-chip, input[name="tags[]"]').forEach(el => el.remove());
                    tags.forEach(name => {
                        const chip = document.createElement('span');
                        chip.className = 'tag-chip';
                        chip.innerHTML = `${escapeHtml(name)}<button type="button" data-tag="${escapeHtml(name)}">&times;</button>`;
                        tagContainer.insertBefore(chip, tagInput);

                        const hidden = document.createElement('input');
                        hidden.type = 'hidden';
                        hidden.name = 'tags[]';
                        hidden.value = name;
                        tagContainer.insertBefore(hidden, tagInput);
                    });
                }

                function escapeHtml(str) {
                    const d = document.createElement('div');
                    d.textContent = str;
                    return d.innerHTML;
                }

                tagContainer.addEventListener('click', (e) => {
                    if (e.target.closest('.tag-chip button')) {
                        removeTag(e.target.closest('button').dataset.tag);
                    } else {
                        tagInput.focus();
                    }
                });

                tagInput.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        const items = tagSuggestions.querySelectorAll('div');
                        if (activeSuggestion >= 0 && items[activeSuggestion]) {
                            addTag(items[activeSuggestion].textContent);
                        } else if (tagInput.value.trim()) {
                            addTag(tagInput.value);
                        }
                        tagInput.value = '';
                        tagSuggestions.classList.add('hidden');
                        activeSuggestion = -1;
                    } else if (e.key === 'Backspace' && !tagInput.value) {
                        const arr = [...tags];
                        if (arr.length) removeTag(arr[arr.length - 1]);
                    } else if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                        e.preventDefault();
                        const items = tagSuggestions.querySelectorAll('div');
                        if (!items.length) return;
                        activeSuggestion += e.key === 'ArrowDown' ? 1 : -1;
                        activeSuggestion = Math.max(-1, Math.min(activeSuggestion, items.length - 1));
                        items.forEach((item, i) => item.classList.toggle('active', i === activeSuggestion));
                    } else if (e.key === 'Escape') {
                        tagSuggestions.classList.add('hidden');
                        activeSuggestion = -1;
                    }
                });

                tagInput.addEventListener('input', () => {
                    clearTimeout(suggestTimer);
                    const q = tagInput.value.trim();
                    if (q.length < 1) { tagSuggestions.classList.add('hidden'); return; }
                    suggestTimer = setTimeout(async () => {
                        try {
                            const res = await fetch(`/api/blog/tags/suggest?q=${encodeURIComponent(q)}`);
                            const data = await res.json();
                            const filtered = data.filter(t => !tags.has(t.name));
                            if (!filtered.length) { tagSuggestions.classList.add('hidden'); return; }
                            tagSuggestions.innerHTML = filtered.map(t =>
                                `<div>${escapeHtml(t.name)}</div>`
                            ).join('');
                            tagSuggestions.classList.remove('hidden');
                            activeSuggestion = -1;
                        } catch { tagSuggestions.classList.add('hidden'); }
                    }, 200);
                });

                tagSuggestions.addEventListener('click', (e) => {
                    if (e.target.matches('div')) {
                        addTag(e.target.textContent);
                        tagInput.value = '';
                        tagSuggestions.classList.add('hidden');
                    }
                });

                document.addEventListener('click', (e) => {
                    if (!tagContainer.contains(e.target) && !tagSuggestions.contains(e.target)) {
                        tagSuggestions.classList.add('hidden');
                    }
                });

                // old() で復元
                @if(old('tags'))
                    @foreach(old('tags') as $tagName)
                        addTag(@json($tagName));
                    @endforeach
                @endif

                // === 画像アップロード ===
                let lastCursorPos = editor.value.length;
                editor.addEventListener('blur', () => { lastCursorPos = editor.selectionStart; });
                editor.addEventListener('focus', () => { lastCursorPos = editor.selectionStart; });
                editor.addEventListener('click', () => { lastCursorPos = editor.selectionStart; });
                editor.addEventListener('keyup', () => { lastCursorPos = editor.selectionStart; });

                // ツールバーの画像ボタン
                const imageUploadBtn = document.getElementById('imageUploadBtn');
                const imageFileInput = document.getElementById('imageFileInput');
                imageUploadBtn.addEventListener('click', () => imageFileInput.click());
                imageFileInput.addEventListener('change', async () => {
                    for (const file of imageFileInput.files) {
                        if (file.type.startsWith('image/')) await uploadImage(file);
                    }
                    imageFileInput.value = '';
                });

                // ドラッグ＆ドロップ
                editor.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    editor.classList.add('drop-zone-active');
                });
                editor.addEventListener('dragleave', () => { editor.classList.remove('drop-zone-active'); });
                editor.addEventListener('drop', async (e) => {
                    e.preventDefault();
                    editor.classList.remove('drop-zone-active');
                    for (const file of e.dataTransfer.files) {
                        if (file.type.startsWith('image/')) await uploadImage(file);
                    }
                });

                // ペースト
                editor.addEventListener('paste', async (e) => {
                    const items = e.clipboardData?.items;
                    if (!items) return;
                    for (const item of items) {
                        if (item.type.startsWith('image/')) {
                            e.preventDefault();
                            await uploadImage(item.getAsFile());
                        }
                    }
                });

                async function uploadImage(file) {
                    const formData = new FormData();
                    formData.append('image', file);

                    const pos = (document.activeElement === editor) ? editor.selectionStart : lastCursorPos;
                    const placeholder = '![アップロード中...]()';
                    editor.focus();
                    editor.setRangeText(placeholder, pos, pos, 'end');
                    lastCursorPos = pos + placeholder.length;
                    updatePreview();

                    try {
                        const res = await fetch('{{ route("admin.blog.upload-image") }}', {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                            body: formData
                        });
                        const data = await res.json();
                        const markdown = `![](${data.url})`;
                        editor.value = editor.value.replace(placeholder, markdown);
                        lastCursorPos = pos + markdown.length;
                        updatePreview();
                    } catch (err) {
                        editor.value = editor.value.replace(placeholder, '');
                        lastCursorPos = pos;
                        alert('画像のアップロードに失敗しました。');
                    }
                }
            });
        </script>
    </x-slot:scripts>
</x-layout>
