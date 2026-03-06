/**
 * MotoHub Push通知マネージャー
 *
 * 使い方:
 *   MotoHubPush.subscribe(bikeModelId)   - 車種の通知を購読
 *   MotoHubPush.unsubscribe(bikeModelId) - 車種の通知を解除
 *   MotoHubPush.isSubscribed(bikeModelId) - 購読状態を確認
 */
var MotoHubPush = (function () {
    'use strict';

    // Blade側の <meta name="vapid-public-key"> から取得
    var vapidKey = null;
    var swRegistration = null;
    var csrfToken = null;

    function init() {
        var meta = document.querySelector('meta[name="vapid-public-key"]');
        if (meta) vapidKey = meta.content;

        var csrfMeta = document.querySelector('meta[name="csrf-token"]');
        if (csrfMeta) csrfToken = csrfMeta.content;

        if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
            console.warn('[MotoHubPush] Push通知非対応ブラウザ');
            return;
        }

        navigator.serviceWorker.register('/sw.js').then(function (reg) {
            swRegistration = reg;
        }).catch(function (err) {
            console.error('[MotoHubPush] SW登録失敗:', err);
        });
    }

    function urlBase64ToUint8Array(base64String) {
        var padding = '='.repeat((4 - base64String.length % 4) % 4);
        var base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        var rawData = atob(base64);
        var outputArray = new Uint8Array(rawData.length);
        for (var i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }

    /**
     * 指定車種の通知を購読する
     * @param {number} bikeModelId
     * @returns {Promise<boolean>}
     */
    function subscribe(bikeModelId) {
        if (!swRegistration || !vapidKey) {
            return Promise.reject(new Error('MotoHubPush未初期化'));
        }

        return Notification.requestPermission().then(function (permission) {
            if (permission !== 'granted') {
                throw new Error('通知が許可されませんでした');
            }

            return swRegistration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(vapidKey),
            });
        }).then(function (subscription) {
            var keys = subscription.toJSON().keys;

            return fetch('/api/push/subscribe', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({
                    endpoint: subscription.endpoint,
                    p256dh: keys.p256dh,
                    auth: keys.auth,
                    bike_model_id: bikeModelId,
                }),
            });
        }).then(function (res) {
            if (!res.ok) throw new Error('サーバー登録失敗: ' + res.status);
            // ローカルに購読状態を保存
            saveLocal(bikeModelId, true);
            return true;
        });
    }

    /**
     * 指定車種の通知を解除する
     * @param {number} bikeModelId
     * @returns {Promise<boolean>}
     */
    function unsubscribe(bikeModelId) {
        if (!swRegistration) {
            return Promise.reject(new Error('MotoHubPush未初期化'));
        }

        return swRegistration.pushManager.getSubscription().then(function (subscription) {
            if (!subscription) return true;

            return fetch('/api/push/unsubscribe', {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({
                    endpoint: subscription.endpoint,
                    bike_model_id: bikeModelId,
                }),
            }).then(function (res) {
                if (!res.ok) throw new Error('サーバー解除失敗: ' + res.status);
                saveLocal(bikeModelId, false);
                return true;
            });
        });
    }

    /**
     * 購読状態をlocalStorageで管理（未ログインユーザー対応）
     */
    function saveLocal(bikeModelId, subscribed) {
        try {
            var data = JSON.parse(localStorage.getItem('push_subs') || '{}');
            if (subscribed) {
                data[bikeModelId] = true;
            } else {
                delete data[bikeModelId];
            }
            localStorage.setItem('push_subs', JSON.stringify(data));
        } catch (e) { /* ignore */ }
    }

    function isSubscribed(bikeModelId) {
        try {
            var data = JSON.parse(localStorage.getItem('push_subs') || '{}');
            return !!data[bikeModelId];
        } catch (e) {
            return false;
        }
    }

    // DOMContentLoaded で自動初期化
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    return {
        subscribe: subscribe,
        unsubscribe: unsubscribe,
        isSubscribed: isSubscribed,
    };
})();
