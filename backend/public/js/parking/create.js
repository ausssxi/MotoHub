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

    // 都道府県リスト
    const PREFECTURES = [
        '北海道','青森県','岩手県','宮城県','秋田県','山形県','福島県',
        '茨城県','栃木県','群馬県','埼玉県','千葉県','東京都','神奈川県',
        '新潟県','富山県','石川県','福井県','山梨県','長野県','岐阜県',
        '静岡県','愛知県','三重県','滋賀県','京都府','大阪府','兵庫県',
        '奈良県','和歌山県','鳥取県','島根県','岡山県','広島県','山口県',
        '徳島県','香川県','愛媛県','高知県','福岡県','佐賀県','長崎県',
        '熊本県','大分県','宮崎県','鹿児島県','沖縄県'
    ];

    // 住所 → 座標（国土地理院 API + Nominatim フォールバック）
    const geocodeAddress = async (address) => {
        btnGeocode.disabled = true;
        btnGeocode.textContent = '検索中...';

        try {
            // 1) 国土地理院 API（日本語住所に強い）
            const gsiResult = await geocodeGSI(address);
            if (gsiResult) {
                setMarker(gsiResult.lat, gsiResult.lng);
                map.setView([gsiResult.lat, gsiResult.lng], 16);
                // 逆ジオコーディングで都道府県・市区町村を取得
                reverseGeocode(gsiResult.lat, gsiResult.lng);
                return;
            }

            // 2) Nominatim フォールバック
            const nomResult = await geocodeNominatim(address);
            if (nomResult) {
                setMarker(nomResult.lat, nomResult.lng);
                map.setView([nomResult.lat, nomResult.lng], 16);
                extractPrefecture(nomResult.displayName);
                reverseGeocode(nomResult.lat, nomResult.lng);
                return;
            }

            // 両方失敗
            showGeocodeError();
        } catch (error) {
            console.error('Geocoding error:', error);
            showGeocodeError();
        } finally {
            btnGeocode.disabled = false;
            btnGeocode.textContent = '住所検索';
        }
    };

    // 国土地理院 API
    const geocodeGSI = async (address) => {
        // スペースを除去して検索
        const query = address.replace(/[\s\u3000]+/g, '');
        const url = 'https://msearch.gsi.go.jp/address-search/AddressSearch?q=' + encodeURIComponent(query);
        const res = await fetch(url);
        if (!res.ok) return null;
        const data = await res.json();
        if (!data || data.length === 0) return null;

        // 最もマッチ度の高い候補を選択
        const best = selectBestGSIResult(data, query);
        if (!best) return null;

        // 国土地理院APIは [経度, 緯度] の順
        const coords = best.geometry.coordinates;
        return { lat: coords[1], lng: coords[0] };
    };

    // GSI結果のスコアリング
    const selectBestGSIResult = (results, query) => {
        const FACILITY_RE = /消防署|消防局|市役所|警察署|学校|病院|郵便局|図書館|公民館|交番|出張所|役場|裁判所|税務署|保健所|センター|事務所|庁舎/;

        // キーワード分割
        const keywords = splitQuery(query);
        let best = results[0];
        let bestScore = -Infinity;

        for (const r of results) {
            const title = r.properties?.title || '';
            let score = 0;
            for (const kw of keywords) {
                if (title.includes(kw)) score++;
            }
            if (FACILITY_RE.test(title)) score -= 1;
            if (score > bestScore || (score === bestScore && title.length < (best.properties?.title || '').length)) {
                best = r;
                bestScore = score;
            }
        }
        return best;
    };

    // クエリを行政区画で分割
    const splitQuery = (query) => {
        const tokens = query.split(/(..?[都道府県]|.+?[市区町村郡])/).filter(Boolean);
        const result = [];
        for (const t of tokens) {
            const sub = t.split(/([^\s]+?[駅丁目])/).filter(Boolean);
            result.push(...sub);
        }
        return result.map(s => s.trim()).filter(Boolean);
    };

    // Nominatim フォールバック
    const geocodeNominatim = async (address) => {
        const response = await fetch(
            `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(address)}&countrycodes=jp&limit=1`,
            { headers: { 'Accept-Language': 'ja' } }
        );
        const data = await response.json();
        if (data.length > 0) {
            return {
                lat: parseFloat(data[0].lat),
                lng: parseFloat(data[0].lon),
                displayName: data[0].display_name
            };
        }
        return null;
    };

    // エラー表示
    const showGeocodeError = () => {
        // 既存のエラーメッセージを削除
        const existing = document.getElementById('geocode-error');
        if (existing) existing.remove();

        const errorDiv = document.createElement('div');
        errorDiv.id = 'geocode-error';
        errorDiv.className = 'bg-amber-50 border border-amber-200 text-amber-700 text-xs rounded-xl px-4 py-3 mt-2 flex items-start gap-2';
        errorDiv.innerHTML = '<svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>' +
            '<span>住所が見つかりませんでした。<strong>地図をクリック</strong>して位置を直接指定してください。番地を省略すると見つかりやすくなります。</span>';

        const mapEl = document.getElementById('create-map');
        mapEl.parentNode.insertBefore(errorDiv, mapEl);

        // 5秒後に自動で消す
        setTimeout(() => {
            if (errorDiv.parentNode) {
                errorDiv.style.transition = 'opacity 0.3s';
                errorDiv.style.opacity = '0';
                setTimeout(() => errorDiv.remove(), 300);
            }
        }, 8000);
    };

    // 座標 → 住所（逆ジオコーディング: Nominatim）
    const reverseGeocode = async (lat, lng) => {
        try {
            const response = await fetch(
                `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=18&addressdetails=1`,
                { headers: { 'Accept-Language': 'ja' } }
            );
            const data = await response.json();
            if (data && data.display_name) {
                // 住所フィールドが空の場合のみ自動入力
                const cleaned = data.display_name.replace(/, 日本$/, '').replace(/〒[\d-]+,?\s*/, '');
                if (!addressInput.value.trim()) {
                    addressInput.value = cleaned;
                }

                if (data.address) {
                    prefectureInput.value = data.address.province || data.address.state || '';
                    cityInput.value = data.address.city || data.address.town || data.address.village || '';
                }

                // Nominatimで都道府県が取れなかった場合display_nameから抽出
                if (!prefectureInput.value) {
                    extractPrefecture(data.display_name);
                }
            }
        } catch (error) {
            console.error('Reverse geocoding error:', error);
        }
    };

    // display_nameから都道府県を抽出
    const extractPrefecture = (displayName) => {
        for (const pref of PREFECTURES) {
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
            // 既存エラーを消す
            const existing = document.getElementById('geocode-error');
            if (existing) existing.remove();
            geocodeAddress(address);
        });

        // Enterキー対応
        addressInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                btnGeocode.click();
            }
        });
    }

    // 料金フィールド: 半角数字のみ許可
    document.querySelectorAll('.price-numeric').forEach(input => {
        input.addEventListener('input', () => {
            input.value = input.value.replace(/[^0-9]/g, '');
        });
    });

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

    // 画像アップロード機能
    initImageUpload();

    if (typeof lucide !== 'undefined') lucide.createIcons();
});

