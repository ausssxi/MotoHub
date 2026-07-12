/**
 * 閲覧履歴ベースの再訪フック「あなたが見た車種の新着」。
 * - 車種ページ: window.__viewedModel を localStorage に記録（直近10件・新しい順・slug重複は最新で上書き）。
 * - トップページ: 記録を読み、/api/bikes/viewed-stock で現在在庫を取得し、記録時より増えた車種を「新着N台」で強調。
 * 許可も登録も不要（localStorageで完結）。保存/送信するのは車種スラグ・在庫数・日時のみ（PII非該当）。
 */
(function () {
    'use strict';

    var KEY = 'motohub_viewed_models';
    var MAX = 10;

    function load() {
        try { return JSON.parse(localStorage.getItem(KEY) || '[]'); } catch (e) { return []; }
    }
    function save(list) {
        try { localStorage.setItem(KEY, JSON.stringify(list)); } catch (e) {}
    }

    function esc(s) {
        var d = document.createElement('div');
        d.textContent = (s == null) ? '' : String(s);
        return d.innerHTML;
    }

    // ---- 記録（車種ページ） ----
    function record(m) {
        if (!m || !m.slug || !m.mfrSlug) return; // slug が無ければURLを作れないので記録しない
        var list = load().filter(function (x) {
            return !(x.slug === m.slug && x.mfrSlug === m.mfrSlug);
        });
        list.unshift({
            slug: m.slug,
            mfrSlug: m.mfrSlug,
            maker: m.maker || '',
            name: m.name || '',
            stockCount: parseInt(m.stockCount, 10) || 0,
            viewedAt: Date.now(),
        });
        if (list.length > MAX) list = list.slice(0, MAX);
        save(list);
    }

    // ---- 表示（トップページ） ----
    function render(widget) {
        var list = load();
        if (!list.length) return;

        var keys = list.map(function (x) { return x.mfrSlug + '/' + x.slug; }).join(',');
        fetch('/api/bikes/viewed-stock?models=' + encodeURIComponent(keys), { headers: { Accept: 'application/json' } })
            .then(function (r) { return r.ok ? r.json() : {}; })
            .then(function (stock) { paint(widget, list, stock || {}); })
            .catch(function () { paint(widget, list, {}); }); // 取得失敗でも「最近見た車種」は出す
    }

    function paint(widget, list, stock) {
        var rows = list.map(function (x) {
            var key = x.mfrSlug + '/' + x.slug;
            var cur = (key in stock) ? (parseInt(stock[key], 10) || 0) : null;
            var delta = (cur !== null) ? cur - (parseInt(x.stockCount, 10) || 0) : 0;
            return { x: x, cur: cur, delta: delta };
        });
        // 新着(在庫増)を先頭、その後は閲覧が新しい順
        rows.sort(function (a, b) {
            return (b.delta > 0 ? 1 : 0) - (a.delta > 0 ? 1 : 0) || b.x.viewedAt - a.x.viewedAt;
        });

        var html = '';
        rows.forEach(function (e) {
            var x = e.x;
            var url = '/bikes/' + encodeURIComponent(x.mfrSlug) + '/' + encodeURIComponent(x.slug);
            var badge;
            if (e.delta > 0) {
                badge = '<span class="inline-flex items-center gap-1 text-[11px] font-black text-blue-600 bg-blue-50 px-2 py-0.5 rounded-full">新着' + e.delta + '台</span>';
            } else if (e.cur !== null && e.cur > 0) {
                badge = '<span class="inline-flex items-center text-[11px] font-bold text-gray-500 bg-gray-100 px-2 py-0.5 rounded-full">在庫' + e.cur + '台</span>';
            } else {
                badge = '<span class="inline-flex items-center text-[11px] font-bold text-gray-400">最近見た車種</span>';
            }
            html += '<a href="' + url + '" class="snap-start shrink-0 w-40 bg-white rounded-2xl border ' + (e.delta > 0 ? 'border-blue-200' : 'border-gray-100') + ' p-4 hover:shadow-md transition-shadow">'
                + '<div class="text-[10px] font-bold text-gray-400 mb-1 truncate">' + esc(x.maker) + '</div>'
                + '<div class="text-sm font-black text-gray-900 line-clamp-2 mb-2 leading-snug">' + esc(x.name) + '</div>'
                + badge
                + '</a>';
        });

        widget.innerHTML = html;
        var sec = document.getElementById('viewed-models-section');
        if (sec) sec.classList.remove('hidden');
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    function init() {
        if (window.__viewedModel) record(window.__viewedModel);

        var widget = document.getElementById('viewed-models-widget');
        if (widget) render(widget);

        var clearBtn = document.getElementById('viewed-models-clear');
        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                try { localStorage.removeItem(KEY); } catch (e) {}
                var sec = document.getElementById('viewed-models-section');
                if (sec) sec.classList.add('hidden');
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
