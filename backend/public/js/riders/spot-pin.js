/**
 * ライダーズマップ - お気に入りスポット ピン留め機能
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

        var btn = document.getElementById('btn-spot-pin');
        if (!btn) return;

        btn.addEventListener('click', function() {
            togglePinMode();
        });
    }

    function togglePinMode() {
        pinMode = !pinMode;
        var btn = document.getElementById('btn-spot-pin');
        var mapEl = document.getElementById('map');

        if (pinMode) {
            // ルート作成モードが有効なら解除
            var routeBtn = document.getElementById('btn-route-toggle');
            if (routeBtn && routeBtn.classList.contains('active')) {
                routeBtn.click();
            }

            // ブログピンモードが有効なら解除
            var blogBtn = document.getElementById('btn-blog-pin');
            if (blogBtn && blogBtn.classList.contains('active')) {
                blogBtn.click();
            }

            btn.classList.add('active');
            btn.querySelector('.spot-pin-label').textContent = '場所を選択中...';
            if (mapEl) mapEl.classList.add('spot-pin-mode');

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
        var btn = document.getElementById('btn-spot-pin');
        var mapEl = document.getElementById('map');

        if (btn) {
            btn.classList.remove('active');
            btn.querySelector('.spot-pin-label').textContent = 'ピン留め';
        }
        if (mapEl) mapEl.classList.remove('spot-pin-mode');

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

        // ポップアップ表示中はマップのz-indexを上げてレイヤーボタンの上に表示
        document.getElementById('map').classList.add('has-spot-popup');

        pinMarker = L.marker([lat, lng], {
            icon: L.divIcon({
                className: '',
                html: '<div style="width:36px;height:36px;border-radius:50%;background:#f59e0b;color:#fff;display:flex;align-items:center;justify-content:center;font-size:18px;line-height:1;border:3px solid #fff;box-shadow:0 3px 8px rgba(0,0,0,.35);animation:bounce-in .3s ease;">&#x2B50;</div>',
                iconSize: [36, 36],
                iconAnchor: [18, 18],
            }),
        }).addTo(map);

        // ローディングポップアップを表示
        pinMarker.bindPopup(buildLoadingPopup(lat, lng), { maxWidth: 300, minWidth: 260 }).openPopup();

        // ポップアップが閉じられたらピンとz-indexクラスを除去
        pinMarker.on('popupclose', function() {
            removePin();
        });

        // 逆ジオコーディング
        reverseGeocode(lat, lng, function(address) {
            if (!pinMarker) return;
            pinMarker.setPopupContent(buildFormPopup(lat, lng, address));
            bindFormEvents(lat, lng);
        });

        // ピンモード解除
        deactivate();
    }

    function buildLoadingPopup(lat, lng) {
        return '<div style="min-width:240px;font-family:system-ui,sans-serif;">'
            + '<div style="display:flex;align-items:center;gap:6px;margin:0 0 8px;">'
            + '<svg width="14" height="14" fill="none" stroke="#9ca3af" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke-width="2"/><path stroke-width="2" d="M12 8v4m0 4h.01"/></svg>'
            + '<span style="font-size:11px;color:#6b7280;">住所を取得中...</span>'
            + '</div>'
            + '<p style="font-size:11px;color:#9ca3af;margin:0;">'
            + lat.toFixed(5) + ', ' + lng.toFixed(5) + '</p>'
            + '</div>';
    }

    function buildFormPopup(lat, lng, address) {
        return '<div style="min-width:240px;font-family:system-ui,sans-serif;" id="spot-save-form">'
            + '<p style="font-size:11px;color:#9ca3af;margin:0 0 8px;">'
            + lat.toFixed(5) + ', ' + lng.toFixed(5) + '</p>'
            + '<div style="margin:0 0 8px;">'
            + '<label style="font-size:11px;font-weight:700;color:#374151;display:block;margin:0 0 3px;">スポット名 *</label>'
            + '<input id="spot-name" type="text" value="' + escapeAttr(address) + '" style="width:100%;padding:6px 8px;border:1px solid #d1d5db;border-radius:6px;font-size:12px;box-sizing:border-box;" />'
            + '</div>'
            + '<div style="margin:0 0 8px;">'
            + '<label style="font-size:11px;font-weight:700;color:#374151;display:block;margin:0 0 3px;">カテゴリ</label>'
            + '<select id="spot-category" style="width:100%;padding:6px 8px;border:1px solid #d1d5db;border-radius:6px;font-size:12px;box-sizing:border-box;background:#fff;">'
            + '<option value="touring_spot">ツーリングスポット</option>'
            + '<option value="rest_area">休憩所</option>'
            + '<option value="scenic_point">絶景ポイント</option>'
            + '<option value="other">その他</option>'
            + '</select>'
            + '</div>'
            + '<div style="margin:0 0 10px;">'
            + '<label style="font-size:11px;font-weight:700;color:#374151;display:block;margin:0 0 3px;">メモ（任意）</label>'
            + '<textarea id="spot-memo" rows="2" style="width:100%;padding:6px 8px;border:1px solid #d1d5db;border-radius:6px;font-size:12px;box-sizing:border-box;resize:vertical;" placeholder="メモを入力..."></textarea>'
            + '</div>'
            + '<button id="spot-save-btn" style="display:block;width:100%;text-align:center;padding:8px 12px;background:#f59e0b;color:#fff;border:none;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;">お気に入りに保存</button>'
            + '<p id="spot-save-error" style="font-size:10px;color:#ef4444;margin:4px 0 0;display:none;"></p>'
            + '</div>';
    }

    function bindFormEvents(lat, lng) {
        // ポップアップ内のフォームにイベントをバインド（DOMが描画後に実行）
        setTimeout(function() {
            var saveBtn = document.getElementById('spot-save-btn');
            if (!saveBtn) return;

            saveBtn.addEventListener('click', function() {
                var name = document.getElementById('spot-name').value.trim();
                var memo = document.getElementById('spot-memo').value.trim();
                var category = document.getElementById('spot-category').value;
                var errorEl = document.getElementById('spot-save-error');

                if (!name) {
                    errorEl.textContent = 'スポット名を入力してください';
                    errorEl.style.display = 'block';
                    return;
                }

                saveBtn.textContent = '保存中...';
                saveBtn.disabled = true;

                var csrfToken = document.querySelector('meta[name="csrf-token"]');
                fetch('/api/spots', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken ? csrfToken.content : '',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        name: name,
                        latitude: lat,
                        longitude: lng,
                        memo: memo || null,
                        category: category,
                    }),
                })
                .then(function(r) {
                    if (!r.ok) throw new Error('保存に失敗しました');
                    return r.json();
                })
                .then(function() {
                    removePin();
                    // レイヤー再読込
                    if (typeof window.ridersMapRefresh === 'function') {
                        window.ridersMapRefresh();
                    }
                })
                .catch(function(e) {
                    errorEl.textContent = e.message || '保存に失敗しました';
                    errorEl.style.display = 'block';
                    saveBtn.textContent = 'お気に入りに保存';
                    saveBtn.disabled = false;
                });
            });
        }, 50);
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

    function escapeAttr(str) {
        if (!str) return '';
        return str.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function removePin() {
        if (pinMarker) {
            map.removeLayer(pinMarker);
            pinMarker = null;
        }
        document.getElementById('map').classList.remove('has-spot-popup');
    }

    // ESCキーでモード解除
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (pinMode) {
                deactivate();
            }
            removePin();
        }
    });

    waitForMap(init);
})();
