document.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    const latParam = urlParams.get('lat');
    const lngParam = urlParams.get('lng');

    const defaultLat = latParam ? parseFloat(latParam) : 35.681236;
    const defaultLng = lngParam ? parseFloat(lngParam) : 139.767125;
    const defaultZoom = latParam ? 15 : 13;

    const map = L.map('map').setView([defaultLat, defaultLng], defaultZoom);

    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>'
    }).addTo(map);

    let markers = [];
    const loading = document.getElementById('map-loading');

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

    // カスタムアイコン（緑のマーカー）
    const parkingIcon = L.divIcon({
        html: '<div style="background:#16a34a;width:28px;height:28px;border-radius:50%;border:3px solid white;box-shadow:0 2px 6px rgba(0,0,0,.3);display:flex;align-items:center;justify-content:center;color:white;font-weight:bold;font-size:12px;">P</div>',
        className: '',
        iconSize: [28, 28],
        iconAnchor: [14, 14],
        popupAnchor: [0, -16]
    });

    const fetchParkings = async () => {
        if (loading) loading.classList.remove('hidden');

        const bounds = map.getBounds();
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

            markers.forEach(m => map.removeLayer(m));
            markers = [];

            parkings.forEach(p => {
                const marker = L.marker([p.latitude, p.longitude], { icon: parkingIcon }).addTo(map);

                const detailRows = [];
                if (p.available_hours) detailRows.push(`<p class="text-[10px] text-gray-500"><span class="font-bold text-gray-600">時間:</span> ${p.available_hours}</p>`);
                if (p.capacity) detailRows.push(`<p class="text-[10px] text-gray-500"><span class="font-bold text-gray-600">台数:</span> ${p.capacity}台</p>`);
                if (p.price_detail) detailRows.push(`<p class="text-[10px] text-gray-500"><span class="font-bold text-gray-600">料金:</span> ${p.price_detail}</p>`);

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
                        ${ratingStars(p.avg_rating)}
                        ${facilityBadges(p)}
                        <a href="/parking/${p.id}" target="_blank" class="block w-full bg-green-600 text-white text-center text-xs font-bold py-2 rounded-lg hover:bg-green-700 transition-colors mt-3">
                            詳細を見る
                        </a>
                    </div>
                `;

                marker.bindPopup(popupContent, {
                    className: 'custom-popup',
                    minWidth: 280
                });

                markers.push(marker);
            });

        } catch (error) {
            console.error('Failed to fetch parkings:', error);
        } finally {
            if (loading) loading.classList.add('hidden');
        }
    };

    map.on('moveend', fetchParkings);
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
