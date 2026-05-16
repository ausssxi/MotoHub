/**
 * MotoHub ツーリングプランナー
 * Alpine.js コンポーネント + Leaflet マップ描画
 */
function touringPlanner() {
    return {
        origin: '',
        distance: '200',
        preference: 'any',
        loading: false,
        result: null,
        error: null,
        googleMapsUrl: '',
        _map: null,
        _layerGroup: null,
        _prefilledCoords: null,

        init() {
            var params = new URLSearchParams(window.location.search);
            var lat = parseFloat(params.get('lat'));
            var lng = parseFloat(params.get('lng'));
            if (lat && lng && !isNaN(lat) && !isNaN(lng)) {
                this._prefilledCoords = { lat: lat, lng: lng };
                this.reverseGeocode(lat, lng);
            }
        },

        async reverseGeocode(lat, lng) {
            try {
                var url = 'https://mreversegeocoder.gsi.go.jp/reverse-geocoder/LonLatToAddress?lat=' + lat + '&lon=' + lng;
                var res = await fetch(url);
                if (!res.ok) return;
                var data = await res.json();
                var muniCode = data.results ? data.results.mupiCode || data.results.muniCode : null;
                var lv01Nm = data.results ? data.results.lv01Nm : '';
                if (muniCode) {
                    var prefCode = muniCode.substring(0, 2);
                    var name = await this.resolveMuniName(prefCode, muniCode);
                    this.origin = name + (lv01Nm ? lv01Nm : '');
                } else {
                    this.origin = lat.toFixed(3) + ', ' + lng.toFixed(3);
                }
            } catch (e) {
                this.origin = lat.toFixed(3) + ', ' + lng.toFixed(3);
            }
        },

        async resolveMuniName(prefCode, muniCode) {
            var PREF_NAMES = [
                '', 'hokkaido', 'aomori', 'iwate', 'miyagi', 'akita', 'yamagata', 'fukushima',
                'ibaraki', 'tochigi', 'gunma', 'saitama', 'chiba', 'tokyo', 'kanagawa',
                'niigata', 'toyama', 'ishikawa', 'fukui', 'yamanashi', 'nagano', 'gifu',
                'shizuoka', 'aichi', 'mie', 'shiga', 'kyoto', 'osaka', 'hyogo',
                'nara', 'wakayama', 'tottori', 'shimane', 'okayama', 'hiroshima', 'yamaguchi',
                'tokushima', 'kagawa', 'ehime', 'kochi', 'fukuoka', 'saga', 'nagasaki',
                'kumamoto', 'oita', 'miyazaki', 'kagoshima', 'okinawa'
            ];
            var PREF_JP = [
                '', '\u5317\u6d77\u9053', '\u9752\u68ee\u770c', '\u5ca9\u624b\u770c', '\u5bae\u57ce\u770c',
                '\u79cb\u7530\u770c', '\u5c71\u5f62\u770c', '\u798f\u5cf6\u770c', '\u8328\u57ce\u770c',
                '\u6803\u6728\u770c', '\u7fa4\u99ac\u770c', '\u57fc\u7389\u770c', '\u5343\u8449\u770c',
                '\u6771\u4eac\u90fd', '\u795e\u5948\u5ddd\u770c', '\u65b0\u6f5f\u770c', '\u5bcc\u5c71\u770c',
                '\u77f3\u5ddd\u770c', '\u798f\u4e95\u770c', '\u5c71\u68a8\u770c', '\u9577\u91ce\u770c',
                '\u5c90\u961c\u770c', '\u9759\u5ca1\u770c', '\u611b\u77e5\u770c', '\u4e09\u91cd\u770c',
                '\u6ecb\u8cc0\u770c', '\u4eac\u90fd\u5e9c', '\u5927\u962a\u5e9c', '\u5175\u5eab\u770c',
                '\u5948\u826f\u770c', '\u548c\u6b4c\u5c71\u770c', '\u9ce5\u53d6\u770c', '\u5cf6\u6839\u770c',
                '\u5ca1\u5c71\u770c', '\u5e83\u5cf6\u770c', '\u5c71\u53e3\u770c', '\u5fb3\u5cf6\u770c',
                '\u9999\u5ddd\u770c', '\u611b\u5a9b\u770c', '\u9ad8\u77e5\u770c', '\u798f\u5ca1\u770c',
                '\u4f50\u8cc0\u770c', '\u9577\u5d0e\u770c', '\u718a\u672c\u770c', '\u5927\u5206\u770c',
                '\u5bae\u5d0e\u770c', '\u9e7f\u5150\u5cf6\u770c', '\u6c96\u7e04\u770c'
            ];
            var idx = parseInt(prefCode, 10);
            return (PREF_JP[idx] || '') + ' ';
        },

        async suggest() {
            const query = this.origin.trim();
            if (!query) return;

            this.loading = true;
            this.error = null;
            this.result = null;

            try {
                // URLパラメータからの座標がある場合はジオコーディングをスキップ
                var coords = null;
                if (this._prefilledCoords) {
                    coords = this._prefilledCoords;
                    this._prefilledCoords = null; // 2回目以降はテキストからジオコーディング
                } else {
                    coords = await this.geocode(query);
                }
                if (!coords) {
                    this.error = '「' + query + '」の場所が見つかりませんでした。別の地名で試してください。';
                    return;
                }

                // 2. ルート提案APIを呼び出し
                const res = await fetch('/api/touring/suggest', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    },
                    body: JSON.stringify({
                        lat: coords.lat,
                        lng: coords.lng,
                        distance_km: parseInt(this.distance),
                        preference: this.preference,
                        origin_name: query,
                    }),
                });

                const data = await res.json();

                if (!res.ok) {
                    this.error = data.error || 'ルート提案の取得に失敗しました。';
                    return;
                }

                this.result = data;
                this.googleMapsUrl = this.buildGoogleMapsUrl(data.stops);

                // マップ描画はDOMが更新された後に実行
                this.$nextTick(() => this.renderRoute(data));
            } catch (e) {
                this.error = '通信エラーが発生しました。しばらくしてから再度お試しください。';
            } finally {
                this.loading = false;
            }
        },

        /**
         * 国土地理院API ジオコーディング
         */
        async geocode(query) {
            const url = 'https://msearch.gsi.go.jp/address-search/AddressSearch?q=' + encodeURIComponent(query);
            const res = await fetch(url);
            if (!res.ok) return null;

            const results = await res.json();
            if (!results || results.length === 0) return null;

            // クエリとタイトルの一致度でベスト結果を選択
            // （APIは部分一致で無関係な結果が先頭に来ることがある）
            const exact = results.find(r => r.properties?.title === query);
            const partial = results.find(r => r.properties?.title?.includes(query));
            const best = exact || partial || results[results.length - 1];

            // 国土地理院APIは [経度, 緯度] の順
            const coords = best.geometry.coordinates;
            return { lat: coords[1], lng: coords[0] };
        },

        /**
         * Leaflet マップにルートを描画
         */
        renderRoute(data) {
            const container = document.getElementById('planner-map');
            if (!container) return;

            // 既存マップを破棄
            if (this._map) {
                this._map.remove();
                this._map = null;
            }

            const stops = data.stops;
            if (!stops || stops.length === 0) return;

            // マップ初期化
            this._map = L.map('planner-map', { zoomControl: true });
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors',
                maxZoom: 18,
            }).addTo(this._map);

            this._layerGroup = L.layerGroup().addTo(this._map);

            const markerColors = {
                touring_spot: '#3b82f6',
                roadside_station: '#22c55e',
                waypoint: '#9ca3af',
            };

            const latlngs = [];

            stops.forEach((stop, idx) => {
                const color = markerColors[stop.type] || markerColors.waypoint;
                const isStart = idx === 0;
                const isEnd = idx === stops.length - 1 && stop.type === 'waypoint';
                const label = isStart ? 'S' : (isEnd ? 'G' : idx);

                const icon = L.divIcon({
                    className: 'planner-marker',
                    html: '<div style="background:' + color + ';color:#fff;width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;border:2px solid #fff;box-shadow:0 2px 4px rgba(0,0,0,.3);">' + label + '</div>',
                    iconSize: [28, 28],
                    iconAnchor: [14, 14],
                });

                const typeLabel = stop.type === 'touring_spot' ? 'スポット' :
                                  stop.type === 'roadside_station' ? '道の駅' : '経由地';

                const marker = L.marker([stop.lat, stop.lng], { icon: icon })
                    .bindPopup(
                        '<div style="font-size:13px;">' +
                        '<strong>' + this.escapeHtml(stop.name) + '</strong>' +
                        '<span style="font-size:10px;margin-left:4px;color:#6b7280;">' + typeLabel + '</span>' +
                        (stop.note ? '<br><span style="color:#6b7280;font-size:12px;">' + this.escapeHtml(stop.note) + '</span>' : '') +
                        (stop.stay_minutes > 0 ? '<br><span style="color:#9ca3af;font-size:11px;">滞在: ' + stop.stay_minutes + '分</span>' : '') +
                        '</div>',
                        { maxWidth: 250 }
                    );

                this._layerGroup.addLayer(marker);
                latlngs.push([stop.lat, stop.lng]);
            });

            // ストップ間をpolylineで接続
            if (latlngs.length >= 2) {
                const polyline = L.polyline(latlngs, {
                    color: '#3b82f6',
                    weight: 3,
                    opacity: 0.7,
                    dashArray: '8, 6',
                });
                this._layerGroup.addLayer(polyline);
            }

            // 全マーカーが見えるようにfit
            if (latlngs.length > 0) {
                this._map.fitBounds(L.latLngBounds(latlngs), { padding: [30, 30] });
            }
        },

        /**
         * Google Maps directions URL を組み立て
         */
        buildGoogleMapsUrl(stops) {
            if (!stops || stops.length < 2) return '#';

            const origin = stops[0];
            const destination = stops[stops.length - 1];
            const waypoints = stops.slice(1, -1);

            let url = 'https://www.google.com/maps/dir/?api=1';
            url += '&origin=' + origin.lat + ',' + origin.lng;
            url += '&destination=' + destination.lat + ',' + destination.lng;

            if (waypoints.length > 0) {
                const wp = waypoints.map(s => s.lat + ',' + s.lng).join('|');
                url += '&waypoints=' + encodeURIComponent(wp);
            }

            url += '&travelmode=driving';
            return url;
        },

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },
    };
}
