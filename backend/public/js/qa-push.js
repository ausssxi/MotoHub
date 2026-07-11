/**
 * 車種Q&A「回答が付いたら通知」オプトイン。
 * 質問フォーム送信（ユーザージェスチャ）内でプッシュ購読を取得し、hidden fieldへ詰めて通常POST。
 * 質問IDはサーバ側で作成後に紐付くため、クライアントはendpoint/keysを渡すだけでよい。
 * 既存のプッシュ基盤（/sw.js・vapid-public-keyメタ）を流用。push-manager.js には手を入れない。
 */
(function () {
    'use strict';

    function isSupported() {
        return ('serviceWorker' in navigator) && ('PushManager' in window) && ('Notification' in window);
    }

    function vapidKey() {
        var meta = document.querySelector('meta[name="vapid-public-key"]');
        return meta ? meta.content : null;
    }

    function urlBase64ToUint8Array(base64String) {
        var padding = '='.repeat((4 - base64String.length % 4) % 4);
        var base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        var raw = atob(base64);
        var arr = new Uint8Array(raw.length);
        for (var i = 0; i < raw.length; ++i) arr[i] = raw.charCodeAt(i);
        return arr;
    }

    // 購読を取得（POSTはしない）。{endpoint, p256dh, auth} を返す。失敗時は reject。
    function acquireSubscription() {
        var key = vapidKey();
        if (!key) return Promise.reject(new Error('no-vapid'));

        return Notification.requestPermission().then(function (perm) {
            if (perm !== 'granted') throw new Error('denied');
            return navigator.serviceWorker.ready;
        }).then(function (reg) {
            return reg.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(key),
            });
        }).then(function (sub) {
            var keys = sub.toJSON().keys;
            return { endpoint: sub.endpoint, p256dh: keys.p256dh, auth: keys.auth };
        });
    }

    function setup(form) {
        var optinWrap = form.querySelector('[data-qa-push-optin]');
        var checkbox = form.querySelector('input[name="qa_notify_optin"]');
        var fEndpoint = form.querySelector('input[name="push_endpoint"]');
        var fP256dh = form.querySelector('input[name="push_p256dh"]');
        var fAuth = form.querySelector('input[name="push_auth"]');

        // 非対応環境ではオプトインUIを出さない（既存の入荷通知と同じ考え方）
        if (!isSupported() || Notification.permission === 'denied') {
            if (optinWrap) optinWrap.remove();
            return;
        }
        if (optinWrap) {
            optinWrap.classList.remove('hidden');
            optinWrap.classList.add('flex');
        }

        var submitting = false;

        form.addEventListener('submit', function (e) {
            if (submitting) return;                       // 2周目（プログラム送信）は素通し
            if (!checkbox || !checkbox.checked) return;   // オプトインOFF → 通常送信
            if (!fEndpoint || !fP256dh || !fAuth) return;

            e.preventDefault();
            submitting = true;

            var btn = form.querySelector('button[type="submit"]');
            if (btn) btn.disabled = true;

            acquireSubscription().then(function (info) {
                fEndpoint.value = info.endpoint;
                fP256dh.value = info.p256dh;
                fAuth.value = info.auth;
            }).catch(function () {
                // 許可されなかった/失敗 → 通知なしで投稿続行（マイナス無し）
            }).then(function () {
                form.submit();
            });
        });
    }

    function init() {
        var forms = document.querySelectorAll('form[data-qa-push-form]');
        for (var i = 0; i < forms.length; i++) setup(forms[i]);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
