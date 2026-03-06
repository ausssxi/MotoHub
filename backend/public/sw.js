/**
 * MotoHub Service Worker
 * - Web Push 通知の受信・表示
 * - 通知クリック時のページ遷移
 */

self.addEventListener('push', function (event) {
    if (!event.data) return;

    var data = event.data.json();

    var options = {
        body: data.body || '',
        icon: data.icon || '/favicon-96x96.png',
        badge: data.badge || '/favicon-96x96.png',
        data: { url: data.url || '/' },
        vibrate: [200, 100, 200],
        tag: data.tag || 'motohub-notification',
        renotify: true,
    };

    event.waitUntil(
        self.registration.showNotification(data.title || 'MotoHub', options)
    );
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();

    var url = event.notification.data && event.notification.data.url
        ? event.notification.data.url
        : '/';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clientList) {
            // 既に開いているタブがあればフォーカス
            for (var i = 0; i < clientList.length; i++) {
                var client = clientList[i];
                if (client.url.includes(self.location.origin) && 'focus' in client) {
                    client.navigate(url);
                    return client.focus();
                }
            }
            // なければ新しいタブで開く
            if (clients.openWindow) {
                return clients.openWindow(url);
            }
        })
    );
});
