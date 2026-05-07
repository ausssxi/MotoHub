/**
 * ブログ埋め込みライダーズマップ
 * .riders-map-embed 要素を検出し、Leafletマップを初期化する
 */
(function() {
    'use strict';

    var layerConfig = {
        shop:    { endpoint: '/shops/api/area', color: '#2563eb', label: '\uD83C\uDFCD\uFE0F', title: 'ショップ' },
        parking: { endpoint: '/parking/api/search', color: '#16a34a', label: '\uD83C\uDD7F\uFE0F', title: '駐車場' },
        gas_station:       { endpoint: '/api/pois?type=gas_station', color: '#dc2626', label: '\u26FD', title: 'GS' },
        convenience_store: { endpoint: '/api/pois?type=convenience_store', color: '#ea580c', label: '\uD83C\uDFEA', title: 'コンビニ' },
        michi_no_eki:      { endpoint: '/api/pois?type=michi_no_eki', color: '#9333ea', label: '\uD83D\uDEE3\uFE0F', title: '道の駅' },
        blog:              { endpoint: '/api/blog/map-pins', color: '#0891b2', label: '\u270D\uFE0F', title: '記事' },
    };

    function createIcon(color, label) {
        return L.divIcon({
            className: '',
            html: '<div style="width:28px;height:28px;border-radius:50%;background:#fff;display:flex;align-items:center;justify-content:center;font-size:14px;line-height:1;border:3px solid ' + color + ';box-shadow:0 2px 4px rgba(0,0,0,.3);">' + label + '</div>',
            iconSize: [28, 28],
            iconAnchor: [14, 14],
        });
    }

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function initEmbeddedMaps() {
        var containers = document.querySelectorAll('.riders-map-embed');
        if (!containers.length) return;

        containers.forEach(function(el) {
            var lat = parseFloat(el.dataset.lat) || 35.681236;
            var lng = parseFloat(el.dataset.lng) || 139.767125;
            var zoom = parseInt(el.dataset.zoom) || 12;
            var layersStr = el.dataset.layers || 'all';
            var routeStr = el.dataset.route || '';

            var map = L.map(el, { zoomControl: true, scrollWheelZoom: false }).setView([lat, lng], zoom);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
                maxZoom: 18,
            }).addTo(map);

            // スクロールズーム：フォーカス時のみ有効
            el.addEventListener('click', function() { map.scrollWheelZoom.enable(); });
            el.addEventListener('mouseleave', function() { map.scrollWheelZoom.disable(); });

            // 有効レイヤーを決定
            var enabledLayers = getEnabledLayers(layersStr);

            // POI読み込み
            fetchLayers(map, enabledLayers);

            // moveend時にPOI再読み込み
            var debounce = null;
            map.on('moveend', function() {
                clearTimeout(debounce);
                debounce = setTimeout(function() { fetchLayers(map, enabledLayers); }, 400);
            });

            // ルート描画
            if (routeStr) {
                drawRoute(map, routeStr);
            }
        });
    }

    function getEnabledLayers(layersStr) {
        if (layersStr === 'all') {
            return ['gas_station', 'parking', 'michi_no_eki'];
        }
        return layersStr.split(',').map(function(s) { return s.trim(); }).filter(function(s) {
            return layerConfig.hasOwnProperty(s);
        });
    }

    function fetchLayers(map, enabledLayers) {
        var bounds = map.getBounds();
        var ne = bounds.getNorthEast();
        var sw = bounds.getSouthWest();
        var boundsParams = 'ne_lat=' + ne.lat + '&ne_lng=' + ne.lng + '&sw_lat=' + sw.lat + '&sw_lng=' + sw.lng;

        // 既存のPOIレイヤーをクリア
        if (!map._poiLayers) map._poiLayers = {};

        enabledLayers.forEach(function(key) {
            var config = layerConfig[key];
            if (!config) return;

            // レイヤーグループ初期化
            if (!map._poiLayers[key]) {
                map._poiLayers[key] = L.layerGroup().addTo(map);
            }
            var group = map._poiLayers[key];
            group.clearLayers();

            var url = config.endpoint + (config.endpoint.includes('?') ? '&' : '?') + boundsParams;

            fetch(url)
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    var items = Array.isArray(data) ? data : (data.data || data);
                    var icon = createIcon(config.color, config.label);

                    items.forEach(function(item) {
                        var itemLat = parseFloat(item.latitude || item.lat);
                        var itemLng = parseFloat(item.longitude || item.lng);
                        if (!itemLat || !itemLng) return;

                        var marker = L.marker([itemLat, itemLng], { icon: icon }).addTo(group);
                        var name = item.name || item.shop_name || item.title || '名称不明';

                        var popupHtml = '<div style="min-width:160px;">';

                        if (key === 'blog') {
                            popupHtml += '<strong>' + escapeHtml(item.title) + '</strong>';
                            if (item.excerpt) {
                                popupHtml += '<br><span style="font-size:12px;color:#666;">' + escapeHtml(item.excerpt) + '</span>';
                            }
                            popupHtml += '<br><a href="/blog/' + encodeURIComponent(item.slug) + '" style="font-size:12px;color:#0891b2;font-weight:bold;">記事を読む &rarr;</a>';
                        } else {
                            popupHtml += '<strong>' + escapeHtml(name) + '</strong>';
                            if (item.address) {
                                popupHtml += '<br><span style="font-size:12px;color:#666;">' + escapeHtml(item.address) + '</span>';
                            }
                            var gmapUrl = 'https://www.google.com/maps?q=' + itemLat + ',' + itemLng;
                            popupHtml += '<br><a href="' + gmapUrl + '" target="_blank" rel="noopener" style="font-size:12px;color:#2563eb;">Google Map</a>';
                            if (item.id && (key === 'shop' || key === 'parking')) {
                                var detailUrl = key === 'shop' ? '/shops/' + item.id : '/parking/' + item.id;
                                popupHtml += ' | <a href="' + detailUrl + '" style="font-size:12px;color:#2563eb;">詳細</a>';
                            }
                        }

                        popupHtml += '</div>';
                        marker.bindPopup(popupHtml);
                    });
                })
                .catch(function(e) { console.warn('Blog map layer error:', key, e); });
        });
    }

    /**
     * ルート描画: "lat1,lng1;lat2,lng2;..." 形式の座標文字列からルートを描画
     */
    function drawRoute(map, routeStr) {
        var points = routeStr.split(';').map(function(s) {
            var parts = s.trim().split(',');
            if (parts.length < 2) return null;
            var lat = parseFloat(parts[0]);
            var lng = parseFloat(parts[1]);
            return (isNaN(lat) || isNaN(lng)) ? null : L.latLng(lat, lng);
        }).filter(Boolean);

        if (points.length < 2) return;

        L.Routing.control({
            waypoints: points,
            routeWhileDragging: false,
            addWaypoints: false,
            draggableWaypoints: false,
            show: false,
            createMarker: function(i, wp, n) {
                var color, letter;
                if (i === 0) { color = '#16a34a'; letter = 'S'; }
                else if (i === n - 1) { color = '#dc2626'; letter = 'G'; }
                else { color = '#ec4899'; letter = '' + i; }

                return L.marker(wp.latLng, {
                    icon: L.divIcon({
                        className: '',
                        html: '<div style="width:28px;height:28px;border-radius:50%;background:' + color + ';color:#fff;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;line-height:1;border:2px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,.3);">' + letter + '</div>',
                        iconSize: [28, 28],
                        iconAnchor: [14, 14],
                    }),
                });
            },
            lineOptions: {
                styles: [{ color: '#ec4899', weight: 5, opacity: 0.8 }],
                addWaypoints: false,
            },
            router: L.Routing.osrmv1({
                serviceUrl: 'https://router.project-osrm.org/route/v1',
                profile: 'car',
            }),
        }).addTo(map);
    }

    // DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initEmbeddedMaps);
    } else {
        initEmbeddedMaps();
    }
})();
