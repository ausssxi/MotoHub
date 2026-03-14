<x-layout>
    <x-slot:title>このバイクなに？ 車種判定AI | MotoHub</x-slot:title>
    <x-slot:metaDescription>バイクの写真を送るだけで車種を瞬時に判定。中古相場や在庫もすぐ確認できます。</x-slot:metaDescription>

    <x-slot:navigation>
        <x-navigation :showSearch="true" />
    </x-slot:navigation>

    <div class="bg-gray-50 min-h-screen py-8">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">

            {{-- パンくず --}}
            <nav class="flex text-xs font-bold text-gray-400 mb-6" aria-label="Breadcrumb">
                <ol class="flex items-center space-x-2">
                    <li><a href="/" class="hover:text-gray-600 transition-colors">HOME</a></li>
                    <li><span class="text-gray-300">＞</span></li>
                    <li><span class="text-gray-800">車種判定AI</span></li>
                </ol>
            </nav>

            {{-- ヘッダー --}}
            <div class="text-center mb-8">
                <div class="inline-flex items-center gap-2 bg-blue-100 text-blue-700 text-[10px] font-bold px-3 py-1 rounded-full mb-4">
                    <i data-lucide="sparkles" class="w-3 h-3"></i>
                    AI Powered
                </div>
                <h1 class="text-3xl sm:text-4xl font-black text-gray-900 mb-2">このバイクなに？</h1>
                <p class="text-sm text-gray-500 font-bold">写真をアップロードするだけで、AIが車種を判定します</p>
            </div>

            {{-- アップロードエリア --}}
            <div id="upload-area"
                 class="bg-white rounded-3xl shadow-sm border-2 border-dashed border-gray-200 hover:border-blue-400 p-8 sm:p-12 text-center transition-colors duration-200 cursor-pointer relative"
                 ondragover="event.preventDefault(); this.classList.add('border-blue-400', 'bg-blue-50')"
                 ondragleave="this.classList.remove('border-blue-400', 'bg-blue-50')"
                 ondrop="handleDrop(event)"
                 onclick="document.getElementById('file-input').click()">

                <input type="file" id="file-input" accept="image/*" class="hidden" onchange="handleFile(this.files[0])">
                <input type="file" id="camera-input" accept="image/*" capture="environment" class="hidden" onchange="handleFile(this.files[0])">

                <div id="upload-placeholder">
                    <div class="w-16 h-16 bg-gray-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                        <i data-lucide="camera" class="w-8 h-8 text-gray-400"></i>
                    </div>
                    <p class="text-sm font-black text-gray-700 mb-1">写真をドラッグ&ドロップ</p>
                    <p class="text-xs text-gray-400">またはクリックしてファイルを選択</p>

                    {{-- モバイル用カメラ起動ボタン --}}
                    <button id="camera-btn" type="button" onclick="event.stopPropagation(); document.getElementById('camera-input').click();"
                            class="hidden mt-4 inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-black text-sm px-5 py-3 rounded-2xl transition-colors shadow-md">
                        <i data-lucide="camera" class="w-5 h-5"></i>
                        カメラで撮影
                    </button>

                    <p class="text-[10px] text-gray-300 mt-2">JPEG / PNG / WebP 対応（最大10MB）</p>
                </div>

                <div id="upload-preview" class="hidden">
                    <img id="preview-image" class="max-h-64 mx-auto rounded-2xl shadow-sm" alt="アップロード画像">
                </div>
            </div>

            {{-- 判定ボタン --}}
            <div id="identify-btn-area" class="hidden mt-6">
                <button id="identify-btn" onclick="submitIdentify()"
                        class="w-full bg-gray-900 hover:bg-gray-800 text-white font-black py-4 rounded-2xl transition-colors text-sm flex items-center justify-center gap-2 shadow-lg">
                    <i data-lucide="sparkles" class="w-5 h-5"></i>
                    AIで車種を判定する
                </button>
            </div>

            {{-- ローディング --}}
            <div id="loading-area" class="hidden mt-8 text-center">
                <div class="inline-flex items-center gap-3 bg-white rounded-2xl shadow-sm border border-gray-100 px-6 py-4">
                    <i data-lucide="loader-2" class="w-5 h-5 animate-spin text-blue-600"></i>
                    <span class="text-sm font-bold text-gray-700">AIが画像を分析しています...</span>
                </div>
            </div>

            {{-- エラー --}}
            <div id="error-area" class="hidden mt-8">
                <div class="bg-red-50 border border-red-200 rounded-2xl p-6 text-center">
                    <i data-lucide="alert-circle" class="w-8 h-8 text-red-400 mx-auto mb-2"></i>
                    <p id="error-message" class="text-sm font-bold text-red-600"></p>
                    <button onclick="resetUI()" class="mt-4 text-xs font-bold text-red-500 hover:text-red-700 underline">もう一度試す</button>
                </div>
            </div>

            {{-- 判定結果 --}}
            <div id="result-area" class="hidden mt-8 space-y-6">
                {{-- メインカード --}}
                <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8">
                    <div class="flex items-center gap-2 mb-4">
                        <span class="bg-green-100 text-green-700 text-[10px] font-bold px-2.5 py-1 rounded-full flex items-center gap-1">
                            <i data-lucide="check-circle" class="w-3 h-3"></i>
                            判定完了
                        </span>
                        <span id="result-confidence" class="text-[10px] font-bold px-2.5 py-1 rounded-full"></span>
                    </div>

                    <h2 id="result-model" class="text-2xl sm:text-3xl font-black text-gray-900 mb-1"></h2>
                    <p id="result-maker" class="text-sm font-bold text-gray-500 mb-4"></p>

                    <div class="grid grid-cols-3 gap-3 mb-6">
                        <div class="bg-gray-50 rounded-xl p-3 text-center">
                            <p class="text-[10px] font-bold text-gray-400">推定年式</p>
                            <p id="result-year" class="text-sm font-black text-gray-800"></p>
                        </div>
                        <div class="bg-gray-50 rounded-xl p-3 text-center">
                            <p class="text-[10px] font-bold text-gray-400">排気量</p>
                            <p id="result-displacement" class="text-sm font-black text-gray-800"></p>
                        </div>
                        <div class="bg-gray-50 rounded-xl p-3 text-center">
                            <p class="text-[10px] font-bold text-gray-400">カテゴリ</p>
                            <p id="result-category" class="text-sm font-black text-gray-800"></p>
                        </div>
                    </div>

                    {{-- 特徴タグ --}}
                    <div id="result-features" class="flex flex-wrap gap-2 mb-6"></div>

                    {{-- AIコメント --}}
                    <div id="result-comment-area" class="bg-blue-50 rounded-xl p-4 border border-blue-100">
                        <p class="text-xs font-bold text-blue-700 flex items-center gap-1 mb-1">
                            <i data-lucide="message-circle" class="w-3 h-3"></i>
                            AIコメント
                        </p>
                        <p id="result-comment" class="text-sm text-blue-900"></p>
                    </div>
                </div>

                {{-- アクションボタン --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <a id="link-search" href="#"
                       class="flex items-center justify-center gap-2 bg-gray-900 hover:bg-gray-800 text-white font-black py-4 rounded-2xl transition-colors text-sm shadow-lg">
                        <i data-lucide="search" class="w-5 h-5"></i>
                        MotoHubで在庫を探す
                    </a>
                    <a id="link-models" href="#"
                       class="flex items-center justify-center gap-2 bg-white hover:bg-gray-50 text-gray-900 font-black py-4 rounded-2xl transition-colors text-sm border-2 border-gray-200 hover:border-gray-400">
                        <i data-lucide="bar-chart-2" class="w-5 h-5"></i>
                        相場を見る
                    </a>
                </div>

                {{-- 再判定 --}}
                <div class="text-center">
                    <button onclick="resetUI()" class="text-xs font-bold text-gray-400 hover:text-gray-600 underline">別の写真で判定する</button>
                </div>
            </div>

            {{-- 使い方説明 --}}
            <div class="mt-16 bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8">
                <h2 class="text-lg font-black text-gray-900 mb-6 text-center">使い方</h2>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                    <div class="text-center">
                        <div class="w-12 h-12 bg-blue-50 rounded-2xl flex items-center justify-center mx-auto mb-3">
                            <i data-lucide="upload" class="w-6 h-6 text-blue-600"></i>
                        </div>
                        <p class="text-sm font-black text-gray-800 mb-1">1. 写真をアップ</p>
                        <p class="text-xs text-gray-400">気になるバイクの写真を送信</p>
                    </div>
                    <div class="text-center">
                        <div class="w-12 h-12 bg-purple-50 rounded-2xl flex items-center justify-center mx-auto mb-3">
                            <i data-lucide="cpu" class="w-6 h-6 text-purple-600"></i>
                        </div>
                        <p class="text-sm font-black text-gray-800 mb-1">2. AIが判定</p>
                        <p class="text-xs text-gray-400">車種・年式・排気量を分析</p>
                    </div>
                    <div class="text-center">
                        <div class="w-12 h-12 bg-green-50 rounded-2xl flex items-center justify-center mx-auto mb-3">
                            <i data-lucide="shopping-bag" class="w-6 h-6 text-green-600"></i>
                        </div>
                        <p class="text-sm font-black text-gray-800 mb-1">3. 在庫・相場を確認</p>
                        <p class="text-xs text-gray-400">MotoHubですぐ検索できます</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <x-slot:scripts>
    <script>
        var selectedFile = null;

        // モバイル判定：カメラボタン表示
        (function() {
            var ua = navigator.userAgent || '';
            var isMobile = /iPhone|iPad|iPod|Android/i.test(ua);
            if (isMobile) {
                var btn = document.getElementById('camera-btn');
                if (btn) btn.classList.remove('hidden');
            }
        })();

        function handleDrop(e) {
            e.preventDefault();
            var area = document.getElementById('upload-area');
            area.classList.remove('border-blue-400', 'bg-blue-50');
            var file = e.dataTransfer.files[0];
            if (file && file.type.startsWith('image/')) {
                handleFile(file);
            }
        }

        function handleFile(file) {
            if (!file) return;
            selectedFile = file;

            var reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('preview-image').src = e.target.result;
                document.getElementById('upload-placeholder').classList.add('hidden');
                document.getElementById('upload-preview').classList.remove('hidden');
                document.getElementById('identify-btn-area').classList.remove('hidden');

                // 結果やエラーをリセット
                document.getElementById('result-area').classList.add('hidden');
                document.getElementById('error-area').classList.add('hidden');
            };
            reader.readAsDataURL(file);
        }

        // Canvas を使って画像をリサイズ・圧縮
        function compressImage(file, callback) {
            var canvas = document.createElement('canvas');
            var img = new Image();
            img.onload = function() {
                var maxSize = 1024;
                var w = img.width, h = img.height;
                if (w > h && w > maxSize) { h = h * maxSize / w; w = maxSize; }
                else if (h > maxSize) { w = w * maxSize / h; h = maxSize; }
                canvas.width = w;
                canvas.height = h;
                canvas.getContext('2d').drawImage(img, 0, 0, w, h);
                canvas.toBlob(function(blob) {
                    callback(blob);
                }, 'image/jpeg', 0.8);
            };
            img.src = URL.createObjectURL(file);
        }

        function submitIdentify() {
            if (!selectedFile) return;

            var btn = document.getElementById('identify-btn');
            btn.disabled = true;
            document.getElementById('identify-btn-area').classList.add('hidden');
            document.getElementById('loading-area').classList.remove('hidden');
            document.getElementById('error-area').classList.add('hidden');
            document.getElementById('result-area').classList.add('hidden');

            compressImage(selectedFile, function(blob) {
                var formData = new FormData();
                formData.append('image', blob, 'photo.jpg');

                fetch('{{ route("bikes.identify.post") }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    },
                    body: formData
                })
                .then(function(r) { return r.json().then(function(data) { return { ok: r.ok, data: data }; }); })
                .then(function(res) {
                    document.getElementById('loading-area').classList.add('hidden');
                    btn.disabled = false;

                    if (!res.ok || res.data.error) {
                        showError(res.data.error || 'AI判定に失敗しました。');
                        return;
                    }
                    showResult(res.data);
                })
                .catch(function() {
                    document.getElementById('loading-area').classList.add('hidden');
                    btn.disabled = false;
                    showError('通信エラーが発生しました。');
                });
            });
        }

        function showError(msg) {
            document.getElementById('error-message').textContent = msg;
            document.getElementById('error-area').classList.remove('hidden');
            document.getElementById('identify-btn-area').classList.remove('hidden');
        }

        function showResult(data) {
            document.getElementById('result-model').textContent = data.model || '不明';
            document.getElementById('result-maker').textContent = (data.maker_jp || '') + (data.maker ? ' (' + data.maker + ')' : '');
            document.getElementById('result-year').textContent = data.year || '-';
            document.getElementById('result-displacement').textContent = data.displacement || '-';
            document.getElementById('result-category').textContent = data.category || '-';

            // 確度バッジ
            var conf = document.getElementById('result-confidence');
            var level = data.confidence || '中';
            conf.textContent = '確度: ' + level;
            conf.className = 'text-[10px] font-bold px-2.5 py-1 rounded-full ';
            if (level === '高') conf.className += 'bg-green-100 text-green-700';
            else if (level === '低') conf.className += 'bg-red-100 text-red-700';
            else conf.className += 'bg-yellow-100 text-yellow-700';

            // 特徴タグ
            var featuresEl = document.getElementById('result-features');
            featuresEl.innerHTML = '';
            (data.features || []).forEach(function(f) {
                var tag = document.createElement('span');
                tag.className = 'inline-flex items-center gap-1 bg-gray-100 text-gray-700 text-xs font-bold px-3 py-1.5 rounded-full';
                tag.textContent = f;
                featuresEl.appendChild(tag);
            });

            // コメント
            var comment = data.comment || '';
            document.getElementById('result-comment').textContent = comment;
            document.getElementById('result-comment-area').classList.toggle('hidden', !comment);

            // リンク生成
            var modelName = data.model || '';
            document.getElementById('link-search').href = '/bikes/search?keyword=' + encodeURIComponent(modelName);
            document.getElementById('link-models').href = '/bikes/models';

            document.getElementById('result-area').classList.remove('hidden');

            // Lucideアイコン再描画
            if (typeof lucide !== 'undefined') lucide.createIcons();
        }

        function resetUI() {
            selectedFile = null;
            document.getElementById('file-input').value = '';
            document.getElementById('camera-input').value = '';
            document.getElementById('upload-placeholder').classList.remove('hidden');
            document.getElementById('upload-preview').classList.add('hidden');
            document.getElementById('identify-btn-area').classList.add('hidden');
            document.getElementById('loading-area').classList.add('hidden');
            document.getElementById('error-area').classList.add('hidden');
            document.getElementById('result-area').classList.add('hidden');
        }
    </script>
    </x-slot:scripts>
</x-layout>
