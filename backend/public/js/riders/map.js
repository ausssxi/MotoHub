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
        michi_no_eki:      { endpoint: '/api/roadside-stations', color: '#9333ea', label: '\uD83D\uDEE3\uFE0F', title: '道の駅' },
        rental_garage:     { endpoint: '/api/rental-garages', color: '#7c3aed', label: '\uD83C\uDFE0', title: 'レンタルガレージ' },
        blog:              { endpoint: '/api/blog/map-pins', color: '#0891b2', label: '\u270D\uFE0F', title: '記事' },
        car_wash:          { endpoint: '/api/pois?type=car_wash', color: '#0ea5e9', label: '\uD83D\uDEBF', title: '洗車場' },
        saved_spots:       { endpoint: '/api/spots', color: '#f59e0b', label: '\u2B50', title: 'お気に入り' },
    };

    let map;
    // 全種類を1つのクラスタグループに集約（種類横断でまとまる＝Googleマップ的挙動）。
    // markercluster(CDN)が未ロードなら L.layerGroup にフォールバック（地図は動く・非クラスタ）。
    let clusterGroup = null;
    let allMarkers = [];
    let debounceTimer = null;
    let userLat = null, userLng = null;
    let userMarker = null;
    let distanceLimitKm = 0; // 0 = no limit
    let fetchGeneration = 0;

    // チェーン別アイコン定義（slug=サーバーが config/bike.php pattern で付与）。
    // ★ロゴ画像は使わない（商標回避）＝色＋短ラベルで識別。
    const CHAIN_ICONS = {
        'red-baron':      { label: 'RB',  color: '#dc2626' },
        'bikeo':          { label: '王',  color: '#16a34a' },
        'bikekan':        { label: '館',  color: '#7c3aed' },
        'kawasaki-plaza': { label: 'KP',  color: '#15803d' },
        'ysp':            { label: 'YSP', color: '#1d4ed8' },
        'sbs':            { label: 'SBS', color: '#ea580c' },
        'sox':            { label: 'SOX', color: '#4f46e5' },
        'naps':           { label: 'ナ',  color: '#db2777' },
        'ricoland':       { label: 'ラ',  color: '#ca8a04' },
        'bikeland':       { label: 'BL',  color: '#0d9488' },
        'scs':            { label: 'SCS', color: '#0e7490' },
        'honda-dream':    { label: 'HD',  color: '#b91c1c' },
        'reverse-auto':   { label: 'RA',  color: '#0369a1' },
    };
    var chainIconCache = {};
    var makerIconCache = {};

    // メーカー正規ディーラー（別カテゴリ・輸入ブランドのみ）。チェーン（色塗り丸）と区別するため「角丸四角」形。
    // ★キーは config/bike.php maker_dealer_brands と一致（サーバーが item.maker_dealer にブランドキーを返す）。
    //   キーワード照合はしない＝サーバーが正規化・allowlist判定済のキーだけを渡す。国産/その他/未知は null（=その他ピン）。
    var BRAND_DEALERS = {
        'harley':        { label: 'H-D', color: '#111827' },
        'triumph':       { label: 'TRI', color: '#7f1d1d' },
        'bmw':           { label: 'BMW', color: '#1e3a8a' },
        'ktm':           { label: 'KTM', color: '#c2410c' },
        'ducati':        { label: 'DUC', color: '#9d174d' },
        'husqvarna':     { label: 'HQV', color: '#1e40af' },
        'vespa':         { label: 'VES', color: '#0d9488' },
        'kymco':         { label: 'KYM', color: '#047857' },
        'aprilia':       { label: 'APR', color: '#7e22ce' },
        'indian':        { label: 'IND', color: '#78350f' },
        'royal-enfield': { label: 'RE',  color: '#3f3f46' },
        'beta':          { label: 'BET', color: '#b45309' },
    };
    function makerIconFor(key) {
        var d = BRAND_DEALERS[key];
        if (!d) return null; // 未知キーは通常来ない（サーバーは既知キーのみ返す）。保険で null＝従来アイコン。
        if (makerIconCache[key]) return makerIconCache[key];
        var icon = L.divIcon({
            className: '',
            html: '<div style="width:28px;height:28px;border-radius:6px;background:' + d.color + ';color:#fff;display:flex;align-items:center;justify-content:center;font-size:9px;font-weight:900;line-height:1;border:2px solid #fff;box-shadow:0 2px 4px rgba(0,0,0,.35);">' + d.label + '</div>',
            iconSize: [28, 28],
            iconAnchor: [14, 14],
        });
        makerIconCache[key] = icon;
        return icon;
    }

    // チェーン別ピン（色塗り丸＋白ラベル）。従来のSHOPピン（白丸＋色border）と見分けやすい反転配色。
    function chainIconFor(slug) {
        if (chainIconCache[slug]) return chainIconCache[slug];
        var c = CHAIN_ICONS[slug];
        var icon = L.divIcon({
            className: '',
            html: '<div style="width:30px;height:30px;border-radius:50%;background:' + c.color + ';color:#fff;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:900;line-height:1;border:2px solid #fff;box-shadow:0 2px 4px rgba(0,0,0,.35);">' + c.label + '</div>',
            iconSize: [30, 30],
            iconAnchor: [15, 15],
        });
        chainIconCache[slug] = icon;
        return icon;
    }

    // 駐車場レイヤー専用の運営ブランドアイコン（ショップの CHAIN_ICONS/BRAND_DEALERS とは別系統）。
    // 形状はショップと区別するため「横長の角丸タグ」。キーはサーバー(BikeParking::parkingBrand)が返す:
    //   'akippa'/'ecostation'=固有色 / 'other'=その他運営（共通色）/ null=自治体名・無し（=従来の緑🅿️）。
    var PARKING_BRAND_ICONS = {
        'akippa':     { label: 'ak',  color: '#e4007f' }, // マゼンタ（akippa）
        'ecostation': { label: 'eco', color: '#2563eb' }, // ブルー（エコステーション21）
        'other':      { label: 'P',   color: '#f59e0b' }, // アンバー（その他運営バッジ）
    };
    var parkingBrandIconCache = {};
    function parkingBrandIconFor(key) {
        var b = PARKING_BRAND_ICONS[key];
        if (!b) return null; // 未知キー/null は従来の緑🅿️
        if (parkingBrandIconCache[key]) return parkingBrandIconCache[key];
        var icon = L.divIcon({
            className: '',
            html: '<div style="width:34px;height:22px;border-radius:6px;background:' + b.color + ';color:#fff;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:900;line-height:1;border:2px solid #fff;box-shadow:0 2px 4px rgba(0,0,0,.35);">' + b.label + '</div>',
            iconSize: [34, 22],
            iconAnchor: [17, 11],
        });
        parkingBrandIconCache[key] = icon;
        return icon;
    }

    // GS(ガソリンスタンド)レイヤー専用の運営ブランドアイコン（ショップ/駐車場とは別系統）。
    // 形状は区別のため「ひし形（回転した角丸四角）」。キーはサーバー(Poi::gasBrand)が返す:
    //   eneos/idemitsu/cosmo/ja-ss/hokuren/kygnus/solato=固有色 / 'other'=その他運営（グレー）
    //   / null=brand不明（=従来の赤⛽）。'exclude'（非GS）はサーバー側で除外済みで届かない。
    var GAS_BRAND_ICONS = {
        'eneos':    { label: 'EN', color: '#f97316' }, // オレンジ
        'idemitsu': { label: '出', color: '#be123c' }, // クリムゾン
        'cosmo':    { label: 'コ', color: '#0891b2' }, // シアン
        'ja-ss':    { label: 'JA', color: '#15803d' }, // グリーン（農協系）
        'hokuren':  { label: 'ホ', color: '#7c3aed' }, // パープル
        'kygnus':   { label: 'キ', color: '#0d9488' }, // ティール
        'solato':   { label: '太', color: '#ca8a04' }, // ゴールド
        'other':    { label: '油', color: '#64748b' }, // グレー（その他運営）
    };
    var gasBrandIconCache = {};
    function gasBrandIconFor(key) {
        var b = GAS_BRAND_ICONS[key];
        if (!b) return null; // 未知キー/null は従来の赤⛽
        if (gasBrandIconCache[key]) return gasBrandIconCache[key];
        var icon = L.divIcon({
            className: '',
            html: '<div style="width:34px;height:34px;display:flex;align-items:center;justify-content:center;">'
                + '<div style="width:22px;height:22px;transform:rotate(45deg);border-radius:5px;background:' + b.color + ';border:2px solid #fff;box-shadow:0 2px 4px rgba(0,0,0,.35);display:flex;align-items:center;justify-content:center;">'
                + '<span style="transform:rotate(-45deg);color:#fff;font-size:9px;font-weight:900;line-height:1;">' + b.label + '</span>'
                + '</div></div>',
            iconSize: [34, 34],
            iconAnchor: [17, 17],
        });
        gasBrandIconCache[key] = icon;
        return icon;
    }

    // コンビニレイヤー専用の運営ブランドアイコン（GSひし形・ショップ丸・駐車場タグと区別＝「六角形」）。
    // キーはサーバー(Poi::cvsBrand)が返す。'other'=その他チェーン（グレー）/ null=不明（従来アイコン）。
    var CVS_BRAND_ICONS = {
        'seven':          { label: '7',  color: '#16a34a' }, // グリーン
        'familymart':     { label: 'FM', color: '#1e40af' }, // 紺
        'lawson':         { label: 'LW', color: '#0ea5e9' }, // 空色
        'ministop':       { label: 'MS', color: '#ca8a04' }, // 金
        'daily-yamazaki': { label: 'DY', color: '#dc2626' }, // 赤
        'seicomart':      { label: 'セ', color: '#ea580c' }, // 橙
        'newdays':        { label: 'ND', color: '#7c3aed' }, // 紫
        'other':          { label: '他', color: '#64748b' }, // グレー（その他チェーン）
    };
    var cvsBrandIconCache = {};
    function cvsBrandIconFor(key) {
        var b = CVS_BRAND_ICONS[key];
        if (!b) return null; // 未知キー/null は従来アイコン
        if (cvsBrandIconCache[key]) return cvsBrandIconCache[key];
        var hex = 'polygon(25% 0%, 75% 0%, 100% 50%, 75% 100%, 25% 100%, 0% 50%)';
        var icon = L.divIcon({
            className: '',
            html: '<div style="width:30px;height:28px;background:' + b.color + ';-webkit-clip-path:' + hex + ';clip-path:' + hex + ';filter:drop-shadow(0 1px 2px rgba(0,0,0,.4));display:flex;align-items:center;justify-content:center;color:#fff;font-size:9px;font-weight:900;line-height:1;">' + b.label + '</div>',
            iconSize: [30, 28],
            iconAnchor: [15, 14],
        });
        cvsBrandIconCache[key] = icon;
        return icon;
    }

    // レンタルガレージ専用アイコン（種別で border 色を出し分け・形状は従来の丸🏠）。
    // キーはサーバー(RentalGarage.garage_type): indoor/container/open/other=固有色 / 未知のみ defaultIcon。
    // ※ 凡例(map.blade.php)の色と二重管理。変更時は両方揃えること。
    var GARAGE_TYPE_ICONS = {
        'indoor':    '#7c3aed', // 屋内ガレージ（紫）
        'container': '#f59e0b', // 屋外コンテナ（橙）
        'open':      '#64748b', // 青空月極（灰）
        'other':     '#db2777', // その他・不明（ローズ）。どのレイヤー色とも非衝突・上記3色と明確に別相
    };
    var garageIconCache = {};
    function garageIconFor(type) {
        var color = GARAGE_TYPE_ICONS[type];
        if (!color) return null; // 未知の型のみ defaultIcon（=layerConfig.color の紫）
        if (garageIconCache[type]) return garageIconCache[type];
        var icon = createIcon(color, '\uD83C\uDFE0');
        garageIconCache[type] = icon;
        return icon;
    }

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

        map = L.map('map', { zoomControl: false }).setView([lat, lng], zoom);
        L.control.zoom({ position: 'bottomleft' }).addTo(map);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
            maxZoom: 18,
        }).addTo(map);

        // Expose map instance globally (for route.js etc.)
        window.ridersMap = map;

        // Initialize a single marker cluster group for all categories.
        // ズームアウト時は数字バッジに集約、ズームインで個別ピンに展開。CDN未ロード時は素の layerGroup。
        clusterGroup = (typeof L.markerClusterGroup === 'function')
            ? L.markerClusterGroup({
                maxClusterRadius: 50,
                showCoverageOnHover: false,
                spiderfyOnMaxZoom: true,
                chunkedLoading: true,
            })
            : L.layerGroup();
        clusterGroup.addTo(map);

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
            var btn = document.getElementById('btn-current-location');
            btn.classList.add('text-blue-600');
            navigator.geolocation.getCurrentPosition(function(pos) {
                userLat = pos.coords.latitude;
                userLng = pos.coords.longitude;
                map.setView([userLat, userLng], 14);
                setUserMarker(userLat, userLng);
                showDistanceFilter();
            }, function() {
                btn.classList.remove('text-blue-600');
            });
        });

        // Distance filter change
        document.getElementById('distance-select').addEventListener('change', function() {
            distanceLimitKm = parseInt(this.value) || 0;
            updateCards();
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
        var gen = ++fetchGeneration;
        var layers = window.ridersMapLayers || { shop: true, parking: true, gas_station: false, convenience_store: false, michi_no_eki: false, car_wash: false, rental_garage: false, blog: false, saved_spots: false };
        var bounds = map.getBounds();
        var ne = bounds.getNorthEast();
        var sw = bounds.getSouthWest();
        var boundsParams = 'ne_lat=' + ne.lat + '&ne_lng=' + ne.lng + '&sw_lat=' + sw.lat + '&sw_lng=' + sw.lng;

        var loading = document.getElementById('map-loading');
        loading.classList.remove('hidden');

        allMarkers = [];
        clusterGroup.clearLayers(); // 単一クラスタを一度クリアし、有効レイヤーのみ再投入
        var promises = [];

        Object.keys(layerConfig).forEach(function(key) {
            if (!layers[key]) return;

            var config = layerConfig[key];
            var url = config.endpoint + (config.endpoint.includes('?') ? '&' : '?') + boundsParams;

            var fetchOpts = key === 'saved_spots' ? { credentials: 'same-origin' } : {};
            promises.push(
                fetch(url, fetchOpts)
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (gen !== fetchGeneration) return; // stale fetch
                        var items = Array.isArray(data) ? data : (data.data || data);
                        processLayerData(key, items);
                    })
                    .catch(function(e) { console.warn('Layer fetch error:', key, e); })
            );
        });

        Promise.all(promises).then(function() {
            if (gen !== fetchGeneration) return;
            loading.classList.add('hidden');
            updateCards();
        });
    }

    // Process fetched data for a layer（マーカーは単一クラスタグループへ投入）
    function processLayerData(layerKey, items) {
        var config = layerConfig[layerKey];
        var defaultIcon = createIcon(config.color, config.label);

        items.forEach(function(item) {
            var lat = parseFloat(item.latitude || item.lat);
            var lng = parseFloat(item.longitude || item.lng);
            if (!lat || !lng) return;

            // SHOPピンのみ分類差し替え: チェーン別 → メーカー正規店 → その他(従来アイコン)。他レイヤーは従来のまま。
            var icon = defaultIcon;
            if (layerKey === 'shop') {
                if (item.chain && CHAIN_ICONS[item.chain]) {
                    icon = chainIconFor(item.chain);
                } else if (item.maker_dealer) {
                    var mi = makerIconFor(item.maker_dealer);
                    if (mi) icon = mi; // 既知ブランドキーのみ（null=従来アイコン）
                }
            } else if (layerKey === 'parking') {
                if (item.brand) {
                    var pi = parkingBrandIconFor(item.brand);
                    if (pi) icon = pi; // 自治体名/無し（brand=null）は従来の緑🅿️
                }
            } else if (layerKey === 'gas_station') {
                if (item.gas_brand) {
                    var gi = gasBrandIconFor(item.gas_brand);
                    if (gi) icon = gi; // brand不明（gas_brand=null）は従来の赤⛽
                }
            } else if (layerKey === 'convenience_store') {
                if (item.cvs_brand) {
                    var ci = cvsBrandIconFor(item.cvs_brand);
                    if (ci) icon = ci; // brand不明（cvs_brand=null）は従来アイコン
                }
            } else if (layerKey === 'rental_garage') {
                if (item.garage_type) {
                    var ri = garageIconFor(item.garage_type);
                    if (ri) icon = ri; // other/未知は従来の紫🏠（defaultIcon）
                }
            }

            var marker = L.marker([lat, lng], { icon: icon }).addTo(clusterGroup);
            marker.on('click', function() { showDetail(layerKey, item); });

            var displayName = resolveName(layerKey, item);
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

        // Compute distance for each marker
        var filtered = allMarkers;
        if (userLat && userLng) {
            filtered.forEach(function(m) {
                m._dist = getDistance(userLat, userLng, m.lat, m.lng);
            });
            // Apply distance filter
            if (distanceLimitKm > 0) {
                filtered = filtered.filter(function(m) { return m._dist <= distanceLimitKm; });
            }
            // Sort by distance (nearest first)
            filtered.sort(function(a, b) { return a._dist - b._dist; });
        }

        // Count text
        if (userLat && userLng && distanceLimitKm > 0) {
            countEl.textContent = '現在地から' + distanceLimitKm + 'km圏内に' + filtered.length + '件';
        } else {
            countEl.textContent = '地図内に' + filtered.length + '件';
        }

        if (filtered.length === 0) {
            container.innerHTML = '<div class="flex items-center justify-center w-full text-sm text-gray-400">' +
                (distanceLimitKm > 0 ? distanceLimitKm + 'km圏内にスポットが見つかりません' : 'この範囲にスポットが見つかりません') +
                '</div>';
            return;
        }

        var html = '';
        filtered.slice(0, 50).forEach(function(m) {
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
            if (item.operator) meta.push(item.operator);
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
            if (item.gas_operator) {
                lines += '<p class="text-[10px] font-bold text-red-600 mt-0.5">' + escapeHtml(item.gas_operator) + '</p>';
            }
            var gsSubtitle = gsDisplayName(item).sub;
            if (gsSubtitle) {
                lines += '<p class="text-[10px] font-bold text-gray-600 mt-0.5">' + escapeHtml(gsSubtitle) + '</p>';
            }
            lines += '<p class="text-[10px] text-gray-400 truncate mt-0.5">' + escapeHtml(item.address || '') + '</p>';
            if (item.opening_hours) {
                lines += '<p class="text-[10px] text-gray-500 mt-0.5">' + escapeHtml(item.opening_hours) + '</p>';
            }
        } else if (layerKey === 'convenience_store') {
            if (item.cvs_operator) {
                lines += '<p class="text-[10px] font-bold text-gray-600 mt-0.5">' + escapeHtml(item.cvs_operator) + '</p>';
            }
            lines += '<p class="text-[10px] text-gray-400 truncate mt-0.5">' + escapeHtml(item.address || '') + '</p>';
        } else if (layerKey === 'michi_no_eki') {
            lines += '<p class="text-[10px] text-gray-400 truncate mt-0.5">' + escapeHtml(item.address || '') + '</p>';
            lines += michiNoEkiFacilities(item);
        } else if (layerKey === 'rental_garage') {
            lines += '<p class="text-[10px] text-gray-400 truncate mt-0.5">' + escapeHtml(item.address || '') + '</p>';
            var gMeta = [garageTypeLabel(item.garage_type)];
            if (item.operator) gMeta.push(item.operator);
            var gFee2 = garageFeeDisplay(item);
            if (gFee2) gMeta.push(gFee2);
            lines += '<p class="text-[10px] text-gray-500 mt-0.5">' + escapeHtml(gMeta.join(' / ')) + '</p>';
        } else if (layerKey === 'blog') {
            if (item.excerpt) {
                lines += '<p class="text-[10px] text-gray-500 truncate mt-0.5">' + escapeHtml(item.excerpt) + '</p>';
            }
            if (item.published_at) {
                lines += '<p class="text-[10px] text-gray-400 mt-0.5">' + escapeHtml(item.published_at) + (item.type === 'touring' ? ' [ガイド]' : '') + '</p>';
            }
        } else if (layerKey === 'saved_spots') {
            if (item.memo) {
                lines += '<p class="text-[10px] text-gray-500 truncate mt-0.5">' + escapeHtml(item.memo) + '</p>';
            }
            var catLabels = { touring_spot: 'ツーリングスポット', rest_area: '休憩所', scenic_point: '絶景ポイント', other: 'その他' };
            lines += '<p class="text-[10px] text-gray-400 mt-0.5">' + escapeHtml(catLabels[item.category] || 'ツーリングスポット') + '</p>';
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
    // 表示名フォールバックは共通実装（poi-name.js の window.MotoHub.resolvePoiName）を使う。
    // 未読込時の保険として種別名も返せないケースは空文字に留める。
    function resolveName(layerKey, item) {
        if (window.MotoHub && typeof window.MotoHub.resolvePoiName === 'function') {
            return window.MotoHub.resolvePoiName(layerKey, item);
        }
        return (item && item.name) || (layerConfig[layerKey] && layerConfig[layerKey].title) || '';
    }

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
        var base = name || brand || resolveName(item.type, item);
        if (item.address) {
            var loc = shortLocation(item.address);
            if (loc) return { main: base + '（' + loc + '）', sub: '' };
        }
        if (!name && brand) return { main: brand, sub: '' };
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

    // レンタルガレージ種別ラベル（RentalGarage::garageTypeLabel と揃える）
    function garageTypeLabel(type) {
        var labels = { indoor: '屋内ガレージ', container: '屋外コンテナ', open: '青空月極', other: 'その他' };
        return labels[type] || 'その他';
    }

    // 月額表示。両方=「min〜max円」/ min のみ=「min円〜」/ max のみ=「〜max円」/ 無し=''。
    function garageFeeDisplay(item) {
        var min = item.monthly_fee_min, max = item.monthly_fee_max;
        var has = function(v) { return v !== null && v !== undefined && v !== ''; };
        var fmt = function(v) { return Number(v).toLocaleString(); };
        if (has(min) && has(max)) return fmt(min) + '〜' + fmt(max) + '円';
        if (has(min)) return fmt(min) + '円〜';
        if (has(max)) return '〜' + fmt(max) + '円';
        return '';
    }

    // OSM opening_hours が24時間営業を示すなら 24hバッジHTMLを返す（"24/7" / "24時間"）
    function open24hBadge(item) {
        var oh = (item.opening_hours || '').trim();
        if (/24\/7/.test(oh) || /24\s*時間/.test(oh)) {
            return '<span class="inline-block px-2.5 py-1 bg-emerald-50 text-emerald-700 text-[11px] font-bold rounded-md mb-3 ml-1">24時間営業</span>';
        }
        return '';
    }

    // OSM由来データ（GS/コンビニ/洗車場）の ODbL クレジット（詳細パネル内表記）
    function osmDataCredit() {
        return '<p class="text-[10px] text-gray-400 mt-3">データ: &copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener" class="underline">OpenStreetMap contributors</a> (ODbL)</p>';
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
            if (item.operator) {
                html += '<div class="flex items-start gap-2"><span class="text-[10px] font-bold text-gray-400 w-14 shrink-0 pt-0.5">運営</span><span class="text-xs text-gray-700">' + escapeHtml(item.operator) + '</span></div>';
            }
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
                + (item.gas_operator ? '<span class="inline-block px-2.5 py-1 bg-red-50 text-red-700 text-[11px] font-bold rounded-md mb-3">運営: ' + escapeHtml(item.gas_operator) + '</span>' : '')
                + open24hBadge(item)
                + (item.address ? '<p class="text-xs text-gray-500 mb-3">' + escapeHtml(item.address) + '</p>' : '')
                + (item.opening_hours ? '<div class="bg-gray-50 rounded-lg p-3 mb-3"><div class="flex items-start gap-2"><span class="text-[10px] font-bold text-gray-400 w-14 shrink-0 pt-0.5">営業時間</span><span class="text-xs text-gray-700">' + escapeHtml(item.opening_hours) + '</span></div></div>' : '')
                + gmapBtn + routeBtn + osmDataCredit();
        } else if (layerKey === 'convenience_store') {
            var cvs = gsDisplayName(item);
            html = '<h3 class="text-base font-black text-gray-900 mb-2">' + escapeHtml(cvs.main) + '</h3>'
                + (item.cvs_operator ? '<span class="inline-block px-2.5 py-1 bg-gray-100 text-gray-700 text-[11px] font-bold rounded-md mb-3">' + escapeHtml(item.cvs_operator) + '</span>' : '')
                + open24hBadge(item)
                + (item.address ? '<p class="text-xs text-gray-500 mb-3">' + escapeHtml(item.address) + '</p>' : '')
                + (item.opening_hours ? '<div class="bg-gray-50 rounded-lg p-3 mb-3"><div class="flex items-start gap-2"><span class="text-[10px] font-bold text-gray-400 w-14 shrink-0 pt-0.5">営業時間</span><span class="text-xs text-gray-700">' + escapeHtml(item.opening_hours) + '</span></div></div>' : '')
                + gmapBtn + routeBtn + osmDataCredit();
        } else if (layerKey === 'car_wash') {
            // A1修正: car_wash の分岐が無く本文が空だった。pois が持つ name/brand/address/opening_hours で構成。
            html = '<h3 class="text-base font-black text-gray-900 mb-1">' + escapeHtml(resolveName('car_wash', item)) + '</h3>'
                + (item.brand ? '<span class="inline-block px-2.5 py-1 bg-sky-50 text-sky-700 text-[11px] font-bold rounded-md mb-3">' + escapeHtml(item.brand) + '</span>' : '')
                + open24hBadge(item)
                + (item.address ? '<p class="text-xs text-gray-500 mb-3">' + escapeHtml(item.address) + '</p>' : '')
                + (item.opening_hours ? '<div class="bg-gray-50 rounded-lg p-3 mb-3"><div class="flex items-start gap-2"><span class="text-[10px] font-bold text-gray-400 w-14 shrink-0 pt-0.5">営業時間</span><span class="text-xs text-gray-700">' + escapeHtml(item.opening_hours) + '</span></div></div>' : '')
                + gmapBtn + routeBtn + osmDataCredit();
        } else if (layerKey === 'rental_garage') {
            html = '<h3 class="text-base font-black text-gray-900 mb-2">' + escapeHtml(resolveName('rental_garage', item)) + '</h3>'
                + '<span class="inline-block px-2.5 py-1 bg-purple-50 text-purple-700 text-[11px] font-bold rounded-md mb-3">' + escapeHtml(garageTypeLabel(item.garage_type)) + '</span>'
                + (item.address ? '<p class="text-xs text-gray-500 mb-3">' + escapeHtml(item.address) + '</p>' : '');
            // 情報テーブル（運営・月額・サイズ）
            var gRows = '';
            if (item.operator) {
                gRows += '<div class="flex items-start gap-2"><span class="text-[10px] font-bold text-gray-400 w-14 shrink-0 pt-0.5">運営</span><span class="text-xs text-gray-700">' + escapeHtml(item.operator) + '</span></div>';
            }
            var gFee = garageFeeDisplay(item);
            if (gFee) {
                gRows += '<div class="flex items-start gap-2"><span class="text-[10px] font-bold text-gray-400 w-14 shrink-0 pt-0.5">月額（区画により変動）</span><span class="text-xs text-gray-700">' + escapeHtml(gFee) + '</span></div>';
            }
            if (item.size_text) {
                gRows += '<div class="flex items-start gap-2"><span class="text-[10px] font-bold text-gray-400 w-14 shrink-0 pt-0.5">サイズ</span><span class="text-xs text-gray-700">' + escapeHtml(item.size_text) + '</span></div>';
            }
            if (gRows) {
                html += '<div class="bg-gray-50 rounded-lg p-3 mb-3 space-y-2">' + gRows + '</div>';
            }
            // 設備バッジ
            var gBadges = [];
            if (item.is_24h) gBadges.push('24時間出入り可');
            if (item.has_power) gBadges.push('電源あり');
            if (item.has_security) gBadges.push('防犯設備');
            if (item.has_shutter) gBadges.push('シャッター付');
            if (gBadges.length) {
                html += '<div class="flex flex-wrap gap-1 mb-3">' + gBadges.map(function(b) { return '<span class="px-2 py-0.5 bg-gray-100 text-gray-600 text-[10px] font-bold rounded">' + b + '</span>'; }).join('') + '</div>';
            }
            // MotoHub内の詳細ページ（公式サイトリンクはページ内に配置）。既存の /parking/ 等と同じ内部パス方式。
            if (item.id) {
                html += '<a href="/rental-garages/' + item.id + '" class="flex items-center justify-center gap-1.5 w-full px-4 py-2.5 bg-purple-600 text-white text-xs font-bold rounded-lg hover:bg-purple-700 transition">詳細を見る &rarr;</a>';
            }
            html += gmapBtn + routeBtn;
        } else if (layerKey === 'michi_no_eki') {
            html = '';
            // サムネイル画像
            if (item.image_url) {
                html += '<div class="rounded-lg overflow-hidden mb-3 bg-gray-100">'
                    + '<img src="' + escapeHtml(item.image_url) + '" alt="' + escapeHtml(item.name) + '" class="w-full h-32 object-cover" loading="lazy" onerror="this.parentElement.style.display=\'none\'">'
                    + '</div>';
            }
            html += '<h3 class="text-base font-black text-gray-900 mb-1">' + escapeHtml(item.name) + '</h3>';
            if (item.address) {
                html += '<p class="text-xs text-gray-500 mb-3">' + escapeHtml(item.address) + '</p>';
            }
            // 施設アイコン一覧
            var facilities = michiNoEkiFacilitiesDetail(item);
            if (facilities) {
                html += '<div class="bg-gray-50 rounded-lg p-3 mb-3">'
                    + '<p class="text-[10px] font-bold text-gray-400 mb-2">施設・設備</p>'
                    + '<div class="flex flex-wrap gap-1.5">' + facilities + '</div>'
                    + '</div>';
            }
            // 概要
            if (item.summary) {
                html += '<p class="text-xs text-gray-600 mb-3 leading-relaxed">' + escapeHtml(item.summary) + '</p>';
            }
            // 導線は MotoHub の詳細ページ一本に絞る（public/js は URL ハードコードの例外）。
            // station_code は先頭ゼロを含む5桁文字列。/^\d{5}$/ に一致する場合のみ詳細ページへ
            // （/michinoeki/undefined を絶対に生成しない）。
            // まとめサイトへ流出する公式URL欄はどの分岐でも一切出力しない。
            var michiCode = String(item.station_code || '');
            if (/^\d{5}$/.test(michiCode)) {
                html += '<a href="/michinoeki/' + michiCode + '" class="flex items-center justify-center gap-1.5 w-full px-4 py-2.5 bg-purple-600 text-white text-xs font-bold rounded-lg hover:bg-purple-700 transition">詳細ページを見る &rarr;</a>';
            } else if (item.wikipedia_url) {
                // station_code 不正時のみ Wikipedia を副次リンクとして出す（公式URL欄は使わない）。
                html += '<a href="' + escapeHtml(item.wikipedia_url) + '" target="_blank" rel="noopener" class="flex items-center justify-center gap-1.5 w-full px-4 py-2.5 bg-gray-100 text-gray-700 text-xs font-bold rounded-lg hover:bg-gray-200 transition">詳しく見る &rarr;</a>';
            }
            html += gmapBtn + routeBtn;
        } else if (layerKey === 'blog') {
            var blogUrl = item.type === 'touring' ? '/touring/' : '/blog/';
            var blogLabel = item.type === 'touring' ? 'ガイドを読む' : '記事を読む';
            html = '<h3 class="text-base font-black text-gray-900 mb-2">' + escapeHtml(item.title) + '</h3>'
                + (item.excerpt ? '<p class="text-sm text-gray-600 mb-3 leading-relaxed">' + escapeHtml(item.excerpt) + '</p>' : '')
                + '<p class="text-[10px] text-gray-400 mb-3">' + escapeHtml(item.published_at || '') + '</p>'
                + '<a href="' + blogUrl + encodeURIComponent(item.slug) + '" class="flex items-center justify-center gap-1.5 w-full px-4 py-2.5 bg-cyan-600 text-white text-xs font-bold rounded-lg hover:bg-cyan-700 transition">' + blogLabel + ' &rarr;</a>';
        } else if (layerKey === 'saved_spots') {
            var spotCatLabels = { touring_spot: 'ツーリングスポット', rest_area: '休憩所', scenic_point: '絶景ポイント', other: 'その他' };
            html = '<h3 class="text-base font-black text-gray-900 mb-2">' + escapeHtml(item.name) + '</h3>'
                + '<span class="inline-block px-2.5 py-1 bg-amber-50 text-amber-700 text-[11px] font-bold rounded-md mb-3">' + escapeHtml(spotCatLabels[item.category] || 'ツーリングスポット') + '</span>'
                + (item.memo ? '<p class="text-sm text-gray-600 mb-3 leading-relaxed">' + escapeHtml(item.memo) + '</p>' : '')
                + '<p class="text-[10px] text-gray-400 mb-3">保存日: ' + escapeHtml(item.created_at || '') + '</p>'
                + gmapBtn + routeBtn
                + '<button onclick="window.ridersMapDeleteSpot(' + item.id + ')" class="flex items-center justify-center gap-1.5 w-full px-4 py-2.5 bg-red-50 text-red-600 text-xs font-bold rounded-lg hover:bg-red-100 transition mt-2">このスポットを削除</button>';
        }

        body.innerHTML = html;
        panel.classList.add('open');
        overlay.classList.add('open');
    }

    // Expose for card click
    window.ridersMapShowDetail = showDetail;

    // Delete saved spot
    window.ridersMapDeleteSpot = function(id) {
        if (!confirm('このスポットを削除しますか？')) return;
        var csrfToken = document.querySelector('meta[name="csrf-token"]');
        fetch('/api/spots/' + id, {
            method: 'DELETE',
            credentials: 'same-origin',
            headers: {
                'X-CSRF-TOKEN': csrfToken ? csrfToken.content : '',
                'Accept': 'application/json',
            },
        })
        .then(function(r) { return r.json(); })
        .then(function() {
            closePanel();
            fetchAllLayers();
        })
        .catch(function(e) { console.warn('Delete spot error:', e); });
    };

    // Expose fetchAllLayers for spot-pin.js
    window.ridersMapRefresh = fetchAllLayers;

    // Show current location marker
    function setUserMarker(lat, lng) {
        if (userMarker) map.removeLayer(userMarker);
        var icon = L.divIcon({
            className: '',
            html: '<div style="width:16px;height:16px;border-radius:50%;background:#3b82f6;border:3px solid #fff;box-shadow:0 0 0 2px #3b82f6,0 2px 6px rgba(0,0,0,.3);"></div>',
            iconSize: [16, 16],
            iconAnchor: [8, 8],
        });
        userMarker = L.marker([lat, lng], { icon: icon, zIndexOffset: 1000 }).addTo(map);
        userMarker.bindTooltip('現在地', { direction: 'top', offset: [0, -10] });
    }

    // Show distance filter UI
    function showDistanceFilter() {
        var el = document.getElementById('distance-filter');
        if (el) el.style.display = '';
    }

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

    // 道の駅施設アイコン（カード用・コンパクト版）
    function michiNoEkiFacilities(item) {
        var icons = [];
        if (item.has_restaurant) icons.push('\uD83C\uDF7D');
        if (item.has_onsen)      icons.push('\u2668\uFE0F');
        if (item.has_ev_charging) icons.push('\u26A1');
        if (item.has_wifi)       icons.push('\uD83D\uDCF6');
        if (item.has_shower)     icons.push('\uD83D\uDEBF');
        if (item.has_gas_station) icons.push('\u26FD');
        if (item.has_shop)       icons.push('\uD83C\uDFEA');
        if (!icons.length) return '';
        return '<p class="text-[10px] mt-0.5">' + icons.join(' ') + '</p>';
    }

    // 道の駅施設バッジ（詳細パネル用）
    function michiNoEkiFacilitiesDetail(item) {
        var list = [
            { key: 'has_restaurant',  icon: '\uD83C\uDF7D', label: 'レストラン' },
            { key: 'has_onsen',       icon: '\u2668\uFE0F',  label: '温泉' },
            { key: 'has_ev_charging', icon: '\u26A1',        label: 'EV充電' },
            { key: 'has_wifi',        icon: '\uD83D\uDCF6', label: 'WiFi' },
            { key: 'has_shower',      icon: '\uD83D\uDEBF', label: 'シャワー' },
            { key: 'has_gas_station', icon: '\u26FD',        label: 'GS' },
            { key: 'has_shop',        icon: '\uD83C\uDFEA', label: 'ショップ' },
        ];
        var html = '';
        list.forEach(function(f) {
            if (item[f.key]) {
                html += '<span class="inline-flex items-center gap-1 px-2 py-0.5 bg-white rounded text-[10px] font-bold text-gray-600 border border-gray-200">' + f.icon + ' ' + f.label + '</span>';
            }
        });
        return html;
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
