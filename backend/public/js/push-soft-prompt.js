/**
 * MotoHub プッシュ通知ソフトプロンプト（プッシュが使える環境の「1枚だけ」ポップアップ）
 * - 車両詳細／車種ページの閲覧数を localStorage でカウントし、2件目以降 もしくは お気に入り操作の直後に提案。
 * - 「通知を受け取る」を押して初めて MotoHubPush.subscribe() を呼ぶ（読込時に勝手にネイティブ許可を出さない）。
 * - 抑制は LINE 系ポップアップと共有（localStorage 'line_banner_dismissed_at' / 14日）。片方を閉じれば全て出ない。
 * - プッシュが使えない環境（iOS Safari 非PWA / 許可denied / 購読済み）では出さず、LINE 側（return-trigger / engagement-banner）に委ねる。
 * - 他ポップアップが表示中なら出さない＝常に1枚。
 * - SW・購読・送信ロジックには一切触れない（既存 MotoHubPush をそのまま利用）。
 */
(function () {
    'use strict';

    var DISMISS_KEY = 'line_banner_dismissed_at'; // LINE系と共有する抑制キー
    var DISMISS_DAYS = 14;
    var SESSION_SEEN = 'mh_push_soft_seen';
    var VIEW_KEY = 'mh_push_views';
    var SHOW_FROM_VIEW = 2; // 2〜3件目で提案

    // GA4 計測（非ブロッキング・既存 gtag 慣習に準拠）
    function track(name, params) {
        if (typeof gtag === 'function') {
            try { gtag('event', name, params || {}); } catch (e) {}
        }
    }

    // ---- 共通ガード ----

    function isDismissed() {
        var ts = localStorage.getItem(DISMISS_KEY);
        if (!ts) return false;
        return (Date.now() - parseInt(ts, 10)) < DISMISS_DAYS * 24 * 60 * 60 * 1000;
    }

    function setDismissed() {
        try { localStorage.setItem(DISMISS_KEY, String(Date.now())); } catch (e) {}
    }

    // 他のポップアップ（LINE再訪シート / 詳細バナー / 会員登録 / 自分）が表示中か
    function anyPopupOpen() {
        return !!(document.getElementById('mh-push-soft-prompt')
            || document.getElementById('engagement-banner')
            || document.getElementById('return-trigger-sheet')
            || document.getElementById('promo-pageview-popup'));
    }

    // 詳細/車種ページの push-area から対象モデルを取得（無ければ null）
    function getPushArea() {
        return document.getElementById('push-area-listing') || document.getElementById('push-area-header');
    }

    // この環境・このページでプッシュ購読が成立するか（= プッシュ優先の判定）
    function pushViable() {
        try {
            if (typeof MotoHubPush === 'undefined' || typeof MotoHubPush.getNotificationSupport !== 'function') return false;
            if (MotoHubPush.getNotificationSupport() !== 'supported') return false;
            if (typeof Notification !== 'undefined' && Notification.permission === 'denied') return false;
            var area = getPushArea();
            if (!area) return false;
            var mid = parseInt(area.dataset.modelId, 10);
            if (!mid) return false;
            if (MotoHubPush.isSubscribed && MotoHubPush.isSubscribed(mid)) return false;
            return true;
        } catch (e) {
            return false;
        }
    }

    function init() {
        var area = getPushArea();
        if (!area) return; // 単一の詳細ページのみ対象

        var modelId = parseInt(area.dataset.modelId, 10);
        if (!modelId) return;
        var modelName = area.dataset.modelName || 'この車種';

        // プッシュが使えない/購読済み/抑制中 なら出さない（LINE側に委ねる）
        if (!pushViable()) return;
        if (isDismissed()) return;

        // 閲覧数カウント（このページの読込で1回だけ加算）
        var views = 0;
        try { views = parseInt(localStorage.getItem(VIEW_KEY) || '0', 10) || 0; } catch (e) {}
        views += 1;
        try { localStorage.setItem(VIEW_KEY, String(views)); } catch (e) {}

        var shown = false;

        function showPrompt() {
            if (shown) return;
            try { if (sessionStorage.getItem(SESSION_SEEN)) return; } catch (e) {}
            // 表示直前に全条件を再チェック（遅延中に状況が変わった可能性）
            if (isDismissed()) return;
            if (!pushViable()) return;
            if (anyPopupOpen()) return; // 既に他ポップアップが出ていれば譲る＝常に1枚
            shown = true;
            try { sessionStorage.setItem(SESSION_SEEN, '1'); } catch (e) {}
            render();
        }

        function render() {
            var wrap = document.createElement('div');
            wrap.id = 'mh-push-soft-prompt';
            wrap.className = 'fixed left-4 right-4 bottom-24 sm:bottom-6 z-[60] max-w-md sm:mx-auto';
            wrap.style.cssText = 'transition:transform .3s ease,opacity .3s ease;transform:translateY(20px);opacity:0;';

            var card = document.createElement('div');
            card.className = 'bg-white rounded-2xl shadow-2xl border border-gray-100 p-4 flex items-start gap-3';
            card.innerHTML =
                '<div class="shrink-0 w-10 h-10 rounded-full bg-blue-50 text-blue-500 flex items-center justify-center text-lg">🔔</div>' +
                '<div class="flex-1 min-w-0">' +
                  '<p class="text-sm font-black text-gray-800 leading-snug">' + escapeHtml(modelName) + ' の新着を通知で受け取りますか？</p>' +
                  '<p class="text-[11px] font-bold text-gray-400 mt-0.5">値下げ・新着入荷があったらお届けします（いつでも解除OK）</p>' +
                  '<div class="flex items-center gap-2 mt-3">' +
                    '<button type="button" data-act="allow" class="flex-1 py-2 rounded-xl bg-blue-500 hover:bg-blue-400 text-white text-xs font-black transition">通知を受け取る</button>' +
                    '<button type="button" data-act="later" class="py-2 px-3 rounded-xl bg-gray-50 text-gray-500 text-xs font-bold hover:bg-gray-100 transition">あとで</button>' +
                  '</div>' +
                '</div>' +
                '<button type="button" data-act="close" aria-label="閉じる" class="shrink-0 text-gray-300 hover:text-gray-500 -mt-1 -mr-1 p-1">&times;</button>';

            wrap.appendChild(card);
            document.body.appendChild(wrap);
            requestAnimationFrame(function () {
                wrap.style.transform = 'translateY(0)';
                wrap.style.opacity = '1';
            });

            function dismiss(suppress) {
                if (suppress) setDismissed();
                wrap.style.transform = 'translateY(20px)';
                wrap.style.opacity = '0';
                setTimeout(function () { if (wrap.parentNode) wrap.parentNode.removeChild(wrap); }, 300);
            }

            card.addEventListener('click', function (e) {
                var act = e.target.closest('[data-act]');
                if (!act) return;
                var a = act.dataset.act;
                var mSlug = (window.__viewedModel && window.__viewedModel.slug) || null;
                if (a === 'allow') {
                    // GA4: ポップアップ導線の通知ボタンクリック（許可/成功は subscribe 経由で計測）
                    track('notify_cta_click', { source: 'popup', bike_model_id: modelId || null, model_slug: mSlug });
                    act.disabled = true;
                    act.textContent = '処理中...';
                    MotoHubPush.subscribe(modelId).then(function () {
                        act.textContent = '登録しました！';
                        setDismissed(); // 登録済みは再提案しない
                        setTimeout(function () { dismiss(false); }, 1200);
                    }).catch(function () {
                        act.disabled = false;
                        act.textContent = '通知を受け取る';
                        // 拒否された場合はしつこくしない（共有抑制）
                        dismiss(true);
                    });
                } else {
                    // GA4: ポップアップの「あとで」/「×」離脱（a = later | close）
                    track('notify_popup_dismiss', { action: a, bike_model_id: modelId || null, model_slug: mSlug });
                    // 「あとで」/「×」は14日抑制（LINE系と共有）
                    dismiss(true);
                }
            });
        }

        // トリガー1: 閲覧数が2件目以降
        if (views >= SHOW_FROM_VIEW) {
            setTimeout(showPrompt, 1500);
        }

        // トリガー2: お気に入り操作の直後（.wishlist-btn は WishlistManager が拾うので別途監視）
        document.addEventListener('click', function (e) {
            if (e.target.closest('.wishlist-btn')) {
                setTimeout(showPrompt, 1200);
            }
        });
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