/**
 * 画像アップロード機能
 */
function initImageUpload() {
    const dropZone = document.getElementById('image-drop-zone');
    const fileInput = document.getElementById('image-input');
    const previewContainer = document.getElementById('image-previews');
    if (!dropZone || !fileInput || !previewContainer) return;

    const MAX_FILES = 5;
    const MAX_SIZE = 5 * 1024 * 1024; // 5MB
    const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
    let selectedFiles = []; // DataTransfer doesn't work well cross-browser, track manually

    // ドラッグ&ドロップイベント
    ['dragenter', 'dragover'].forEach(evt => {
        dropZone.addEventListener(evt, (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropZone.classList.add('border-green-400', 'bg-green-50');
        });
    });

    ['dragleave', 'drop'].forEach(evt => {
        dropZone.addEventListener(evt, (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropZone.classList.remove('border-green-400', 'bg-green-50');
        });
    });

    dropZone.addEventListener('drop', (e) => {
        const files = Array.from(e.dataTransfer.files);
        addFiles(files);
    });

    // クリックでファイル選択
    dropZone.addEventListener('click', () => fileInput.click());
    fileInput.addEventListener('change', () => {
        addFiles(Array.from(fileInput.files));
        fileInput.value = ''; // リセット（同じファイルを再選択可能に）
    });

    function addFiles(files) {
        for (const file of files) {
            if (selectedFiles.length >= MAX_FILES) {
                alert(`画像は最大${MAX_FILES}枚までです。`);
                break;
            }
            if (!ALLOWED_TYPES.includes(file.type)) {
                alert(`${file.name}: 対応していない形式です。JPG, PNG, WebPのみ対応しています。`);
                continue;
            }
            if (file.size > MAX_SIZE) {
                alert(`${file.name}: ファイルサイズが大きすぎます（最大5MB）。`);
                continue;
            }
            selectedFiles.push(file);
        }
        updatePreviews();
        syncFileInput();
    }

    function removeFile(index) {
        selectedFiles.splice(index, 1);
        updatePreviews();
        syncFileInput();
    }

    function syncFileInput() {
        // DataTransferを使ってFileListを再構成
        const dt = new DataTransfer();
        selectedFiles.forEach(f => dt.items.add(f));
        fileInput.files = dt.files;

        // 枚数表示更新
        const countEl = document.getElementById('image-count');
        if (countEl) {
            countEl.textContent = `${selectedFiles.length}/${MAX_FILES}`;
            countEl.classList.toggle('text-red-500', selectedFiles.length >= MAX_FILES);
        }
    }

    function updatePreviews() {
        previewContainer.innerHTML = '';
        selectedFiles.forEach((file, index) => {
            const wrapper = document.createElement('div');
            wrapper.className = 'relative group w-24 h-24 rounded-xl overflow-hidden border border-gray-200 shrink-0';

            const img = document.createElement('img');
            img.className = 'w-full h-full object-cover';
            img.src = URL.createObjectURL(file);
            img.onload = () => URL.revokeObjectURL(img.src);

            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'absolute top-1 right-1 w-5 h-5 bg-black/60 text-white rounded-full flex items-center justify-center text-xs opacity-0 group-hover:opacity-100 transition-opacity';
            removeBtn.innerHTML = '&times;';
            removeBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                removeFile(index);
            });

            const order = document.createElement('span');
            order.className = 'absolute bottom-1 left-1 bg-black/60 text-white text-[9px] font-bold w-4 h-4 rounded-full flex items-center justify-center';
            order.textContent = index + 1;

            wrapper.appendChild(img);
            wrapper.appendChild(removeBtn);
            wrapper.appendChild(order);
            previewContainer.appendChild(wrapper);
        });
    }
}
