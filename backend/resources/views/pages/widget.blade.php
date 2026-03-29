<x-layout>
    <x-slot:title>相場ウィジェット - あなたのブログに貼れる中古バイク相場 | MotoHub</x-slot:title>
    <x-slot:metaDescription>MotoHubの中古バイク相場ウィジェットを無料であなたのブログやWebサイトに埋め込めます。リアルタイムの価格情報を自動更新で表示。</x-slot:metaDescription>

    <x-slot:navigation>
        <x-navigation :showSearch="true" />
    </x-slot:navigation>

    <div class="bg-gray-50 min-h-screen py-12 sm:py-20">
        <div class="max-w-4xl mx-auto px-4">

            {{-- ヘッダー --}}
            <div class="text-center mb-12">
                <div class="inline-flex items-center gap-2 bg-blue-100 text-blue-700 text-xs font-bold px-3 py-1 rounded-full mb-4">
                    <i data-lucide="code" class="w-3 h-3"></i> 無料で使える
                </div>
                <h1 class="text-3xl sm:text-4xl font-black text-gray-900 mb-3">
                    中古バイク相場ウィジェット
                </h1>
                <p class="text-gray-500 text-sm font-bold max-w-xl mx-auto">
                    あなたのブログやWebサイトに、リアルタイムの中古バイク相場情報を埋め込めます。<br>
                    コピペするだけ、無料、自動更新。
                </p>
            </div>

            {{-- こんな方におすすめ --}}
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 mb-8">
                <h2 class="text-sm font-black text-gray-900 mb-3">💡 こんな方におすすめ</h2>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-center">
                    <div class="p-4 bg-gray-50 rounded-xl">
                        <span class="text-2xl block mb-2">📝</span>
                        <p class="text-xs font-bold text-gray-700">バイクブログを書いている方</p>
                        <p class="text-[10px] text-gray-500 mt-1">レビュー記事に相場情報を添えて説得力UP</p>
                    </div>
                    <div class="p-4 bg-gray-50 rounded-xl">
                        <span class="text-2xl block mb-2">🏍️</span>
                        <p class="text-xs font-bold text-gray-700">バイクショップのサイト運営者</p>
                        <p class="text-[10px] text-gray-500 mt-1">取扱車種の相場をお客様に提示</p>
                    </div>
                    <div class="p-4 bg-gray-50 rounded-xl">
                        <span class="text-2xl block mb-2">💰</span>
                        <p class="text-xs font-bold text-gray-700">売却を検討中の方</p>
                        <p class="text-[10px] text-gray-500 mt-1">SNSやブログで相場を共有</p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">

                {{-- 左: 設定パネル --}}
                <div class="space-y-6">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <h2 class="text-lg font-black text-gray-900 mb-4 flex items-center gap-2">
                            <i data-lucide="settings" class="w-5 h-5 text-gray-400"></i>
                            ウィジェットの設定
                        </h2>

                        {{-- 車種選択 --}}
                        <div class="mb-4">
                            <label class="block text-xs font-bold text-gray-500 mb-1">車種を選択</label>
                            <div class="flex gap-2">
                                <input type="text" id="widget-search" placeholder="例: CB400SF, Ninja 250..."
                                       class="flex-1 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none"
                                       autocomplete="off">
                            </div>
                            <div id="widget-search-results" class="mt-1 bg-white border border-gray-200 rounded-lg shadow-lg hidden max-h-48 overflow-y-auto"></div>
                            <input type="hidden" id="widget-model-id" value="">
                        </div>

                        {{-- テーマ選択 --}}
                        <div class="mb-4">
                            <label class="block text-xs font-bold text-gray-500 mb-2">テーマ</label>
                            <div class="flex gap-3">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="widget-theme" value="light" checked class="accent-blue-600">
                                    <span class="text-sm font-bold text-gray-700">ライト</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="widget-theme" value="dark" class="accent-blue-600">
                                    <span class="text-sm font-bold text-gray-700">ダーク</span>
                                </label>
                            </div>
                        </div>

                        {{-- 買取相場表示 --}}
                        <div class="mb-6">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" id="widget-show-resale" checked class="accent-blue-600">
                                <span class="text-sm font-bold text-gray-700">買取相場も表示する</span>
                            </label>
                        </div>

                        {{-- 生成ボタン --}}
                        <button onclick="generateCode()" id="generate-btn" disabled
                                class="w-full bg-blue-600 hover:bg-blue-700 disabled:bg-gray-300 disabled:cursor-not-allowed text-white font-bold py-3 rounded-xl transition-colors shadow-md">
                            埋め込みコードを生成する
                        </button>
                    </div>

                    {{-- 埋め込みコード --}}
                    <div id="embed-code-section" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 hidden">
                        <h2 class="text-lg font-black text-gray-900 mb-4 flex items-center gap-2">
                            <i data-lucide="clipboard" class="w-5 h-5 text-gray-400"></i>
                            埋め込みコード
                        </h2>
                        <p class="text-xs text-gray-500 mb-3">以下のコードをあなたのブログ記事のHTML内に貼り付けてください。</p>
                        <div class="relative">
                            <pre id="embed-code" class="bg-gray-900 text-green-400 text-xs p-4 rounded-xl overflow-x-auto whitespace-pre-wrap break-all"></pre>
                            <button onclick="copyCode()" class="absolute top-2 right-2 bg-gray-700 hover:bg-gray-600 text-white text-[10px] font-bold px-3 py-1.5 rounded-lg transition-colors">
                                コピー
                            </button>
                        </div>
                        <p class="text-[10px] text-gray-400 mt-2">※ WordPressの場合は「カスタムHTML」ブロックに貼り付けてください。</p>
                    </div>
                </div>

                {{-- 右: プレビュー --}}
                <div>
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 sticky top-6">
                        <h2 class="text-lg font-black text-gray-900 mb-4 flex items-center gap-2">
                            <i data-lucide="eye" class="w-5 h-5 text-gray-400"></i>
                            プレビュー
                        </h2>
                        <div id="widget-demo-label" class="text-[10px] text-amber-600 font-bold bg-amber-50 border border-amber-200 rounded-lg px-3 py-1.5 mb-3">
                            📌 デモ表示中 — 車種を選ぶとあなたのウィジェットに切り替わります
                        </div>
                        <div id="widget-preview" class="min-h-[200px] flex items-center justify-center">
                            <div class="text-center text-gray-400">
                                <i data-lucide="loader-2" class="w-6 h-6 mx-auto mb-2 text-gray-300 animate-spin"></i>
                                <p class="text-xs font-bold">デモを読み込み中...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- 使い方ガイド --}}
            <div class="mt-16 bg-white rounded-3xl shadow-sm border border-gray-100 p-8">
                <h2 class="text-xl font-black text-gray-900 mb-8 text-center">使い方はかんたん3ステップ</h2>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-8">
                    <div class="text-center">
                        <div class="w-12 h-12 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center mx-auto mb-3 font-black text-lg">1</div>
                        <h3 class="font-black text-gray-900 mb-1">車種を選ぶ</h3>
                        <p class="text-xs text-gray-500">表示したい車種名を検索して選択</p>
                    </div>
                    <div class="text-center">
                        <div class="w-12 h-12 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center mx-auto mb-3 font-black text-lg">2</div>
                        <h3 class="font-black text-gray-900 mb-1">コードをコピー</h3>
                        <p class="text-xs text-gray-500">生成された埋め込みコードをコピー</p>
                    </div>
                    <div class="text-center">
                        <div class="w-12 h-12 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center mx-auto mb-3 font-black text-lg">3</div>
                        <h3 class="font-black text-gray-900 mb-1">ブログに貼る</h3>
                        <p class="text-xs text-gray-500">HTMLに貼り付けるだけで相場表示</p>
                    </div>
                </div>
            </div>

            {{-- 特徴 --}}
            <div class="mt-8 grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white rounded-xl p-5 border border-gray-100 shadow-sm">
                    <div class="flex items-center gap-2 mb-2">
                        <i data-lucide="zap" class="w-4 h-4 text-yellow-500"></i>
                        <h3 class="font-black text-gray-900 text-sm">完全無料</h3>
                    </div>
                    <p class="text-xs text-gray-500">利用料金は一切かかりません。個人ブログから法人メディアまで自由にお使いください。</p>
                </div>
                <div class="bg-white rounded-xl p-5 border border-gray-100 shadow-sm">
                    <div class="flex items-center gap-2 mb-2">
                        <i data-lucide="refresh-cw" class="w-4 h-4 text-green-500"></i>
                        <h3 class="font-black text-gray-900 text-sm">自動更新</h3>
                    </div>
                    <p class="text-xs text-gray-500">相場データはMotoHubのデータベースから自動で最新情報に更新されます。</p>
                </div>
                <div class="bg-white rounded-xl p-5 border border-gray-100 shadow-sm">
                    <div class="flex items-center gap-2 mb-2">
                        <i data-lucide="palette" class="w-4 h-4 text-purple-500"></i>
                        <h3 class="font-black text-gray-900 text-sm">カスタマイズ</h3>
                    </div>
                    <p class="text-xs text-gray-500">ライト/ダークテーマに対応。あなたのサイトデザインに馴染みます。</p>
                </div>
            </div>
        </div>
    </div>

    <x-slot:scripts>
        <script>
        // 車種検索（デバウンス付き）
        let searchTimer = null;
        const searchInput = document.getElementById('widget-search');
        const searchResults = document.getElementById('widget-search-results');
        const modelIdInput = document.getElementById('widget-model-id');
        const generateBtn = document.getElementById('generate-btn');

        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimer);
            const q = this.value.trim();
            if (q.length < 1) {
                searchResults.classList.add('hidden');
                return;
            }
            searchTimer = setTimeout(() => {
                fetch('/bikes/suggest?keyword=' + encodeURIComponent(q))
                    .then(r => r.json())
                    .then(data => {
                        var models = data.models || [];
                        if (!models.length) {
                            searchResults.classList.add('hidden');
                            return;
                        }
                        searchResults.innerHTML = models.map(item =>
                            `<button type="button" class="w-full text-left px-3 py-2 hover:bg-blue-50 text-sm font-bold text-gray-700 border-b border-gray-50 last:border-0 transition-colors"
                                onclick="selectModel(${item.id}, '${escapeAttr((item.manufacturer_name || '') + ' ' + item.name)}')">${item.manufacturer_name || ''} <span class="text-blue-600">${item.name}</span></button>`
                        ).join('');
                        searchResults.classList.remove('hidden');
                    });
            }, 300);
        });

        // クリック外で閉じる
        document.addEventListener('click', function(e) {
            if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
                searchResults.classList.add('hidden');
            }
        });

        function selectModel(id, name) {
            modelIdInput.value = id;
            searchInput.value = name;
            searchResults.classList.add('hidden');
            generateBtn.disabled = false;
            // デモラベルを消す
            var demoLabel = document.getElementById('widget-demo-label');
            if (demoLabel) demoLabel.remove();
            // プレビュー自動更新
            generateCode();
        }

        function generateCode() {
            const modelId = modelIdInput.value;
            if (!modelId) return;

            const theme = document.querySelector('input[name="widget-theme"]:checked').value;
            const showResale = document.getElementById('widget-show-resale').checked;

            // 埋め込みコード生成
            let code = `<!-- MotoHub 相場ウィジェット -->\n`;
            code += `<div id="motohub-price" data-model-id="${modelId}"`;
            if (theme !== 'light') code += ` data-theme="${theme}"`;
            if (!showResale) code += ` data-show-resale="false"`;
            code += `></div>\n`;
            code += `<script src="https://www.motohub.jp/widget/price.js" async><\/script>`;

            // コード表示
            document.getElementById('embed-code').textContent = code;
            document.getElementById('embed-code-section').classList.remove('hidden');

            // プレビュー（price.jsを読み込み直す代わりに、直接APIから描画）
            const preview = document.getElementById('widget-preview');
            preview.innerHTML = '<div style="text-align:center;padding:20px;color:#999;font-size:12px;">読み込み中...</div>';

            fetch('/api/widget/price/' + modelId)
                .then(r => r.json())
                .then(data => {
                    preview.innerHTML = renderPreview(data, theme, showResale);
                })
                .catch(() => {
                    preview.innerHTML = '<div style="text-align:center;padding:20px;color:#999;">プレビューを取得できませんでした</div>';
                });
        }

        function copyCode() {
            const code = document.getElementById('embed-code').textContent;
            navigator.clipboard.writeText(code).then(() => {
                const btn = event.target;
                const original = btn.textContent;
                btn.textContent = 'コピーしました！';
                setTimeout(() => btn.textContent = original, 2000);
            });
        }

        function escapeAttr(str) {
            return str.replace(/'/g, "\\'").replace(/"/g, '&quot;');
        }

        // テーマ変更時に自動プレビュー更新
        document.querySelectorAll('input[name="widget-theme"]').forEach(el => {
            el.addEventListener('change', () => { if (modelIdInput.value) generateCode(); });
        });
        document.getElementById('widget-show-resale').addEventListener('change', () => {
            if (modelIdInput.value) generateCode();
        });

        function renderPreview(data, theme, showResale) {
            var isDark = theme === 'dark';
            var bg = isDark ? '#1a1a2e' : '#ffffff';
            var text = isDark ? '#e0e0e0' : '#1f2937';
            var subtext = isDark ? '#9ca3af' : '#6b7280';
            var border = isDark ? '#2d2d44' : '#e5e7eb';
            var accent = '#2563eb';
            var priceColor = isDark ? '#fbbf24' : '#d97706';
            var cardBg = isDark ? '#16162a' : '#f9fafb';
            var hasStats = data.stats && data.stats.count > 0 && data.stats.avg;
            var hasResale = showResale && data.resale && data.resale.data_count > 0 && data.resale.min;

            var html = '<div style="font-family:-apple-system,BlinkMacSystemFont,sans-serif;max-width:400px;background:'+bg+';border:1px solid '+border+';border-radius:16px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.08);">';
    
            // ヘッダー
            html += '<div style="padding:16px 20px;border-bottom:1px solid '+border+';">';
            html += '<div style="font-size:10px;font-weight:700;color:'+accent+';text-transform:uppercase;letter-spacing:0.05em;margin-bottom:4px;">'+escapeHtml(data.manufacturer_name)+'</div>';
            html += '<div style="font-size:18px;font-weight:900;color:'+text+';line-height:1.2;">'+escapeHtml(data.model_name)+'</div>';
            if (data.displacement) html += '<div style="font-size:11px;color:'+subtext+';margin-top:2px;">'+data.displacement+'cc</div>';
            html += '</div>';

            // 価格
            html += '<div style="padding:16px 20px;">';
            if (hasStats) {
                html += '<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-bottom:12px;">';
                html += pBox('平均', data.stats.avg, cardBg, text, subtext);
                html += pBox('最安', data.stats.min, cardBg, accent, subtext);
                html += pBox('最高', data.stats.max, cardBg, '#ef4444', subtext);
                html += '</div>';
                html += '<div style="font-size:10px;color:'+subtext+';text-align:center;">現在 '+data.stats.count+'台が市場に流通</div>';
            } else {
                html += '<div style="text-align:center;padding:12px;background:'+cardBg+';border-radius:8px;font-size:12px;color:'+subtext+';">価格データ収集中</div>';
            }

            if (hasResale) {
                var resaleBg = isDark ? '#2d2a1a' : '#fffbeb';
                html += '<div style="margin-top:12px;padding:12px;background:'+resaleBg+';border-radius:10px;text-align:center;">';
                html += '<div style="font-size:10px;font-weight:700;color:'+priceColor+';margin-bottom:4px;">想定買取価格</div>';
                html += '<div style="font-size:22px;font-weight:900;color:'+priceColor+';">'+data.resale.min+'<span style="font-size:12px;color:'+subtext+';"> ~ </span>'+data.resale.max+'<span style="font-size:12px;font-weight:700;color:'+subtext+';"> 万円</span></div>';
                html += '</div>';
            }
            html += '</div>';

            // フッター
            html += '<div style="padding:12px 20px;border-top:1px solid '+border+';display:flex;align-items:center;justify-content:space-between;">';
            html += '<span style="font-size:11px;font-weight:700;color:'+accent+';">詳細を見る →</span>';
            html += '<span style="font-size:10px;font-weight:600;color:'+subtext+';">Powered by MotoHub</span>';
            html += '</div></div>';
            html += '<div style="text-align:right;font-size:9px;color:#9ca3af;margin-top:4px;max-width:400px;">更新: '+data.updated_at+'</div>';
            html += '</div>';
            return '<div style="display:block;">' + html + '</div>';
        }

        function pBox(label, value, bg, valueColor, labelColor) {
            return '<div style="background:'+bg+';border-radius:8px;padding:8px 4px;text-align:center;"><div style="font-size:9px;font-weight:700;color:'+labelColor+';">'+label+'</div><div style="font-size:18px;font-weight:900;color:'+valueColor+';line-height:1.2;">'+value+'<span style="font-size:10px;">万</span></div></div>';
        }

        function escapeHtml(str) {
            if (!str) return '';
            return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
        }

        // デモプレビュー（CB400 Super Four）
        document.addEventListener('DOMContentLoaded', function() {
            var DEMO_MODEL_ID = 59;
            var preview = document.getElementById('widget-preview');
            fetch('/api/widget/price/' + DEMO_MODEL_ID)
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    preview.innerHTML = renderPreview(data, 'light', true);
                })
                .catch(function() {
                    preview.innerHTML = '<div style="text-align:center;padding:20px;color:#999;font-size:12px;">デモの読み込みに失敗しました</div>';
                });
        });
        </script>
    </x-slot:scripts>
</x-layout>