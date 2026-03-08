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
    let debounceTimer = null;
    let isPanning = false;
    const loading = document.getElementById('map-loading');
    const cardsContainer = document.getElementById('parking-cards');
    const countEl = document.getElementById('parking-count');

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

    const facilityBadges = (p) => {
        const badges = [];
        if (p.is_covered) badges.push('屋根');
        if (p.is_locked) badges.push('施錠');
        return badges.length
            ? `<div class="flex gap-1 mt-1">${badges.map(b => `<span class="bg-gray-100 text-gray-500 text-[9px] px-1.5 py-0.5 rounded">${b}</span>`).join('')}</div>`
            : '';
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
            iconAnchor: [14, 14],
            popupAnchor: [0, -16]
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

        // 中心からの距離でソート
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

        // カードクリックイベント
        cardsContainer.querySelectorAll('.parking-card').forEach(card => {
            card.addEventListener('click', () => {
                const lat = parseFloat(card.dataset.lat);
                const lng = parseFloat(card.dataset.lng);

                // パン中フラグを立てて moveend での再取得をスキップ
                isPanning = true;
                map.setView([lat, lng], Math.max(map.getZoom(), 16));
                setTimeout(() => { isPanning = false; }, 500);

                // 対応するマーカーのポップアップを開く
                markers.forEach(m => {
                    const pos = m.getLatLng();
                    if (Math.abs(pos.lat - lat) < 0.0001 && Math.abs(pos.lng - lng) < 0.0001) {
                        m.openPopup();
                    }
                });

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

            // マーカー更新
            markers.forEach(m => map.removeLayer(m));
            markers = [];

            parkings.forEach(p => {
                const hasReviews = p.reviews_count > 0;
                const marker = L.marker([p.latitude, p.longitude], { icon: createParkingIcon(hasReviews) }).addTo(map);

                const detailRows = [];
                if (p.available_hours) detailRows.push(`<p class="text-[10px] text-gray-500"><span class="font-bold text-gray-600">時間:</span> ${p.available_hours}</p>`);
                if (p.capacity) detailRows.push(`<p class="text-[10px] text-gray-500"><span class="font-bold text-gray-600">台数:</span> ${p.capacity}台</p>`);
                if (p.price_detail) detailRows.push(`<p class="text-[10px] text-gray-500"><span class="font-bold text-gray-600">料金:</span> ${p.price_detail}</p>`);

                const reviewAction = hasReviews
                    ? `<p class="text-[10px] text-gray-400 mt-1">${p.reviews_count}件のレビュー</p>`
                    : `<a href="/parking/${p.id}#review-detail-form" class="text-[10px] text-blue-500 hover:underline mt-1 block">最初のレビューを投稿する →</a>`;

                const usedInfo = p.used_count > 0 ? `<span class="text-[10px] text-gray-400 ml-2">${p.used_count}人が使った</span>` : '';

                const popupContent = `
                    <div class="p-4">
                        <h3 class="font-bold text-sm mb-1 line-clamp-1">${p.name}</h3>
                        <p class="text-[10px] text-gray-500 mb-1">${p.address || p.prefecture || ''}</p>
                        <div class="flex items-center gap-2 mb-1 flex-wrap">
                            <span class="bg-green-100 text-green-700 text-[10px] px-2 py-0.5 rounded font-bold">
                                ${parkingTypeLabel(p.parking_type)}
                            </span>
                            <span class="text-[10px] font-bold text-gray-700">${priceDisplay(p)}</span>
                        </div>
                        ${detailRows.length ? '<div class="space-y-0.5 mt-1 mb-1">' + detailRows.join('') + '</div>' : ''}
                        <div class="flex items-center">${ratingStars(p.avg_rating)}${usedInfo}</div>
                        ${reviewAction}
                        ${facilityBadges(p)}
                        <a href="/parking/${p.id}" target="_blank" class="block w-full bg-green-600 text-white text-center text-xs font-bold py-2 rounded-lg hover:bg-green-700 transition-colors mt-3">
                            詳細を見る
                        </a>
                    </div>
                `;

                marker.bindPopup(popupContent, {
                    className: 'custom-popup',
                    minWidth: 280,
                    autoClose: false,
                    closeOnClick: false
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

    if (typeof lucide !== 'undefined') lucide.createIcons();
});
