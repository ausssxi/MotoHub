/**
 * ライダーズマップ - 記事投稿ピンドロップ機能
 */
(function() {
    'use strict';

    var map;
    var pinMode = false;
    var pinMarker = null;
    var clickHandler = null;

    function waitForMap(cb) {
        if (window.ridersMap) { cb(); return; }
        var t = setInterval(function() {
            if (window.ridersMap) { clearInterval(t); cb(); }
        }, 100);
    }

    function init() {
        map = window.ridersMap;

        var btn = document.getElementById('btn-blog-pin');
        if (!btn) return;

        btn.addEventListener('click', function() {
            togglePinMode();
        });
    }

    function togglePinMode() {
        pinMode = !pinMode;
        var btn = document.getElementById('btn-blog-pin');
        var mapEl = document.getElementById('map');

        if (pinMode) {
            // ルート作成モードが有効なら解除
            var routeBtn = document.getElementById('btn-route-toggle');
            if (routeBtn && routeBtn.classList.contains('active')) {
                routeBtn.click();
            }

            btn.classList.add('active');
            btn.querySelector('.blog-pin-label').textContent = '場所を選択中...';
            if (mapEl) mapEl.classList.add('blog-pin-mode');

            clickHandler = function(e) {
                placePin(e.latlng);
            };
            map.on('click', clickHandler);
        } else {
            deactivate();
        }
    }

    function deactivate() {
        pinMode = false;
        var btn = document.getElementById('btn-blog-pin');
        var mapEl = document.getElementById('map');

        if (btn) {
            btn.classList.remove('active');
            btn.querySelector('.blog-pin-label').textContent = 'ガイドを書く';
        }
        if (mapEl) mapEl.classList.remove('blog-pin-mode');

        if (clickHandler) {
            map.off('click', clickHandler);
            clickHandler = null;
        }
    }

    function placePin(latlng) {
        // 既存ピンを除去
        if (pinMarker) {
            map.removeLayer(pinMarker);
            pinMarker = null;
        }

        var lat = Math.round(latlng.lat * 10000000) / 10000000;
        var lng = Math.round(latlng.lng * 10000000) / 10000000;

        pinMarker = L.marker([lat, lng], {
            icon: L.divIcon({
                className: '',
                html: '<div style="width:36px;height:36px;border-radius:50%;background:#0891b2;color:#fff;display:flex;align-items:center;justify-content:center;font-size:18px;line-height:1;border:3px solid #fff;box-shadow:0 3px 8px rgba(0,0,0,.35);animation:bounce-in .3s ease;">&#x270D;&#xFE0F;</div>',
                iconSize: [36, 36],
                iconAnchor: [18, 18],
            }),
        }).addTo(map);

        // ローディングポップアップを表示
        pinMarker.bindPopup(buildLoadingPopup(lat, lng)).openPopup();

        // 逆ジオコーディング
        reverseGeocode(lat, lng, function(address) {
            if (!pinMarker) return;
            pinMarker.setPopupContent(buildPopup(lat, lng, address));
        });

        // ピンモード解除
        deactivate();
    }

    function buildLoadingPopup(lat, lng) {
        var createUrl = '/admin/touring/create?lat=' + lat + '&lng=' + lng;
        return '<div style="min-width:240px;font-family:system-ui,sans-serif;">'
            + '<div style="display:flex;align-items:center;gap:6px;margin:0 0 8px;">'
            + '<svg width="14" height="14" fill="none" stroke="#9ca3af" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke-width="2"/><path stroke-width="2" d="M12 8v4m0 4h.01"/></svg>'
            + '<span style="font-size:11px;color:#6b7280;">住所を取得中...</span>'
            + '</div>'
            + '<p style="font-size:11px;color:#9ca3af;margin:0 0 10px;">'
            + lat.toFixed(5) + ', ' + lng.toFixed(5) + '</p>'
            + '<a href="' + createUrl + '" style="display:block;text-align:center;padding:8px 12px;background:#0891b2;color:#fff;border-radius:8px;font-size:12px;font-weight:700;text-decoration:none;">この場所のガイドを書く &rarr;</a>'
            + '</div>';
    }

    function buildPopup(lat, lng, address) {
        var createUrl = '/admin/touring/create?lat=' + lat + '&lng=' + lng;
        return '<div style="min-width:240px;font-family:system-ui,sans-serif;">'
            + '<p style="font-size:14px;font-weight:700;color:#1f2937;margin:0 0 2px;">' + escapeHtml(address) + '</p>'
            + '<p style="font-size:11px;color:#9ca3af;margin:0 0 12px;">'
            + lat.toFixed(5) + ', ' + lng.toFixed(5) + '</p>'
            + '<a href="' + createUrl + '" style="display:block;text-align:center;padding:8px 12px;background:#0891b2;color:#fff;border-radius:8px;font-size:12px;font-weight:700;text-decoration:none;">この場所のガイドを書く &rarr;</a>'
            + '<p style="font-size:10px;color:#9ca3af;margin:6px 0 0;text-align:center;">' + escapeHtml(address) + '</p>'
            + '</div>';
    }

    // 都道府県コード → 名称
    var PREF_NAMES = [
        '','北海道','青森県','岩手県','宮城県','秋田県','山形県','福島県',
        '茨城県','栃木県','群馬県','埼玉県','千葉県','東京都','神奈川県',
        '新潟県','富山県','石川県','福井県','山梨県','長野県','岐阜県',
        '静岡県','愛知県','三重県','滋賀県','京都府','大阪府','兵庫県',
        '奈良県','和歌山県','鳥取県','島根県','岡山県','広島県','山口県',
        '徳島県','香川県','愛媛県','高知県','福岡県','佐賀県','長崎県',
        '熊本県','大分県','宮崎県','鹿児島県','沖縄県'
    ];

    /**
     * 国土地理院 逆ジオコーディングAPI
     * レスポンス: { results: { muniCd: "13101", lv01Nm: "丸の内一丁目" } }
     */
    function reverseGeocode(lat, lng, callback) {
        var url = 'https://mreversegeocoder.gsi.go.jp/reverse-geocoder/LonLatToAddress?lat=' + lat + '&lon=' + lng;
        fetch(url)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data && data.results && data.results.muniCd) {
                    var prefCode = parseInt(data.results.muniCd.substring(0, 2), 10);
                    var pref = PREF_NAMES[prefCode] || '';
                    var local = data.results.lv01Nm || '';
                    var address = pref + local;
                    callback(address || lat.toFixed(5) + ', ' + lng.toFixed(5));
                } else {
                    callback(lat.toFixed(5) + ', ' + lng.toFixed(5));
                }
            })
            .catch(function() {
                callback(lat.toFixed(5) + ', ' + lng.toFixed(5));
            });
    }

    function escapeHtml(str) {
        if (!str) return '';
        var d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

    // マップクリック以外でピン除去（ESCキー）
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (pinMode) {
                deactivate();
            }
            if (pinMarker) {
                map.removeLayer(pinMarker);
                pinMarker = null;
            }
        }
    });

    waitForMap(init);
})();
