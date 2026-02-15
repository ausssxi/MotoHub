document.addEventListener('DOMContentLoaded', () => {
    // クエリパラメータの取得
    const urlParams = new URLSearchParams(window.location.search);
    const latParam = urlParams.get('lat');
    const lngParam = urlParams.get('lng');
    const shopIdParam = urlParams.get('shop_id');

    // 初期座標（パラメータがあればそれを採用、なければ東京駅）
    // パラメータがある場合はズームレベルを上げて詳細表示する
    const defaultLat = latParam ? parseFloat(latParam) : 35.681236;
    const defaultLng = lngParam ? parseFloat(lngParam) : 139.767125;
    const defaultZoom = latParam ? 15 : 13;
    
    // 地図の初期化
    const map = L.map('map').setView([defaultLat, defaultLng], defaultZoom);

    // 地図タイルの読み込み
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>'
    }).addTo(map);

    let markers = [];
    const loading = document.getElementById('map-loading');

    // ショップを検索してピンを立てる関数
    const fetchShops = async () => {
        if(loading) loading.classList.remove('hidden');

        const bounds = map.getBounds();
        const params = new URLSearchParams({
            ne_lat: bounds.getNorthEast().lat,
            ne_lng: bounds.getNorthEast().lng,
            sw_lat: bounds.getSouthWest().lat,
            sw_lng: bounds.getSouthWest().lng,
        });

        try {
            const response = await fetch(`/shops/api/area?${params.toString()}`);
            if (!response.ok) throw new Error('API Error');
            
            const shops = await response.json();

            // 既存マーカーの削除
            markers.forEach(m => map.removeLayer(m));
            markers = [];

            // 新しいマーカーの追加
            shops.forEach(shop => {
                const marker = L.marker([shop.latitude, shop.longitude]).addTo(map);
                
                const popupContent = `
                    <div class="p-4">
                        <h3 class="font-bold text-sm mb-1 line-clamp-1">${shop.name}</h3>
                        <p class="text-[10px] text-gray-500 mb-2">${shop.prefecture}</p>
                        <div class="flex items-center gap-2 mb-3">
                            <span class="bg-blue-100 text-blue-600 text-[10px] px-2 py-0.5 rounded font-bold">
                                在庫 ${shop.listings_count ?? 0}台
                            </span>
                        </div>
                        <a href="/shops/${shop.id}" target="_blank" class="block w-full bg-black text-white text-center text-xs font-bold py-2 rounded-lg hover:bg-gray-800 transition-colors">
                            店舗詳細を見る
                        </a>
                    </div>
                `;

                marker.bindPopup(popupContent, {
                    className: 'custom-popup',
                    minWidth: 260
                });
                
                markers.push(marker);

                // ★追加: URLパラメータで指定されたショップならポップアップを自動で開く
                if (shopIdParam && parseInt(shopIdParam) === shop.id) {
                    setTimeout(() => marker.openPopup(), 500); // マーカー描画後に開く
                }
            });

        } catch (error) {
            console.error('Failed to fetch shops:', error);
        } finally {
            if(loading) loading.classList.add('hidden');
        }
    };

    map.on('moveend', fetchShops);

    // 初回読み込み
    fetchShops();

    // 現在地ボタン
    const btnCurrent = document.getElementById('btn-current-location');
    if (btnCurrent) {
        btnCurrent.addEventListener('click', () => {
            if (!navigator.geolocation) {
                alert('お使いのブラウザは現在地取得に対応していません。');
                return;
            }
            navigator.geolocation.getCurrentPosition((pos) => {
                map.setView([pos.coords.latitude, pos.coords.longitude], 13);
            }, (err) => {
                alert('現在地を取得できませんでした。');
            });
        });
    }

    if (typeof lucide !== 'undefined') lucide.createIcons();
});