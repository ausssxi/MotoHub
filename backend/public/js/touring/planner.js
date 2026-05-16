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

        async suggest() {
            const query = this.origin.trim();
            if (!query) return;

            this.loading = true;
            this.error = null;
            this.result = null;

            try {
                // 1. 国土地理院APIで地名→座標変換
                const coords = await this.geocode(query);
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

            // 最初の結果を使用（国土地理院APIは [経度, 緯度] の順）
            const coords = results[0].geometry.coordinates;
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
