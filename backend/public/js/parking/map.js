document.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    const latParam = urlParams.get('lat');
    const lngParam = urlParams.get('lng');

    const defaultLat = latParam ? parseFloat(latParam) : 35.681236;
    const defaultLng = lngParam ? parseFloat(lngParam) : 139.767125;
    const defaultZoom = latParam ? 15 : 13;

    const map = L.map('map', { zoomControl: false }).setView([defaultLat, defaultLng], defaultZoom);
    L.control.zoom({ position: 'bottomleft' }).addTo(map);

    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>'
    }).addTo(map);

    let markers = [];
    let parkingsCache = [];
    let debounceTimer = null;
    let isPanning = false;
    const loading = document.getElementById('map-loading');
    const cardsContainer = document.getElementById('parking-cards');
    const countEl = document.getElementById('parking-count');

    // ── Detail Panel ──
    const panel = document.getElementById('detail-panel');
    const panelBody = document.getElementById('detail-panel-body');
    const panelOverlay = document.getElementById('detail-panel-overlay');
    const panelClose = document.getElementById('detail-panel-close');

    const openPanel = (p) => {
        panelBody.innerHTML = buildPanelContent(p);
        panel.classList.add('open');
        panelOverlay.classList.add('open');
        // パネル上部にスクロールリセット
        panel.scrollTop = 0;
    };

    const closePanel = () => {
        panel.classList.remove('open');
        panelOverlay.classList.remove('open');
    };

    panelClose.addEventListener('click', closePanel);
    panelOverlay.addEventListener('click', closePanel);

    const buildPanelContent = (p) => {
        const rating = ratingStars(p.avg_rating);
        const usedInfo = p.used_count > 0 ? `<span style="font-size:10px;color:#9ca3af;">${p.used_count}人が使った</span>` : '';
        const reviewAction = (p.reviews_count > 0)
            ? `<p style="font-size:12px;color:#9ca3af;margin:0;">${p.reviews_count}件のレビュー</p>`
            : `<a href="/parking/${p.id}#review-detail-form" style="font-size:12px;color:#3b82f6;text-decoration:none;">最初のレビューを投稿する →</a>`;

        const detailRows = [];
        const rowStyle = 'display:flex;justify-content:space-between;font-size:12px;padding:4px 0;';
        const labelStyle = 'color:#9ca3af;';
        const valueStyle = 'color:#374151;font-weight:700;';
        if (p.available_hours) detailRows.push(`<div style="${rowStyle}"><span style="${labelStyle}">営業時間</span><span style="${valueStyle}">${p.available_hours}</span></div>`);
        if (p.capacity) detailRows.push(`<div style="${rowStyle}"><span style="${labelStyle}">収容台数</span><span style="${valueStyle}">${p.capacity}台</span></div>`);
        const price = p.price_detail || priceDisplay(p);
        detailRows.push(`<div style="${rowStyle}"><span style="${labelStyle}">料金</span><span style="${valueStyle}">${price}</span></div>`);

        const badges = [];
        if (p.is_covered) badges.push('屋根あり');
        if (p.is_locked) badges.push('施錠あり');

        return `
            <div style="margin-bottom:12px;">
                <h3 style="font-size:16px;font-weight:900;color:#111827;margin:0 0 4px 0;">${p.name}</h3>
                <p style="font-size:12px;color:#9ca3af;margin:0 0 12px 0;">${p.address || p.prefecture || ''}</p>
                <span style="display:inline-block;background:#dcfce7;color:#15803d;font-size:11px;padding:4px 12px;border-radius:6px;font-weight:700;">
                    ${parkingTypeLabel(p.parking_type)}
                </span>
                ${badges.length ? badges.map(b => `<span style="display:inline-block;background:#f3f4f6;color:#6b7280;font-size:10px;padding:2px 8px;border-radius:4px;margin-left:4px;">${b}</span>`).join('') : ''}
            </div>

            <div style="background:#f9fafb;border-radius:12px;padding:12px;margin-bottom:12px;">
                ${detailRows.join('')}
            </div>

            <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
                ${rating}
                ${usedInfo}
            </div>
            ${reviewAction}

            <div style="display:flex;flex-direction:column;gap:8px;margin-top:16px;padding-bottom:16px;">
                <a href="https://www.google.com/maps/dir/?api=1&destination=${p.latitude},${p.longitude}" target="_blank" rel="noopener"
                   style="display:flex;align-items:center;justify-content:center;gap:8px;width:100%;background:#2563eb;color:#fff;font-size:14px;font-weight:700;padding:12px 0;border-radius:12px;text-decoration:none;text-align:center;">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l5.447 2.724A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                    ルート案内（Google マップ）
                </a>
                <a href="/parking/${p.id}" target="_blank"
                   style="display:block;width:100%;background:#16a34a;color:#fff;font-size:14px;font-weight:700;padding:12px 0;border-radius:12px;text-decoration:none;text-align:center;">
                    詳細ページを見る
                </a>
            </div>
        `;
    };

    // ── Helpers ──
    const getSelectedParkingType = () => {
        const checked = document.querySelector('input[name="parking_type"]:checked');
        return checked ? checked.value : '';
    };

    const parkingTypeLabel = (type) => {
        const labels = {
            bike_only: 'バイク専用',
            car_shared: '四輪と共用',
            bicycle_shared: '自転車と共用',
            other: 'その他'
        };
        return labels[type] || 'その他';
    };

    const priceDisplay = (p) => {
        if (p.is_free) return '無料';
        const parts = [];
        if (p.price_per_hour) parts.push(Number(p.price_per_hour).toLocaleString() + '円/時');
        if (p.price_per_day) parts.push(Number(p.price_per_day).toLocaleString() + '円/日');
        if (p.price_per_month) parts.push(Number(p.price_per_month).toLocaleString() + '円/月');
        return parts.length ? parts.join(' / ') : '料金不明';
    };

    const ratingStars = (rating) => {
        if (!rating || rating === 0) return '';
        const full = Math.round(rating);
        let stars = '';
        for (let i = 0; i < 5; i++) {
            stars += i < full ? '★' : '☆';
        }
        return `<span class="text-yellow-500 text-xs">${stars}</span> <span class="text-[10px] text-gray-400">${Number(rating).toFixed(1)}</span>`;
    };

    // ── 距離計算 ──
    const getDistance = (center, parking) => {
        const R = 6371000;
        const dLat = (parking.latitude - center.lat) * Math.PI / 180;
        const dLon = (parking.longitude - center.lng) * Math.PI / 180;
        const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
            Math.cos(center.lat * Math.PI / 180) * Math.cos(parking.latitude * Math.PI / 180) *
            Math.sin(dLon / 2) * Math.sin(dLon / 2);
        return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    };

    const getDistanceText = (center, parking) => {
        const d = getDistance(center, parking);
        return d < 1000 ? `${Math.round(d)}m` : `${(d / 1000).toFixed(1)}km`;
    };

    // ── マーカーアイコン ──
    const createParkingIcon = (hasReviews) => {
        const color = hasReviews ? '#eab308' : '#9ca3af';
        return L.divIcon({
            html: `<div style="background:${color};width:28px;height:28px;border-radius:50%;border:3px solid white;box-shadow:0 2px 6px rgba(0,0,0,.3);display:flex;align-items:center;justify-content:center;color:white;font-weight:bold;font-size:12px;">P</div>`,
            className: '',
            iconSize: [28, 28],
            iconAnchor: [14, 14]
        });
    };

    // ── カード描画 ──
    const renderCards = (parkings, center) => {
        if (!cardsContainer) return;

        if (parkings.length === 0) {
            cardsContainer.innerHTML = '<div class="flex items-center justify-center w-full text-sm text-gray-400">この範囲に駐車場はありません</div>';
            if (countEl) countEl.textContent = '地図内に0件';
            return;
        }

        parkings.sort((a, b) => getDistance(center, a) - getDistance(center, b));

        cardsContainer.innerHTML = parkings.map(p => {
            const dist = getDistanceText(center, p);
            const rating = p.avg_rating > 0 ? `<span class="text-xs font-bold text-yellow-600 ml-2">★${Number(p.avg_rating).toFixed(1)}</span>` : '';
            const price = p.price_detail || priceDisplay(p);

            return `<div class="parking-card snap-start shrink-0 w-[200px] sm:w-[260px] bg-white rounded-xl border border-gray-200 p-3 sm:p-4 shadow-sm hover:shadow-md transition-all cursor-pointer" data-id="${p.id}" data-lat="${p.latitude}" data-lng="${p.longitude}">
                <div class="flex items-start justify-between mb-1">
                    <h3 class="text-xs sm:text-sm font-black text-gray-800 line-clamp-1 flex-1">${p.name}</h3>
                    ${rating}
                </div>
                ${p.capacity ? `<div class="text-[10px] sm:text-xs text-blue-600 font-bold mb-1">🅿️ ${p.capacity}台</div>` : ''}
                <div class="text-[10px] text-gray-500 mb-1">📍 中心から${dist}</div>
                <div class="text-[10px] text-gray-600 line-clamp-2">💰 ${price}</div>
                ${p.available_hours ? `<div class="text-[10px] text-gray-500 mt-0.5">🕐 ${p.available_hours}</div>` : ''}
            </div>`;
        }).join('');

        if (countEl) countEl.textContent = `地図内に${parkings.length}件`;

        // カードクリック → パン + パネル表示
        cardsContainer.querySelectorAll('.parking-card').forEach(card => {
            card.addEventListener('click', () => {
                const id = parseInt(card.dataset.id, 10);
                const lat = parseFloat(card.dataset.lat);
                const lng = parseFloat(card.dataset.lng);

                isPanning = true;
                map.setView([lat, lng], Math.max(map.getZoom(), 16));
                setTimeout(() => { isPanning = false; }, 500);

                // パネル表示
                const parking = parkingsCache.find(p => p.id === id);
                if (parking) openPanel(parking);

                // アクティブ状態
                cardsContainer.querySelectorAll('.parking-card').forEach(c => c.classList.remove('parking-card-active'));
                card.classList.add('parking-card-active');
            });
        });
    };

    // ── データ取得 + マーカー描画 + カード描画 ──
    const fetchParkings = async () => {
        if (loading) loading.classList.remove('hidden');

        const bounds = map.getBounds();
        const center = map.getCenter();
        const params = new URLSearchParams({
            ne_lat: bounds.getNorthEast().lat,
            ne_lng: bounds.getNorthEast().lng,
            sw_lat: bounds.getSouthWest().lat,
            sw_lng: bounds.getSouthWest().lng,
        });

        const parkingType = getSelectedParkingType();
        if (parkingType) params.set('parking_type', parkingType);

        try {
            const response = await fetch(`/parking/api/search?${params.toString()}`);
            if (!response.ok) throw new Error('API Error');

            const parkings = await response.json();
            parkingsCache = parkings;

            // マーカー更新
            markers.forEach(m => map.removeLayer(m));
            markers = [];

            parkings.forEach(p => {
                const hasReviews = p.reviews_count > 0;
                const marker = L.marker([p.latitude, p.longitude], { icon: createParkingIcon(hasReviews) }).addTo(map);

                // マーカークリック → パネル表示
                marker.on('click', () => {
                    openPanel(p);
                });

                markers.push(marker);
            });

            // カード描画
            renderCards(parkings, center);

        } catch (error) {
            console.error('Failed to fetch parkings:', error);
        } finally {
            if (loading) loading.classList.add('hidden');
        }
    };

    // デバウンス付きmoveend（カードクリックによるパン中はスキップ）
    const debouncedFetch = () => {
        if (isPanning) return;
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(fetchParkings, 300);
    };

    map.on('moveend', debouncedFetch);
    fetchParkings();

    // フィルタ変更時に再検索
    document.querySelectorAll('input[name="parking_type"]').forEach(radio => {
        radio.addEventListener('change', fetchParkings);
    });

    // 現在地ボタン
    const btnCurrent = document.getElementById('btn-current-location');
    if (btnCurrent) {
        btnCurrent.addEventListener('click', () => {
            if (!navigator.geolocation) {
                alert('お使いのブラウザは現在地取得に対応していません。');
                return;
            }
            navigator.geolocation.getCurrentPosition((pos) => {
                map.setView([pos.coords.latitude, pos.coords.longitude], 15);
            }, () => {
                alert('現在地を取得できませんでした。');
            });
        });
    }

    // 地名検索を初期化
    if (typeof initMapSearch === 'function') {
        initMapSearch(map);
    }

    if (typeof lucide !== 'undefined') lucide.createIcons();
});
