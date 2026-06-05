/**
 * MotoHub Push通知マネージャー
 * Alpine.js に依存しない純粋なDOM操作で通知UIを構築
 */
var MotoHubPush = (function () {
    'use strict';

    var vapidKey = null;
    var swRegistration = null;
    var csrfToken = null;

    function init() {
        var meta = document.querySelector('meta[name="vapid-public-key"]');
        if (meta) vapidKey = meta.content;

        var csrfMeta = document.querySelector('meta[name="csrf-token"]');
        if (csrfMeta) csrfToken = csrfMeta.content;

        if ('serviceWorker' in navigator && 'PushManager' in window) {
            navigator.serviceWorker.register('/sw.js').then(function (reg) {
                swRegistration = reg;
            }).catch(function () {});
        }

        // 通知エリアの描画
        renderAllAreas();
    }

    function urlBase64ToUint8Array(base64String) {
        var padding = '='.repeat((4 - base64String.length % 4) % 4);
        var base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        var rawData = atob(base64);
        var arr = new Uint8Array(rawData.length);
        for (var i = 0; i < rawData.length; ++i) arr[i] = rawData.charCodeAt(i);
        return arr;
    }

    // ---- 購読 / 解除 ----

    function subscribe(bikeModelId) {
        if (!swRegistration || !vapidKey) {
            return Promise.reject(new Error('Push未初期化（HTTPS環境が必要です）'));
        }
        return Notification.requestPermission().then(function (p) {
            if (p !== 'granted') throw new Error('通知が許可されませんでした');
            return swRegistration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(vapidKey),
            });
        }).then(function (sub) {
            var keys = sub.toJSON().keys;
            return fetch('/api/push/subscribe', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify({ endpoint: sub.endpoint, p256dh: keys.p256dh, auth: keys.auth, bike_model_id: bikeModelId }),
            });
        }).then(function (res) {
            if (!res.ok) throw new Error('サーバー登録失敗');
            saveLocal(bikeModelId, true);
            // GA4: 購読成功イベント（露出箇所を問わず subscribe 経由で一括計測）
            if (typeof gtag === 'function') {
                try { gtag('event', 'push_subscribe', { bike_model_id: bikeModelId || null }); } catch (e) {}
            }
            return true;
        });
    }

    function unsubscribe(bikeModelId) {
        if (!swRegistration) return Promise.reject(new Error('Push未初期化'));
        return swRegistration.pushManager.getSubscription().then(function (sub) {
            if (!sub) return true;
            return fetch('/api/push/unsubscribe', {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify({ endpoint: sub.endpoint, bike_model_id: bikeModelId }),
            }).then(function (res) {
                if (!res.ok) throw new Error('解除失敗');
                saveLocal(bikeModelId, false);
                return true;
            });
        });
    }

    // ---- localStorage ----

    function saveLocal(bikeModelId, subscribed) {
        try {
            var d = JSON.parse(localStorage.getItem('push_subs') || '{}');
            if (subscribed) d[bikeModelId] = true; else delete d[bikeModelId];
            localStorage.setItem('push_subs', JSON.stringify(d));
        } catch (e) {}
    }

    function isSubscribed(bikeModelId) {
        try {
            return !!JSON.parse(localStorage.getItem('push_subs') || '{}')[bikeModelId];
        } catch (e) { return false; }
    }

    // ---- 環境判定 ----

    function getNotificationSupport() {
        var ua = navigator.userAgent;
        var isIOS = /iPad|iPhone|iPod/.test(ua);
        if (!isIOS) return 'supported'; // PC / Android は常にOK（本番は HTTPS）
        if (window.matchMedia('(display-mode: standalone)').matches) return 'supported'; // iOS PWA
        if (/CriOS|EdgiOS/.test(ua)) return 'ios-other-browser';
        return 'ios-safari';
    }

    // ---- UI描画 ----

    function renderAllAreas() {
        var areas = document.querySelectorAll('[id^="push-area-"]');
        for (var i = 0; i < areas.length; i++) {
            renderArea(areas[i]);
        }
    }

    function appendHint(el, isHeader) {
        var hint = document.createElement('p');
        hint.className = 'text-xs font-bold mt-1 ' + (isHeader ? 'text-gray-300' : 'text-gray-400 text-center');
        hint.textContent = '\uD83D\uDCEC 毎朝 8:30 に値下げ・新着情報をお届けします';
        el.appendChild(hint);
    }

    function renderArea(el) {
        var modelId = parseInt(el.dataset.modelId, 10);
        if (!modelId) return;

        var support = getNotificationSupport();
        var isHeader = el.id === 'push-area-header';

        if (support === 'supported') {
            renderSubscribeButton(el, modelId, isHeader);
        } else if (support === 'ios-safari') {
            renderIOSSafariGuide(el, isHeader);
            appendHint(el, isHeader);
        } else if (support === 'ios-other-browser') {
            renderIOSOtherGuide(el, isHeader);
            appendHint(el, isHeader);
        }
    }

    function renderSubscribeButton(el, modelId, isHeader) {
        var subscribed = isSubscribed(modelId);

        var btn = document.createElement('button');
        btn.type = 'button';

        if (isHeader) {
            btn.className = 'inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border text-xs font-bold transition-all '
                + (subscribed ? 'bg-white/20 border-white/40 text-white' : 'bg-blue-500 hover:bg-blue-400 border-blue-400 text-white');
        } else {
            btn.className = 'flex items-center justify-center gap-2 w-full py-2.5 rounded-xl border text-xs font-bold transition-all '
                + (subscribed ? 'bg-gray-100 text-gray-500 border-gray-200' : 'bg-blue-50 text-blue-600 border-blue-200 hover:bg-blue-100');
        }

        btn.innerHTML = '<i data-lucide="' + (subscribed ? 'bell-off' : 'bell') + '" class="w-4 h-4"></i>'
            + '<span>' + (subscribed ? (isHeader ? '通知をオフにする' : '通知オフ') : (isHeader ? 'この車種の入荷通知を受け取る' : '入荷通知を受け取る')) + '</span>';

        var msgEl = document.createElement('p');
        msgEl.className = 'text-[10px] font-bold mt-1.5 ' + (isHeader ? 'text-green-300' : 'text-green-600 text-center');
        msgEl.style.display = 'none';

        btn.addEventListener('click', function () {
            btn.disabled = true;
            btn.querySelector('span').textContent = '処理中...';

            var action = isSubscribed(modelId) ? unsubscribe(modelId) : subscribe(modelId);
            action.then(function () {
                var nowSub = isSubscribed(modelId);
                msgEl.textContent = nowSub ? '通知をオンにしました！新着入荷時にお知らせします' : '通知をオフにしました';
                msgEl.style.display = '';
                // ボタン再描画
                el.innerHTML = '';
                renderSubscribeButton(el, modelId, isHeader);
                el.querySelector('p').textContent = msgEl.textContent;
                el.querySelector('p').style.display = '';
                window.dispatchEvent(new CustomEvent('push-subs-changed'));
            }).catch(function (e) {
                msgEl.textContent = e.message || '操作に失敗しました';
                msgEl.style.display = '';
                btn.disabled = false;
                btn.querySelector('span').textContent = isSubscribed(modelId)
                    ? (isHeader ? '通知をオフにする' : '通知オフ')
                    : (isHeader ? 'この車種の入荷通知を受け取る' : '入荷通知を受け取る');
            });
        });

        el.innerHTML = '';
        el.appendChild(btn);
        el.appendChild(msgEl);

        // 補足テキスト（未購読時）
        if (!subscribed) appendHint(el, isHeader);

        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    function renderIOSSafariGuide(el, isHeader) {
        if (isHeader) {
            el.innerHTML = '<div class="bg-white/10 backdrop-blur-sm border border-white/20 rounded-xl p-4 max-w-sm">'
                + '<p class="text-sm font-bold text-white mb-2"><span class="mr-1">📲</span> ホーム画面に追加すると通知が届きます</p>'
                + '<p class="text-xs text-gray-300 leading-relaxed">'
                + '下の <svg class="w-4 h-4 inline-block align-text-bottom" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 12v8a2 2 0 002 2h12a2 2 0 002-2v-8"/><polyline points="16 6 12 2 8 6"/><line x1="12" y1="2" x2="12" y2="15"/></svg>'
                + ' をタップ → 「ホーム画面に追加」</p></div>';
        } else {
            el.innerHTML = '<div class="bg-blue-50 border border-blue-200 rounded-xl p-3 text-center">'
                + '<p class="text-[11px] font-bold text-blue-800 mb-1">📲 ホーム画面に追加で通知OK</p>'
                + '<p class="text-[10px] text-blue-600">'
                + '<svg class="w-3.5 h-3.5 inline-block align-text-bottom" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 12v8a2 2 0 002 2h12a2 2 0 002-2v-8"/><polyline points="16 6 12 2 8 6"/><line x1="12" y1="2" x2="12" y2="15"/></svg>'
                + ' → ホーム画面に追加</p></div>';
        }
    }

    function renderIOSOtherGuide(el, isHeader) {
        var wrap = document.createElement('div');
        wrap.className = isHeader
            ? 'bg-white/10 backdrop-blur-sm border border-white/20 rounded-xl p-4 max-w-sm'
            : 'bg-gray-50 border border-gray-200 rounded-xl p-3 text-center';

        var title = isHeader ? '通知を受け取るには' : 'Safariで開いて通知登録';
        wrap.innerHTML = '<p class="text-' + (isHeader ? 'sm' : '[11px]') + ' font-bold ' + (isHeader ? 'text-white' : 'text-gray-800') + ' mb-' + (isHeader ? '2' : '1') + '"><span class="mr-1">📲</span> ' + title + '</p>'
            + (isHeader ? '<p class="text-xs text-gray-300 mb-2">Safariでこのページを開いて「ホーム画面に追加」してください。</p>' : '');

        var copyBtn = document.createElement('button');
        copyBtn.type = 'button';
        copyBtn.className = 'mt-1 text-xs font-bold px-3 py-1.5 rounded-lg bg-blue-50 text-blue-600';
        copyBtn.textContent = isHeader ? 'このページのURLをコピー' : 'URLをコピー';
        copyBtn.addEventListener('click', function () {
            navigator.clipboard.writeText(window.location.href).then(function () {
                copyBtn.textContent = 'コピーしました！';
                copyBtn.className = copyBtn.className.replace('bg-blue-50 text-blue-600', 'bg-green-100 text-green-700');
                setTimeout(function () {
                    copyBtn.textContent = isHeader ? 'このページのURLをコピー' : 'URLをコピー';
                    copyBtn.className = copyBtn.className.replace('bg-green-100 text-green-700', 'bg-blue-50 text-blue-600');
                }, 2000);
            });
        });

        wrap.appendChild(copyBtn);
        el.innerHTML = '';
        el.appendChild(wrap);
    }

    // ---- 起動 ----

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    return {
        subscribe: subscribe,
        unsubscribe: unsubscribe,
        isSubscribed: isSubscribed,
        getNotificationSupport: getNotificationSupport,
    };
})();
