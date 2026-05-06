/**
 * ライダーズマップ - 統合マップJS
 */
(function() {
    'use strict';

    // Layer configuration
    const layerConfig = {
        shop:    { endpoint: '/shops/api/area', color: '#2563eb', label: '\uD83C\uDFCD\uFE0F', title: 'ショップ' },
        parking: { endpoint: '/parking/api/search', color: '#16a34a', label: '\uD83C\uDD7F\uFE0F', title: '駐車場' },
        gas_station:       { endpoint: '/api/pois?type=gas_station', color: '#dc2626', label: '\u26FD', title: 'GS' },
        convenience_store: { endpoint: '/api/pois?type=convenience_store', color: '#ea580c', label: '\uD83C\uDFEA', title: 'コンビニ' },
        michi_no_eki:      { endpoint: '/api/pois?type=michi_no_eki', color: '#9333ea', label: '\uD83D\uDEE3\uFE0F', title: '道の駅' },
    };

    let map;
    let layerGroups = {};
    let allMarkers = [];
    let debounceTimer = null;
    let userLat = null, userLng = null;

    // Create circular div icon with emoji, white bg + colored border
    function createIcon(color, label) {
        return L.divIcon({
            className: '',
            html: '<div style="width:30px;height:30px;border-radius:50%;background:#fff;display:flex;align-items:center;justify-content:center;font-size:16px;line-height:1;border:3px solid ' + color + ';box-shadow:0 2px 4px rgba(0,0,0,.3);">' + label + '</div>',
            iconSize: [30, 30],
            iconAnchor: [15, 15],
        });
    }

    // Initialize map
    function initMap() {
        var params = new URLSearchParams(window.location.search);
        var lat = parseFloat(params.get('lat')) || 35.681236;
        var lng = parseFloat(params.get('lng')) || 139.767125;
        var zoom = parseInt(params.get('zoom')) || 13;

        map = L.map('map', { zoomControl: true }).setView([lat, lng], zoom);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
            maxZoom: 18,
        }).addTo(map);

        // Initialize layer groups
        Object.keys(layerConfig).forEach(function(key) {
            layerGroups[key] = L.layerGroup().addTo(map);
        });

        // Map move handler
        map.on('moveend', function() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(fetchAllLayers, 300);
        });

        // Initial fetch
        fetchAllLayers();

        // Current location button
        document.getElementById('btn-current-location').addEventListener('click', function() {
            if (!navigator.geolocation) return;
            navigator.geolocation.getCurrentPosition(function(pos) {
                userLat = pos.coords.latitude;
                userLng = pos.coords.longitude;
                map.setView([userLat, userLng], 14);
            });
        });

        // Listen for layer toggle changes from Alpine
        window.addEventListener('layers-changed', function(e) {
            if (e.detail) window.ridersMapLayers = e.detail;
            fetchAllLayers();
        });

        // Detail panel close
        document.getElementById('detail-panel-close').addEventListener('click', closePanel);
        document.getElementById('detail-panel-overlay').addEventListener('click', closePanel);

        // 地名検索を初期化
        if (typeof initMapSearch === 'function') {
            initMapSearch(map);
        }
    }

    // Fetch all enabled layers
    function fetchAllLayers() {
        var layers = window.ridersMapLayers || { shop: true, parking: true, gas_station: false, convenience_store: false, michi_no_eki: false };
        var bounds = map.getBounds();
        var ne = bounds.getNorthEast();
        var sw = bounds.getSouthWest();
        var boundsParams = 'ne_lat=' + ne.lat + '&ne_lng=' + ne.lng + '&sw_lat=' + sw.lat + '&sw_lng=' + sw.lng;

        var loading = document.getElementById('map-loading');
        loading.classList.remove('hidden');

        allMarkers = [];
        var promises = [];

        Object.keys(layerConfig).forEach(function(key) {
            var group = layerGroups[key];
            group.clearLayers();

            if (!layers[key]) return;

            var config = layerConfig[key];
            var url = config.endpoint + (config.endpoint.includes('?') ? '&' : '?') + boundsParams;

            promises.push(
                fetch(url)
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        var items = Array.isArray(data) ? data : (data.data || data);
                        processLayerData(key, items, group);
                    })
                    .catch(function(e) { console.warn('Layer fetch error:', key, e); })
            );
        });

        Promise.all(promises).then(function() {
            loading.classList.add('hidden');
            updateCards();
        });
    }

    // Process fetched data for a layer
    function processLayerData(layerKey, items, group) {
        var config = layerConfig[layerKey];
        var icon = createIcon(config.color, config.label);

        items.forEach(function(item) {
            var lat = parseFloat(item.latitude || item.lat);
            var lng = parseFloat(item.longitude || item.lng);
            if (!lat || !lng) return;

            var marker = L.marker([lat, lng], { icon: icon }).addTo(group);
            marker.on('click', function() { showDetail(layerKey, item); });

            var displayName = item.name || item.shop_name || '名称不明';
            if (layerKey === 'gas_station' || layerKey === 'convenience_store') {
                displayName = gsDisplayName(item).main;
            }

            allMarkers.push({
                layerKey: layerKey,
                data: item,
                lat: lat,
                lng: lng,
                name: displayName,
            });
        });
    }

    // Update card slider
    function updateCards() {
        var container = document.getElementById('result-cards');
        var countEl = document.getElementById('result-count');

        // Sort by distance if user location available
        if (userLat && userLng) {
            allMarkers.sort(function(a, b) {
                return getDistance(userLat, userLng, a.lat, a.lng) - getDistance(userLat, userLng, b.lat, b.lng);
            });
        }

        countEl.textContent = '地図内に' + allMarkers.length + '件';

        if (allMarkers.length === 0) {
            container.innerHTML = '<div class="flex items-center justify-center w-full text-sm text-gray-400">この範囲にスポットが見つかりません</div>';
            return;
        }

        var html = '';
        allMarkers.slice(0, 50).forEach(function(m) {
            var config = layerConfig[m.layerKey];
            var dist = '';
            if (userLat && userLng) {
                var d = getDistance(userLat, userLng, m.lat, m.lng);
                dist = d < 1 ? Math.round(d * 1000) + 'm' : d.toFixed(1) + 'km';
            }
            html += '<div class="snap-start shrink-0 w-64 bg-white rounded-xl border border-gray-100 p-3 cursor-pointer hover:shadow-md transition-shadow" onclick=\'window.ridersMapShowDetail("' + m.layerKey + '",' + JSON.stringify(m.data).replace(/'/g, "\\'") + ')\'>'
                + '<div class="flex items-center gap-2 mb-1.5">'
                + '<span class="w-5 h-5 rounded-full flex items-center justify-center text-xs leading-none" style="border:2px solid ' + config.color + ';background:#fff">' + config.label + '</span>'
                + '<span class="text-[10px] font-bold text-gray-400">' + config.title + '</span>'
                + (dist ? '<span class="text-[10px] text-gray-400 ml-auto">' + dist + '</span>' : '')
                + '</div>'
                + '<p class="text-xs font-bold text-gray-800 truncate">' + escapeHtml(m.name) + '</p>'
                + buildCardDetail(m.layerKey, m.data)
                + '</div>';
        });
        container.innerHTML = html;
    }

    // Build card detail lines per layer type
    function buildCardDetail(layerKey, item) {
        var lines = '';
        if (layerKey === 'shop') {
            lines += '<p class="text-[10px] text-gray-400 truncate mt-0.5">' + escapeHtml(item.address || item.prefecture || '') + '</p>';
            if (item.listings_count) {
                lines += '<p class="text-[10px] font-bold text-blue-600 mt-1">在庫 ' + item.listings_count + '台</p>';
            }
        } else if (layerKey === 'parking') {
            lines += '<p class="text-[10px] text-gray-400 truncate mt-0.5">' + escapeHtml(item.address || '') + '</p>';
            var meta = [];
            if (item.parking_type) meta.push(parkingTypeLabel(item.parking_type));
            var price = priceDisplay(item);
            if (price) meta.push(price);
            if (meta.length) {
                lines += '<p class="text-[10px] text-gray-500 mt-0.5">' + escapeHtml(meta.join(' / ')) + '</p>';
            }
            if (item.avg_rating && parseFloat(item.avg_rating) > 0) {
                lines += '<p class="text-[10px] text-yellow-600 mt-0.5">' + buildStars(item.avg_rating) + ' ' + parseFloat(item.avg_rating).toFixed(1) + '</p>';
            }
        } else if (layerKey === 'gas_station') {
            var gsSubtitle = gsDisplayName(item).sub;
            if (gsSubtitle) {
                lines += '<p class="text-[10px] font-bold text-gray-600 mt-0.5">' + escapeHtml(gsSubtitle) + '</p>';
            }
            lines += '<p class="text-[10px] text-gray-400 truncate mt-0.5">' + escapeHtml(item.address || '') + '</p>';
            if (item.opening_hours) {
                lines += '<p class="text-[10px] text-gray-500 mt-0.5">' + escapeHtml(item.opening_hours) + '</p>';
            }
        } else if (layerKey === 'convenience_store') {
            lines += '<p class="text-[10px] text-gray-400 truncate mt-0.5">' + escapeHtml(item.address || '') + '</p>';
        } else if (layerKey === 'michi_no_eki') {
            lines += '<p class="text-[10px] text-gray-400 truncate mt-0.5">' + escapeHtml(item.address || '') + '</p>';
        }
        return lines;
    }

    // Extract short location from address (市区町村+町名 部分)
    // "東京都渋谷区神宮前" → "渋谷区神宮前"
    function shortLocation(address) {
        if (!address) return '';
        // Remove prefecture prefix (2-4 chars ending with 都道府県)
        var loc = address.replace(/^.{2,3}[都道府県]/, '');
        return loc || address;
    }

    // GS/convenience store display name
    // nameがbrandと異なる具体名 → nameをそのまま使用
    // name == brand or nameなし → brand（市区町村名）形式
    function gsDisplayName(item) {
        var name = item.name || '';
        var brand = item.brand || '';

        // nameが具体的な店舗名（brandと異なる）
        if (name && brand && name !== brand && name.indexOf(brand) === -1) {
            return { main: name, sub: brand };
        }

        // nameにbrandが含まれている場合はnameをそのまま使用
        if (name && name !== brand && name.indexOf(brand) !== -1) {
            return { main: name, sub: '' };
        }

        // name == brand or nameなし → brand（場所）形式
        var base = name || brand || '名称不明';
        if (item.address) {
            var loc = shortLocation(item.address);
            if (loc) return { main: base + '（' + loc + '）', sub: '' };
        }
        if (!name && brand) return { main: brand + '（名称不明）', sub: '' };
        return { main: base, sub: '' };
    }

    // Star rating display
    function buildStars(rating) {
        var r = Math.round(parseFloat(rating));
        var s = '';
        for (var i = 1; i <= 5; i++) s += i <= r ? '\u2605' : '\u2606';
        return s;
    }

    // Parking type label (matches BikeParking model)
    function parkingTypeLabel(type) {
        var labels = { bike_only: 'バイク専用', car_shared: '四輪と共用', bicycle_shared: '自転車と共用', other: 'その他' };
        return labels[type] || 'その他';
    }

    // Parking price display (matches BikeParking model)
    function priceDisplay(item) {
        if (item.is_free) return '無料';
        var parts = [];
        if (item.price_per_hour) parts.push(Number(item.price_per_hour).toLocaleString() + '円/時');
        if (item.price_per_day) parts.push(Number(item.price_per_day).toLocaleString() + '円/日');
        if (item.price_per_month) parts.push(Number(item.price_per_month).toLocaleString() + '円/月');
        return parts.length ? parts.join(' / ') : '';
    }

    // Show detail panel
    function showDetail(layerKey, item) {
        var panel = document.getElementById('detail-panel');
        var overlay = document.getElementById('detail-panel-overlay');
        var body = document.getElementById('detail-panel-body');
        var title = document.getElementById('detail-panel-title');

        var config = layerConfig[layerKey];
        title.textContent = config.title + '詳細';

        var html = '';
        var lat = item.latitude || item.lat;
        var lng = item.longitude || item.lng;
        var gmapBtn = '<a href="https://www.google.com/maps?q=' + lat + ',' + lng + '" target="_blank" rel="noopener" class="flex items-center justify-center gap-1.5 w-full px-4 py-2.5 bg-blue-50 text-blue-700 text-xs font-bold rounded-lg hover:bg-blue-100 transition mt-3">Google マップで開く</a>';
        var routeBtn = '<a href="https://www.google.com/maps/dir/?api=1&destination=' + lat + ',' + lng + '" target="_blank" rel="noopener" class="flex items-center justify-center gap-1.5 w-full px-4 py-2.5 bg-gray-100 text-gray-700 text-xs font-bold rounded-lg hover:bg-gray-200 transition mt-2">ルート案内</a>';

        if (layerKey === 'shop') {
            html = '<h3 class="text-base font-black text-gray-900 mb-2">' + escapeHtml(item.name || item.shop_name) + '</h3>'
                + '<p class="text-xs text-gray-500 mb-3">' + escapeHtml(item.address || item.prefecture || '') + '</p>'
                + (item.listings_count ? '<p class="text-sm text-gray-700 mb-3">在庫 <span class="font-black text-blue-600">' + item.listings_count + '台</span></p>' : '')
                + (item.id ? '<a href="/shops/' + item.id + '" class="flex items-center justify-center gap-1.5 w-full px-4 py-2.5 bg-blue-600 text-white text-xs font-bold rounded-lg hover:bg-blue-700 transition">在庫を見る</a>' : '');
        } else if (layerKey === 'parking') {
            html = '<h3 class="text-base font-black text-gray-900 mb-2">' + escapeHtml(item.name) + '</h3>'
                + '<p class="text-xs text-gray-500 mb-3">' + escapeHtml(item.address || '') + '</p>';
            // タイプバッジ
            if (item.parking_type) {
                html += '<span class="inline-block px-2.5 py-1 bg-green-50 text-green-700 text-[11px] font-bold rounded-md mb-3">' + escapeHtml(parkingTypeLabel(item.parking_type)) + '</span>';
            }
            // 情報テーブル
            html += '<div class="bg-gray-50 rounded-lg p-3 mb-3 space-y-2">';
            if (item.available_hours) {
                html += '<div class="flex items-start gap-2"><span class="text-[10px] font-bold text-gray-400 w-14 shrink-0 pt-0.5">営業時間</span><span class="text-xs text-gray-700">' + escapeHtml(item.available_hours) + '</span></div>';
            }
            if (item.capacity) {
                html += '<div class="flex items-center gap-2"><span class="text-[10px] font-bold text-gray-400 w-14 shrink-0">収容台数</span><span class="text-xs text-gray-700">' + item.capacity + '台</span></div>';
            }
            var price = priceDisplay(item);
            if (price) {
                html += '<div class="flex items-start gap-2"><span class="text-[10px] font-bold text-gray-400 w-14 shrink-0 pt-0.5">料金</span><span class="text-xs text-gray-700">' + escapeHtml(price) + '</span></div>';
            }
            if (item.price_detail) {
                html += '<div class="flex items-start gap-2"><span class="text-[10px] font-bold text-gray-400 w-14 shrink-0 pt-0.5">料金詳細</span><span class="text-xs text-gray-700">' + escapeHtml(item.price_detail) + '</span></div>';
            }
            html += '</div>';
            // 設備バッジ
            var badges = [];
            if (item.is_covered) badges.push('屋根あり');
            if (item.is_locked) badges.push('施錠可');
            if (badges.length) {
                html += '<div class="flex flex-wrap gap-1 mb-3">' + badges.map(function(b) { return '<span class="px-2 py-0.5 bg-gray-100 text-gray-600 text-[10px] font-bold rounded">' + b + '</span>'; }).join('') + '</div>';
            }
            // 評価
            if (item.avg_rating && parseFloat(item.avg_rating) > 0) {
                html += '<div class="flex items-center gap-2 mb-3"><span class="text-sm text-yellow-500">' + buildStars(item.avg_rating) + '</span><span class="text-xs font-bold text-gray-700">' + parseFloat(item.avg_rating).toFixed(1) + '</span><span class="text-[10px] text-gray-400">(' + (item.reviews_count || 0) + '件)</span></div>';
            }
            // ボタン
            if (item.id) {
                html += '<a href="/parking/' + item.id + '" class="flex items-center justify-center gap-1.5 w-full px-4 py-2.5 bg-green-600 text-white text-xs font-bold rounded-lg hover:bg-green-700 transition">詳細を見る</a>';
                html += '<a href="/parking/' + item.id + '#reviews" class="flex items-center justify-center gap-1.5 w-full px-4 py-2.5 bg-gray-100 text-gray-700 text-xs font-bold rounded-lg hover:bg-gray-200 transition mt-2">レビューを見る・書く</a>';
            }
            html += routeBtn;
        } else if (layerKey === 'gas_station') {
            var gs = gsDisplayName(item);
            html = '<h3 class="text-base font-black text-gray-900 mb-1">' + escapeHtml(gs.main) + '</h3>'
                + (gs.sub ? '<p class="text-sm font-bold text-gray-500 mb-2">' + escapeHtml(gs.sub) + '</p>' : '')
                + (item.address ? '<p class="text-xs text-gray-500 mb-3">' + escapeHtml(item.address) + '</p>' : '')
                + (item.opening_hours ? '<div class="bg-gray-50 rounded-lg p-3 mb-3"><div class="flex items-start gap-2"><span class="text-[10px] font-bold text-gray-400 w-14 shrink-0 pt-0.5">営業時間</span><span class="text-xs text-gray-700">' + escapeHtml(item.opening_hours) + '</span></div></div>' : '')
                + gmapBtn + routeBtn;
        } else if (layerKey === 'convenience_store') {
            var cvs = gsDisplayName(item);
            html = '<h3 class="text-base font-black text-gray-900 mb-2">' + escapeHtml(cvs.main) + '</h3>'
                + (item.address ? '<p class="text-xs text-gray-500 mb-3">' + escapeHtml(item.address) + '</p>' : '')
                + gmapBtn + routeBtn;
        } else if (layerKey === 'michi_no_eki') {
            html = '<h3 class="text-base font-black text-gray-900 mb-2">' + escapeHtml(item.name) + '</h3>'
                + (item.address ? '<p class="text-xs text-gray-500 mb-3">' + escapeHtml(item.address) + '</p>' : '')
                + gmapBtn + routeBtn;
        }

        body.innerHTML = html;
        panel.classList.add('open');
        overlay.classList.add('open');
    }

    // Expose for card click
    window.ridersMapShowDetail = showDetail;

    function closePanel() {
        document.getElementById('detail-panel').classList.remove('open');
        document.getElementById('detail-panel-overlay').classList.remove('open');
    }

    // Haversine distance (km)
    function getDistance(lat1, lng1, lat2, lng2) {
        var R = 6371;
        var dLat = (lat2 - lat1) * Math.PI / 180;
        var dLng = (lng2 - lng1) * Math.PI / 180;
        var a = Math.sin(dLat/2) * Math.sin(dLat/2)
            + Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180)
            * Math.sin(dLng/2) * Math.sin(dLng/2);
        return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
    }

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    // Init on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initMap);
    } else {
        initMap();
    }
})();
