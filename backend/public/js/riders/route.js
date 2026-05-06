/**
 * ライダーズマップ - ルート描画機能
 */
(function() {
    'use strict';

    var map;
    var routeMode = false;
    var waypoints = [];
    var waypointMarkers = [];
    var routingControl = null;
    var routePoiLayer = null;
    var routeLine = null;
    var currentRouteCoords = [];
    var clickHandler = null;

    function waitForMap(cb) {
        if (window.ridersMap) { cb(); return; }
        var t = setInterval(function() {
            if (window.ridersMap) { clearInterval(t); cb(); }
        }, 100);
    }

    function init() {
        map = window.ridersMap;
        routePoiLayer = L.layerGroup().addTo(map);

        var btnToggle = document.getElementById('btn-route-toggle');
        var btnClear = document.getElementById('btn-route-clear');

        if (btnToggle) {
            btnToggle.addEventListener('click', function() {
                toggleRouteMode();
            });
        }
        if (btnClear) {
            btnClear.addEventListener('click', function() {
                clearRoute();
            });
        }
    }

    function toggleRouteMode() {
        routeMode = !routeMode;
        var btnToggle = document.getElementById('btn-route-toggle');
        var btnClear = document.getElementById('btn-route-clear');
        var mapEl = document.getElementById('map');

        if (routeMode) {
            btnToggle.classList.add('active');
            btnToggle.querySelector('.route-btn-label').textContent = 'ルート作成中';
            if (btnClear) btnClear.classList.remove('hidden');
            if (mapEl) mapEl.classList.add('route-mode-active');

            clickHandler = function(e) {
                addWaypoint(e.latlng);
            };
            map.on('click', clickHandler);
        } else {
            btnToggle.classList.remove('active');
            btnToggle.querySelector('.route-btn-label').textContent = 'ルート作成';
            if (btnClear) btnClear.classList.add('hidden');
            if (mapEl) mapEl.classList.remove('route-mode-active');

            if (clickHandler) {
                map.off('click', clickHandler);
                clickHandler = null;
            }
        }
    }

    function waypointIcon(index, total) {
        var color, letter;
        if (index === 0) {
            color = '#16a34a'; letter = 'S';
        } else if (index === total - 1 && total > 1) {
            color = '#dc2626'; letter = 'G';
        } else {
            color = '#ec4899'; letter = '' + (index);
        }
        return L.divIcon({
            className: '',
            html: '<div style="width:28px;height:28px;border-radius:50%;background:' + color + ';color:#fff;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;line-height:1;border:2px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,.3);cursor:grab;">' + letter + '</div>',
            iconSize: [28, 28],
            iconAnchor: [14, 14],
        });
    }

    function addWaypoint(latlng) {
        waypoints.push(latlng);
        rebuildWaypointMarkers();
        if (waypoints.length >= 2) {
            buildRoute();
        }
    }

    function rebuildWaypointMarkers() {
        waypointMarkers.forEach(function(m) { map.removeLayer(m); });
        waypointMarkers = [];

        waypoints.forEach(function(wp, i) {
            var marker = L.marker([wp.lat, wp.lng], {
                icon: waypointIcon(i, waypoints.length),
                draggable: true,
                zIndexOffset: 500,
            }).addTo(map);

            marker.on('dragend', function(e) {
                var pos = e.target.getLatLng();
                waypoints[i] = pos;
                rebuildWaypointMarkers();
                if (waypoints.length >= 2) buildRoute();
            });

            waypointMarkers.push(marker);
        });
    }

    function buildRoute() {
        // Remove existing routing control
        if (routingControl) {
            map.removeControl(routingControl);
            routingControl = null;
        }
        if (routeLine) {
            map.removeLayer(routeLine);
            routeLine = null;
        }

        var wpLatLngs = waypoints.map(function(wp) {
            return L.latLng(wp.lat, wp.lng);
        });

        routingControl = L.Routing.control({
            waypoints: wpLatLngs,
            routeWhileDragging: false,
            addWaypoints: false,
            draggableWaypoints: false,
            show: false,
            createMarker: function() { return null; },
            lineOptions: {
                styles: [{ color: '#ec4899', weight: 5, opacity: 0.8 }],
                addWaypoints: false,
            },
            router: L.Routing.osrmv1({
                serviceUrl: 'https://router.project-osrm.org/route/v1',
                profile: 'car',
            }),
        }).addTo(map);

        routingControl.on('routesfound', function(e) {
            var route = e.routes[0];
            var distKm = (route.summary.totalDistance / 1000).toFixed(1);
            var timeMin = Math.round(route.summary.totalTime / 60);
            var hours = Math.floor(timeMin / 60);
            var mins = timeMin % 60;
            var timeStr = hours > 0 ? hours + '時間' + mins + '分' : mins + '分';

            // Store route coordinates for POI search
            currentRouteCoords = route.coordinates.map(function(c) {
                return [c.lat, c.lng];
            });

            // Update info bar
            var infoBar = document.getElementById('route-info-bar');
            if (infoBar) {
                infoBar.classList.remove('hidden');
                document.getElementById('route-distance').textContent = distKm + ' km';
                document.getElementById('route-time').textContent = timeStr;
                document.getElementById('route-poi-count').textContent = '';
            }

            // Show the along-route POI button
            var btnPoi = document.getElementById('btn-route-pois');
            if (btnPoi) btnPoi.classList.remove('hidden');
        });

        routingControl.on('routingerror', function(e) {
            console.warn('Routing error:', e.error);
        });
    }

    function searchAlongRoute() {
        if (!currentRouteCoords.length) return;

        var btnPoi = document.getElementById('btn-route-pois');
        if (btnPoi) {
            btnPoi.disabled = true;
            btnPoi.textContent = '検索中...';
        }

        // Thin out coordinates to ~500m intervals
        var thinned = thinCoordinates(currentRouteCoords, 500);

        fetch('/api/pois/along-route', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({
                coordinates: thinned,
                buffer_km: 0.3,
                types: ['gas_station', 'convenience_store', 'michi_no_eki'],
            }),
        })
        .then(function(r) { return r.json(); })
        .then(function(pois) {
            routePoiLayer.clearLayers();

            var icons = {
                gas_station:       { color: '#dc2626', label: '\u26FD' },
                convenience_store: { color: '#ea580c', label: '\uD83C\uDFEA' },
                michi_no_eki:      { color: '#9333ea', label: '\uD83D\uDEE3\uFE0F' },
            };

            pois.forEach(function(poi) {
                var cfg = icons[poi.type] || { color: '#6b7280', label: '\u2022' };
                var icon = L.divIcon({
                    className: '',
                    html: '<div style="width:26px;height:26px;border-radius:50%;background:#fff;display:flex;align-items:center;justify-content:center;font-size:14px;line-height:1;border:3px solid ' + cfg.color + ';box-shadow:0 2px 4px rgba(0,0,0,.3);opacity:0.9;">' + cfg.label + '</div>',
                    iconSize: [26, 26],
                    iconAnchor: [13, 13],
                });
                var marker = L.marker([poi.latitude, poi.longitude], { icon: icon }).addTo(routePoiLayer);
                marker.on('click', function() {
                    if (typeof window.ridersMapShowDetail === 'function') {
                        window.ridersMapShowDetail(poi.type, poi);
                    }
                });
            });

            // Update POI count
            var countEl = document.getElementById('route-poi-count');
            if (countEl) countEl.textContent = ' / 沿線スポット ' + pois.length + '件';

            if (btnPoi) {
                btnPoi.disabled = false;
                btnPoi.textContent = '沿線スポット更新';
            }
        })
        .catch(function(err) {
            console.warn('Along-route POI error:', err);
            if (btnPoi) {
                btnPoi.disabled = false;
                btnPoi.textContent = '沿線スポット表示';
            }
        });
    }

    /**
     * Thin out coordinate array to approximately `intervalM` meter intervals
     */
    function thinCoordinates(coords, intervalM) {
        if (coords.length <= 2) return coords;
        var result = [coords[0]];
        var accumulated = 0;

        for (var i = 1; i < coords.length; i++) {
            var d = haversineM(coords[i-1][0], coords[i-1][1], coords[i][0], coords[i][1]);
            accumulated += d;
            if (accumulated >= intervalM) {
                result.push(coords[i]);
                accumulated = 0;
            }
        }
        // Always include last point
        var last = coords[coords.length - 1];
        var rLast = result[result.length - 1];
        if (last[0] !== rLast[0] || last[1] !== rLast[1]) {
            result.push(last);
        }
        return result;
    }

    function haversineM(lat1, lng1, lat2, lng2) {
        var R = 6371000;
        var dLat = (lat2 - lat1) * Math.PI / 180;
        var dLng = (lng2 - lng1) * Math.PI / 180;
        var a = Math.sin(dLat/2) * Math.sin(dLat/2)
            + Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180)
            * Math.sin(dLng/2) * Math.sin(dLng/2);
        return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
    }

    function clearRoute() {
        // Remove routing control
        if (routingControl) {
            map.removeControl(routingControl);
            routingControl = null;
        }
        if (routeLine) {
            map.removeLayer(routeLine);
            routeLine = null;
        }

        // Remove waypoint markers
        waypointMarkers.forEach(function(m) { map.removeLayer(m); });
        waypointMarkers = [];
        waypoints = [];
        currentRouteCoords = [];

        // Clear along-route POIs
        if (routePoiLayer) routePoiLayer.clearLayers();

        // Hide info bar
        var infoBar = document.getElementById('route-info-bar');
        if (infoBar) infoBar.classList.add('hidden');

        // Hide POI button
        var btnPoi = document.getElementById('btn-route-pois');
        if (btnPoi) {
            btnPoi.classList.add('hidden');
            btnPoi.textContent = '沿線スポット表示';
        }

        // Exit route mode if active
        if (routeMode) {
            toggleRouteMode();
        }
    }

    // Expose search function for button
    window.ridersRouteSearchPois = searchAlongRoute;

    waitForMap(init);
})();
