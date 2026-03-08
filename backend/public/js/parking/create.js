document.addEventListener('DOMContentLoaded', () => {
    const latInput = document.getElementById('latitude');
    const lngInput = document.getElementById('longitude');
    const addressInput = document.getElementById('address');
    const prefectureInput = document.getElementById('prefecture');
    const cityInput = document.getElementById('city');
    const btnGeocode = document.getElementById('btn-geocode');
    const isFreeCheck = document.getElementById('is_free_check');
    const priceInputs = document.getElementById('price-inputs');

    // 初期座標（東京駅）
    const initialLat = latInput.value ? parseFloat(latInput.value) : 35.681236;
    const initialLng = lngInput.value ? parseFloat(lngInput.value) : 139.767125;
    const initialZoom = latInput.value ? 16 : 12;

    const map = L.map('create-map').setView([initialLat, initialLng], initialZoom);

    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>'
    }).addTo(map);

    let marker = null;

    const setMarker = (lat, lng) => {
        if (marker) map.removeLayer(marker);
        marker = L.marker([lat, lng], { draggable: true }).addTo(map);
        latInput.value = lat.toFixed(7);
        lngInput.value = lng.toFixed(7);

        marker.on('dragend', (e) => {
            const pos = e.target.getLatLng();
            latInput.value = pos.lat.toFixed(7);
            lngInput.value = pos.lng.toFixed(7);
            reverseGeocode(pos.lat, pos.lng);
        });
    };

    // 既存の値がある場合はマーカーを設置
    if (latInput.value && lngInput.value) {
        setMarker(parseFloat(latInput.value), parseFloat(lngInput.value));
    }

    // 地図クリックでマーカーを設置
    map.on('click', (e) => {
        setMarker(e.latlng.lat, e.latlng.lng);
        map.setView(e.latlng, Math.max(map.getZoom(), 15));
        reverseGeocode(e.latlng.lat, e.latlng.lng);
    });

    // 住所 → 座標（Nominatim）
    const geocodeAddress = async (address) => {
        try {
            const response = await fetch(
                `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(address)}&countrycodes=jp&limit=1`,
                { headers: { 'Accept-Language': 'ja' } }
            );
            const data = await response.json();
            if (data.length > 0) {
                const lat = parseFloat(data[0].lat);
                const lng = parseFloat(data[0].lon);
                setMarker(lat, lng);
                map.setView([lat, lng], 16);
                extractPrefecture(data[0].display_name);
            } else {
                alert('住所が見つかりませんでした。もう少し詳しい住所を入力するか、地図上をクリックしてください。');
            }
        } catch (error) {
            console.error('Geocoding error:', error);
            alert('住所検索中にエラーが発生しました。');
        }
    };

    // 座標 → 住所（逆ジオコーディング）
    const reverseGeocode = async (lat, lng) => {
        try {
            const response = await fetch(
                `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=18&addressdetails=1`,
                { headers: { 'Accept-Language': 'ja' } }
            );
            const data = await response.json();
            if (data && data.display_name) {
                addressInput.value = data.display_name.replace(', 日本', '').replace(/〒[\d-]+,?\s*/, '');

                if (data.address) {
                    prefectureInput.value = data.address.province || data.address.state || '';
                    cityInput.value = data.address.city || data.address.town || data.address.village || '';
                }
            }
        } catch (error) {
            console.error('Reverse geocoding error:', error);
        }
    };

    // display_nameから都道府県を抽出
    const extractPrefecture = (displayName) => {
        const prefectures = [
            '北海道','青森県','岩手県','宮城県','秋田県','山形県','福島県',
            '茨城県','栃木県','群馬県','埼玉県','千葉県','東京都','神奈川県',
            '新潟県','富山県','石川県','福井県','山梨県','長野県','岐阜県',
            '静岡県','愛知県','三重県','滋賀県','京都府','大阪府','兵庫県',
            '奈良県','和歌山県','鳥取県','島根県','岡山県','広島県','山口県',
            '徳島県','香川県','愛媛県','高知県','福岡県','佐賀県','長崎県',
            '熊本県','大分県','宮崎県','鹿児島県','沖縄県'
        ];
        for (const pref of prefectures) {
            if (displayName.includes(pref)) {
                prefectureInput.value = pref;
                return;
            }
        }
    };

    // 住所検索ボタン
    if (btnGeocode) {
        btnGeocode.addEventListener('click', () => {
            const address = addressInput.value.trim();
            if (!address) {
                alert('住所を入力してください。');
                return;
            }
            geocodeAddress(address);
        });
    }

    // 無料チェックボックスの制御
    if (isFreeCheck && priceInputs) {
        const togglePriceInputs = () => {
            priceInputs.style.opacity = isFreeCheck.checked ? '0.3' : '1';
            priceInputs.querySelectorAll('input').forEach(input => {
                input.disabled = isFreeCheck.checked;
            });
        };
        isFreeCheck.addEventListener('change', togglePriceInputs);
        togglePriceInputs();
    }

    if (typeof lucide !== 'undefined') lucide.createIcons();
});
