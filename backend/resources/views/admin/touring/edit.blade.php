<x-layout>
    <x-slot:title>ツーリングガイド編集 | MotoHub</x-slot:title>

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
        </style>
    </x-slot:styles>

    <div class="max-w-7xl mx-auto px-4 py-8">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold">ツーリングガイド編集</h1>
            <a href="{{ route('admin.touring.index') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; ガイド一覧に戻る</a>
        </div>

        @if(session('success'))
            <div class="mb-4 p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg">
                {{ session('success') }}
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

        <form method="POST" action="{{ route('admin.touring.update', $guide->id) }}" id="guideForm">
            @csrf
            @method('PUT')

            {{-- タイトル --}}
            <div class="mb-4">
                <input type="text" name="title" value="{{ old('title', $guide->title) }}"
                       placeholder="ツーリングガイドのタイトル"
                       class="w-full text-2xl font-bold border-0 border-b-2 border-gray-200 focus:border-blue-500 focus:ring-0 px-0 py-2"
                       required>
            </div>

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
                        <textarea name="body" id="markdownEditor" placeholder="Markdownでツーリングガイドを書く..." required>{{ old('body', $guide->body) }}</textarea>
                    </div>
                    <div class="preview-pane" id="markdownPreview">
                        <p class="text-gray-400">プレビューがここに表示されます</p>
                    </div>
                </div>
            </div>

            {{-- ルート情報 --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1">都道府県</label>
                    <select name="prefecture" class="w-full rounded-lg border-gray-300 text-sm" required>
                        <option value="">選択してください</option>
                        @foreach(['北海道','青森県','岩手県','宮城県','秋田県','山形県','福島県','茨城県','栃木県','群馬県','埼玉県','千葉県','東京都','神奈川県','新潟県','富山県','石川県','福井県','山梨県','長野県','岐阜県','静岡県','愛知県','三重県','滋賀県','京都府','大阪府','兵庫県','奈良県','和歌山県','鳥取県','島根県','岡山県','広島県','山口県','徳島県','香川県','愛媛県','高知県','福岡県','佐賀県','長崎県','熊本県','大分県','宮崎県','鹿児島県','沖縄県'] as $pref)
                            <option value="{{ $pref }}" @selected(old('prefecture', $guide->prefecture) === $pref)>{{ $pref }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1">難易度</label>
                    <select name="difficulty" class="w-full rounded-lg border-gray-300 text-sm" required>
                        <option value="初級" @selected(old('difficulty', $guide->difficulty) === '初級')>初級</option>
                        <option value="中級" @selected(old('difficulty', $guide->difficulty) === '中級')>中級</option>
                        <option value="上級" @selected(old('difficulty', $guide->difficulty) === '上級')>上級</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1">距離（km）</label>
                    <input type="number" name="distance_km" value="{{ old('distance_km', $guide->distance_km) }}" min="0"
                           class="w-full rounded-lg border-gray-300 text-sm" placeholder="60">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1">所要時間</label>
                    <input type="text" name="duration_text" value="{{ old('duration_text', $guide->duration_text) }}" maxlength="50"
                           class="w-full rounded-lg border-gray-300 text-sm" placeholder="3〜4時間">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1">おすすめ時期</label>
                    <input type="text" name="best_season" value="{{ old('best_season', $guide->best_season) }}" maxlength="100"
                           class="w-full rounded-lg border-gray-300 text-sm" placeholder="5月〜6月/10月〜11月">
                </div>
            </div>

            {{-- 紹介文 --}}
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-600 mb-1">紹介文（空欄で自動生成）</label>
                <textarea name="excerpt" rows="3" class="w-full rounded-lg border-gray-300 text-sm">{{ old('excerpt', $guide->excerpt) }}</textarea>
            </div>

            {{-- 位置情報 --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1">緯度</label>
                    <input type="number" name="latitude" value="{{ old('latitude', $guide->latitude) }}" step="0.0000001" min="-90" max="90"
                           class="w-full rounded-lg border-gray-300 text-sm" placeholder="35.6812362" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1">経度</label>
                    <input type="number" name="longitude" value="{{ old('longitude', $guide->longitude) }}" step="0.0000001" min="-180" max="180"
                           class="w-full rounded-lg border-gray-300 text-sm" placeholder="139.7671248" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1">ズームレベル</label>
                    <input type="number" name="zoom_level" value="{{ old('zoom_level', $guide->zoom_level) }}" min="1" max="18"
                           class="w-full rounded-lg border-gray-300 text-sm" placeholder="12">
                </div>
            </div>

            {{-- ステータス --}}
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-600 mb-1">ステータス</label>
                <select name="status" class="w-full rounded-lg border-gray-300 text-sm max-w-xs">
                    <option value="draft" @selected(old('status', $guide->status)==='draft')>下書き</option>
                    <option value="published" @selected(old('status', $guide->status)==='published')>公開</option>
                </select>
            </div>

            {{-- 送信ボタン --}}
            <div class="flex gap-4">
                <button type="submit" class="px-6 py-3 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition">
                    ガイドを更新
                </button>
                <a href="{{ route('admin.touring.index') }}" class="px-6 py-3 bg-gray-100 text-gray-700 font-medium rounded-lg hover:bg-gray-200 transition">
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

                // === 画像アップロード ===
                let lastCursorPos = editor.value.length;
                editor.addEventListener('blur', () => { lastCursorPos = editor.selectionStart; });
                editor.addEventListener('focus', () => { lastCursorPos = editor.selectionStart; });
                editor.addEventListener('click', () => { lastCursorPos = editor.selectionStart; });
                editor.addEventListener('keyup', () => { lastCursorPos = editor.selectionStart; });

                const imageUploadBtn = document.getElementById('imageUploadBtn');
                const imageFileInput = document.getElementById('imageFileInput');
                imageUploadBtn.addEventListener('click', () => imageFileInput.click());
                imageFileInput.addEventListener('change', async () => {
                    for (const file of imageFileInput.files) {
                        if (file.type.startsWith('image/')) await uploadImage(file);
                    }
                    imageFileInput.value = '';
                });

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
