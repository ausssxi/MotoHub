/**
 * 愛車ガレージ カスタム記録「パーツ名 / ブランド」サジェスト（第1段階）
 * --------------------------------------------------------------------------
 * - データは自前エンドポイント /garage/api/parts-suggest?q=&field=part|brand のみ。
 *   外部API（楽天/Yahoo/Amazon 等）は一切叩かない。
 * - input[data-parts-suggest="part|brand"] に対し、入力→デバウンス→候補ドロップダウン表示。
 *   候補は直後の [data-parts-suggest-box] に描画。クリックで input に反映するだけ
 *   （価格・画像・リンクは出さない＝第2段階）。
 * - 既存の imperative な値書き換え（編集/前回コピー/OCR の setV）は input イベントを
 *   発火しないため、このサジェストとは衝突しない。
 */
(function () {
    'use strict';

    var DEBOUNCE_MS = 250;
    var MIN_CHARS = 1;

    function escapeHtml(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function setup(input) {
        if (input.dataset.partsSuggestInit === '1') return;
        input.dataset.partsSuggestInit = '1';

        var field = input.dataset.partsSuggest === 'brand' ? 'brand' : 'part';
        var box = input.parentElement
            ? input.parentElement.querySelector('[data-parts-suggest-box]')
            : null;
        if (!box) return;

        var timer = null;
        var lastQ = '';

        function hide() {
            box.classList.add('hidden');
            box.innerHTML = '';
        }

        function render(items) {
            if (!items || !items.length) { hide(); return; }
            var html = '';
            for (var i = 0; i < items.length; i++) {
                var safe = escapeHtml(items[i]);
                html += '<button type="button" data-val="' + safe + '" ' +
                    'class="block w-full text-left px-4 py-2.5 text-sm font-bold text-gray-700 hover:bg-purple-50 hover:text-purple-700 transition-colors">' +
                    safe + '</button>';
            }
            box.innerHTML = html;
            box.classList.remove('hidden');
        }

        function fetchSuggest(q) {
            var url = '/garage/api/parts-suggest?field=' + encodeURIComponent(field) +
                '&q=' + encodeURIComponent(q);
            fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.ok ? r.json() : []; })
                .then(function (data) {
                    // 取得中に入力が変わっていたら破棄
                    if (input.value.trim() !== q) return;
                    render(Array.isArray(data) ? data : []);
                })
                .catch(function () { hide(); });
        }

        input.addEventListener('input', function () {
            var q = input.value.trim();
            if (timer) { clearTimeout(timer); }
            if (q.length < MIN_CHARS) { hide(); lastQ = ''; return; }
            if (q === lastQ) { return; }
            lastQ = q;
            timer = setTimeout(function () { fetchSuggest(q); }, DEBOUNCE_MS);
        });

        // 候補クリックで反映（mousedown＋preventDefaultで blur による取りこぼしを防ぐ）
        box.addEventListener('mousedown', function (e) {
            var btn = e.target.closest ? e.target.closest('[data-val]') : null;
            if (!btn) { return; }
            e.preventDefault();
            input.value = btn.getAttribute('data-val');
            lastQ = input.value.trim();
            hide();
            input.focus();
        });

        // フォーカス外し／Escで閉じる
        input.addEventListener('blur', function () { setTimeout(hide, 120); });
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { hide(); }
        });
    }

    function init() {
        var inputs = document.querySelectorAll('input[data-parts-suggest]');
        for (var i = 0; i < inputs.length; i++) { setup(inputs[i]); }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
