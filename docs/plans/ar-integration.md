# MotoHub WebAR統合 - 駐車場・店舗ARファインダー

## 概要
既存のWebARコードをMotoHubに統合し、カメラ越しに近くのバイク駐車場・ショップを表示する。
日本のバイクサイトで唯一無二の機能。

## 既存ARコードの技術スタック（リファクタリング元）
- Three.js（3D矢印 + テキストラベル）
- Device Orientation API（コンパス方位）
- Geolocation API（GPS）
- Geodesy（方位角・距離計算）
- WMM（地磁気偏角補正）
- カメラストリーム + Canvas合成
- Leaflet地図（AR画面の下に表示）

## 新規ファイル構成
```
backend/
├── app/Http/Controllers/Ar/ArController.php
├── resources/views/ar/
│   ├── index.blade.php          # ARメインページ
│   └── components/
│       └── ar-ui.blade.php      # AR UI コンポーネント
├── public/js/ar/
│   ├── ar-main.js               # メインARロジック（リファクタリング済み）
│   ├── ar-orientation.js        # デバイス方位・コンパス処理
│   ├── ar-renderer.js           # Three.js 3Dレンダリング
│   └── wmm.js                   # 地磁気偏角モデル（WMM2025に更新）
└── routes/web.php               # /ar ルート追加
```

---

## ルート

```php
// routes/web.php
Route::get('/ar', [App\Http\Controllers\Ar\ArController::class, 'index'])->name('ar.index');
```

---

## コントローラー

```php
// app/Http/Controllers/Ar/ArController.php
<?php

namespace App\Http\Controllers\Ar;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\BikeParking;
use App\Models\Shop;

class ArController extends Controller
{
    public function index()
    {
        return view('ar.index');
    }
}
```

APIは既存の駐車場API（/parking/api/search）と店舗データを使う。
新規APIは不要。AR側のJSから直接fetchする。

---

## Bladeテンプレート: ar/index.blade.php

```blade
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>ARファインダー | MotoHub</title>
    
    {{-- OGP --}}
    <meta property="og:title" content="ARでバイク駐車場・ショップを探す | MotoHub">
    <meta property="og:description" content="カメラをかざすだけ。近くのバイク駐車場とショップが見える。">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ route('ar.index') }}">
    
    <script type="importmap">
    {
        "imports": {
            "three": "https://cdn.jsdelivr.net/npm/three@0.167.0/build/three.module.js",
            "three/addons/": "https://cdn.jsdelivr.net/npm/three@0.167.0/examples/jsm/"
        }
    }
    </script>
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { 
            width: 100%; height: 100%; 
            overflow: hidden; 
            background: #000;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }
        
        /* AR画面（フルスクリーン） */
        #ar-container {
            position: relative;
            width: 100vw;
            height: 100vh;
        }
        #ar-canvas {
            width: 100%;
            height: 100%;
            display: block;
        }
        
        /* 許可リクエスト画面 */
        #permission-screen {
            position: fixed;
            inset: 0;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 100;
            color: white;
            padding: 24px;
            text-align: center;
        }
        #permission-screen h1 {
            font-size: 24px;
            font-weight: 900;
            margin-bottom: 8px;
        }
        #permission-screen p {
            font-size: 14px;
            color: #94a3b8;
            margin-bottom: 32px;
            line-height: 1.6;
        }
        #start-ar-btn {
            background: #16a34a;
            color: white;
            border: none;
            padding: 16px 48px;
            border-radius: 16px;
            font-size: 16px;
            font-weight: 900;
            cursor: pointer;
            transition: background 0.2s;
        }
        #start-ar-btn:active { background: #15803d; }
        
        /* 上部ヘッダー */
        #ar-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            padding: 12px 16px;
            padding-top: max(env(safe-area-inset-top), 12px);
            background: linear-gradient(to bottom, rgba(0,0,0,0.6), transparent);
            z-index: 50;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        #ar-header .logo {
            color: white;
            font-weight: 900;
            font-size: 16px;
            text-decoration: none;
        }
        #ar-header .back-btn {
            color: white;
            text-decoration: none;
            font-size: 14px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 4px;
            background: rgba(255,255,255,0.15);
            padding: 8px 12px;
            border-radius: 12px;
            backdrop-filter: blur(8px);
        }
        
        /* コンパス表示 */
        #compass {
            position: fixed;
            top: max(calc(env(safe-area-inset-top) + 60px), 72px);
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0,0,0,0.5);
            color: white;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 900;
            z-index: 50;
            backdrop-filter: blur(8px);
        }
        
        /* フィルタートグル */
        #filter-bar {
            position: fixed;
            top: max(calc(env(safe-area-inset-top) + 100px), 112px);
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 8px;
            z-index: 50;
        }
        .filter-btn {
            padding: 6px 14px;
            border-radius: 20px;
            border: none;
            font-size: 11px;
            font-weight: 900;
            cursor: pointer;
            backdrop-filter: blur(8px);
            transition: all 0.2s;
        }
        .filter-btn.active-parking {
            background: rgba(22, 163, 74, 0.8);
            color: white;
        }
        .filter-btn.inactive {
            background: rgba(255, 255, 255, 0.2);
            color: rgba(255, 255, 255, 0.7);
        }
        .filter-btn.active-shop {
            background: rgba(59, 130, 246, 0.8);
            color: white;
        }
        
        /* 下部情報パネル */
        #info-panel {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 16px;
            padding-bottom: max(env(safe-area-inset-bottom), 16px);
            z-index: 50;
            transform: translateY(100%);
            transition: transform 0.3s ease;
        }
        #info-panel.visible { transform: translateY(0); }
        #info-panel .panel-content {
            background: white;
            border-radius: 20px;
            padding: 20px;
            box-shadow: 0 -4px 30px rgba(0,0,0,0.3);
        }
        #info-panel .panel-name {
            font-size: 16px;
            font-weight: 900;
            color: #1e293b;
            margin-bottom: 4px;
        }
        #info-panel .panel-address {
            font-size: 11px;
            color: #94a3b8;
            margin-bottom: 12px;
        }
        #info-panel .panel-meta {
            display: flex;
            gap: 12px;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }
        #info-panel .meta-item {
            font-size: 11px;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 8px;
        }
        #info-panel .panel-actions {
            display: flex;
            gap: 8px;
        }
        #info-panel .panel-actions a {
            flex: 1;
            text-align: center;
            padding: 12px;
            border-radius: 14px;
            font-size: 13px;
            font-weight: 900;
            text-decoration: none;
            transition: opacity 0.2s;
        }
        #info-panel .btn-navigate {
            background: #3b82f6;
            color: white;
        }
        #info-panel .btn-detail {
            background: #f1f5f9;
            color: #334155;
        }
        
        /* ステータス表示 */
        #status-bar {
            position: fixed;
            bottom: max(calc(env(safe-area-inset-bottom) + 16px), 32px);
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0,0,0,0.5);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            z-index: 40;
            backdrop-filter: blur(8px);
        }
        
        /* ローディング */
        #loading {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.8);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 200;
            color: white;
            font-weight: 700;
        }
    </style>
</head>
<body>
    {{-- 許可リクエスト画面 --}}
    <div id="permission-screen">
        <div style="margin-bottom: 24px;">
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2">
                <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
                <circle cx="12" cy="13" r="4"/>
            </svg>
        </div>
        <h1>AR駐車場ファインダー</h1>
        <p>
            カメラをかざすだけで、<br>
            近くのバイク駐車場とショップが見えます。<br><br>
            カメラと位置情報の許可が必要です。
        </p>
        <button id="start-ar-btn">ARを起動する</button>
        <a href="{{ route('parking.index') }}" style="color: #64748b; font-size: 12px; margin-top: 16px; text-decoration: underline;">
            通常のマップで探す →
        </a>
    </div>
    
    {{-- AR画面 --}}
    <div id="ar-container" style="display: none;">
        <canvas id="ar-canvas"></canvas>
        
        {{-- ヘッダー --}}
        <div id="ar-header">
            <a href="{{ route('parking.index') }}" class="back-btn">
                ← マップ
            </a>
            <span class="logo">MotoHub AR</span>
        </div>
        
        {{-- コンパス --}}
        <div id="compass">N 0°</div>
        
        {{-- フィルター --}}
        <div id="filter-bar">
            <button class="filter-btn active-parking" data-type="parking" onclick="toggleFilter('parking')">
                🅿️ 駐車場
            </button>
            <button class="filter-btn active-shop" data-type="shop" onclick="toggleFilter('shop')">
                🏍 ショップ
            </button>
        </div>
        
        {{-- ステータス --}}
        <div id="status-bar">検索中...</div>
        
        {{-- 情報パネル --}}
        <div id="info-panel">
            <div class="panel-content">
                <div id="panel-close" onclick="closePanel()" style="text-align:right;cursor:pointer;font-size:18px;color:#94a3b8;">✕</div>
                <div class="panel-name" id="panel-name"></div>
                <div class="panel-address" id="panel-address"></div>
                <div class="panel-meta" id="panel-meta"></div>
                <div class="panel-actions">
                    <a href="#" id="panel-navigate" class="btn-navigate">ルート案内</a>
                    <a href="#" id="panel-detail" class="btn-detail">詳細を見る</a>
                </div>
            </div>
        </div>
    </div>
    
    {{-- ローディング --}}
    <div id="loading">
        <div style="text-align:center">
            <div style="font-size:32px;margin-bottom:8px;">📡</div>
            位置情報を取得中...
        </div>
    </div>

    <script type="module" src="{{ asset('js/ar/ar-main.js') }}"></script>
</body>
</html>
```

---

## メインJS: public/js/ar/ar-main.js

このファイルは既存のindex.phpのJSをリファクタリングしたもの。
以下の変更を行う:

### 1. データソースの変更
```javascript
// 既存（PHPからDB直接取得）
// fetch('request.php', { method: 'POST', body: JSON.stringify(data) })

// 新（MotoHubの既存APIを使用）
async function fetchNearbyTargets(lat, lng) {
    const radius = 0.005; // 約500m
    const targets = [];
    
    // 駐車場API（既存）
    if (showParking) {
        const parkingRes = await fetch(`/parking/api/search?ne_lat=${lat + radius}&ne_lng=${lng + radius}&sw_lat=${lat - radius}&sw_lng=${lng - radius}`);
        const parkings = await parkingRes.json();
        parkings.forEach(p => {
            targets.push({
                id: p.id,
                name: p.name,
                latitude: parseFloat(p.latitude),
                longitude: parseFloat(p.longitude),
                altitude: 0, // 駐車場にaltitudeはないので0
                type: 'parking',
                detail: p.price_detail || '',
                capacity: p.capacity,
                url: `/parking/${p.id}`,
                color: 0x16a34a // 緑
            });
        });
    }
    
    // 店舗データ（新規API or 既存のshop検索を利用）
    if (showShop) {
        try {
            const shopRes = await fetch(`/api/shops/nearby?lat=${lat}&lng=${lng}&radius=500`);
            const shops = await shopRes.json();
            shops.forEach(s => {
                targets.push({
                    id: s.id,
                    name: s.name,
                    latitude: parseFloat(s.latitude),
                    longitude: parseFloat(s.longitude),
                    altitude: 0,
                    type: 'shop',
                    detail: s.address || '',
                    url: `/shops/${s.id}`,
                    color: 0x3b82f6 // 青
                });
            });
        } catch (e) {
            console.warn('Shop API not available:', e);
        }
    }
    
    return targets;
}
```

### 2. 店舗近隣API追加（コントローラー）
```php
// routes/api.php に追加
Route::get('/shops/nearby', function (Request $request) {
    $lat = $request->query('lat');
    $lng = $request->query('lng');
    $radius = $request->query('radius', 500); // メートル
    $degree = $radius / 111000; // メートル→度の概算変換
    
    $shops = \App\Models\Shop::select('id', 'name', 'latitude', 'longitude', 'address', 'prefecture')
        ->whereNotNull('latitude')
        ->whereNotNull('longitude')
        ->whereBetween('latitude', [$lat - $degree, $lat + $degree])
        ->whereBetween('longitude', [$lng - $degree, $lng + $degree])
        ->limit(20)
        ->get();
    
    return response()->json($shops);
});
```

### 3. 3Dオブジェクトの改善
```javascript
// 既存: 赤い矢印のみ
// 新: タイプ別に色分けしたピン型マーカー + テキスト

function createMarker(target) {
    const group = new THREE.Group();
    
    // ピン本体（円柱 + 球）
    const pinColor = target.type === 'parking' ? 0x16a34a : 0x3b82f6;
    const pinMaterial = new THREE.MeshPhongMaterial({ color: pinColor });
    
    // 円柱（ピンの軸）
    const cylinder = new THREE.Mesh(
        new THREE.CylinderGeometry(15, 15, 60, 16),
        pinMaterial
    );
    cylinder.position.y = -30;
    group.add(cylinder);
    
    // 球（ピンの頭）
    const sphere = new THREE.Mesh(
        new THREE.SphereGeometry(25, 16, 16),
        pinMaterial
    );
    group.add(sphere);
    
    // アイコン（Pマーク or 🏍マーク）をテクスチャとして球に貼る
    const iconCanvas = document.createElement('canvas');
    iconCanvas.width = 64;
    iconCanvas.height = 64;
    const ctx = iconCanvas.getContext('2d');
    ctx.fillStyle = 'white';
    ctx.font = '900 36px system-ui';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText(target.type === 'parking' ? 'P' : '🏍', 32, 32);
    
    const iconTexture = new THREE.Texture(iconCanvas);
    iconTexture.needsUpdate = true;
    const iconMaterial = new THREE.MeshBasicMaterial({
        map: iconTexture,
        transparent: true,
        side: THREE.DoubleSide
    });
    const iconPlane = new THREE.Mesh(
        new THREE.PlaneGeometry(40, 40),
        iconMaterial
    );
    iconPlane.position.z = 26;
    group.add(iconPlane);
    
    return group;
}
```

### 4. テキストラベルの改善
```javascript
function createLabel(target, distance) {
    const group = new THREE.Group();
    
    // 名前ラベル（背景付き）
    const nameCanvas = createTextCanvas(
        target.name.length > 8 ? target.name.substr(0, 8) + '…' : target.name,
        { fontSize: 24, bgColor: 'rgba(0,0,0,0.7)', textColor: 'white', padding: 8 }
    );
    const nameTexture = new THREE.Texture(nameCanvas);
    nameTexture.needsUpdate = true;
    const nameMesh = new THREE.Mesh(
        new THREE.PlaneGeometry(nameCanvas.width, nameCanvas.height),
        new THREE.MeshBasicMaterial({ map: nameTexture, transparent: true, side: THREE.DoubleSide })
    );
    nameMesh.position.y = 50;
    group.add(nameMesh);
    
    // 距離ラベル
    const distText = distance < 1000 ? `${Math.round(distance)}m` : `${(distance/1000).toFixed(1)}km`;
    const distCanvas = createTextCanvas(distText, {
        fontSize: 20,
        bgColor: target.type === 'parking' ? 'rgba(22,163,74,0.8)' : 'rgba(59,130,246,0.8)',
        textColor: 'white',
        padding: 6
    });
    const distTexture = new THREE.Texture(distCanvas);
    distTexture.needsUpdate = true;
    const distMesh = new THREE.Mesh(
        new THREE.PlaneGeometry(distCanvas.width, distCanvas.height),
        new THREE.MeshBasicMaterial({ map: distTexture, transparent: true, side: THREE.DoubleSide })
    );
    distMesh.position.y = 15;
    group.add(distMesh);
    
    return group;
}

function createTextCanvas(text, { fontSize = 24, bgColor = 'rgba(0,0,0,0.7)', textColor = 'white', padding = 8 } = {}) {
    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d');
    ctx.font = `900 ${fontSize}px system-ui`;
    const measure = ctx.measureText(text);
    const width = measure.width + padding * 2;
    const height = fontSize * 1.4 + padding * 2;
    
    canvas.width = width;
    canvas.height = height;
    
    // 背景（角丸）
    ctx.fillStyle = bgColor;
    const r = 6;
    ctx.beginPath();
    ctx.moveTo(r, 0);
    ctx.lineTo(width - r, 0);
    ctx.quadraticCurveTo(width, 0, width, r);
    ctx.lineTo(width, height - r);
    ctx.quadraticCurveTo(width, height, width - r, height);
    ctx.lineTo(r, height);
    ctx.quadraticCurveTo(0, height, 0, height - r);
    ctx.lineTo(0, r);
    ctx.quadraticCurveTo(0, 0, r, 0);
    ctx.fill();
    
    // テキスト
    ctx.fillStyle = textColor;
    ctx.font = `900 ${fontSize}px system-ui`;
    ctx.textBaseline = 'middle';
    ctx.textAlign = 'center';
    ctx.fillText(text, width / 2, height / 2);
    
    return canvas;
}
```

### 5. フルスクリーンカメラ（レスポンシブ対応）
```javascript
// 既存: 360x360固定
// 新: 画面サイズに合わせる
async function initCamera() {
    const stream = await navigator.mediaDevices.getUserMedia({
        video: {
            facingMode: { exact: 'environment' },
            width: { ideal: window.innerWidth },
            height: { ideal: window.innerHeight }
        }
    });
    
    videoSource.muted = true;
    videoSource.playsInline = true;
    videoSource.srcObject = stream;
    await videoSource.play();
    
    // Canvasサイズをビデオに合わせる
    const arCanvas = document.getElementById('ar-canvas');
    arCanvas.width = window.innerWidth;
    arCanvas.height = window.innerHeight;
    
    return stream;
}
```

### 6. タップで情報パネル表示
```javascript
// ARオブジェクトのタップ検出（raycaster）
const raycaster = new THREE.Raycaster();
const pointer = new THREE.Vector2();

document.getElementById('ar-canvas').addEventListener('click', (event) => {
    pointer.x = (event.clientX / window.innerWidth) * 2 - 1;
    pointer.y = -(event.clientY / window.innerHeight) * 2 + 1;
    
    raycaster.setFromCamera(pointer, camera);
    const intersects = raycaster.intersectObjects(scene.children, true);
    
    if (intersects.length > 0) {
        // 最も近いオブジェクトのtargetデータを取得
        const target = findTargetByMesh(intersects[0].object);
        if (target) {
            showInfoPanel(target);
        }
    }
});

function showInfoPanel(target) {
    const panel = document.getElementById('info-panel');
    document.getElementById('panel-name').textContent = target.name;
    document.getElementById('panel-address').textContent = target.detail || target.address || '';
    
    // メタ情報
    const metaHtml = [];
    if (target.type === 'parking') {
        metaHtml.push(`<span class="meta-item" style="background:#dcfce7;color:#16a34a;">🅿️ 駐車場</span>`);
        if (target.capacity) metaHtml.push(`<span class="meta-item" style="background:#f1f5f9;color:#334155;">${target.capacity}台</span>`);
    } else {
        metaHtml.push(`<span class="meta-item" style="background:#dbeafe;color:#3b82f6;">🏍 ショップ</span>`);
    }
    const dist = target.distance < 1000 ? `${Math.round(target.distance)}m` : `${(target.distance/1000).toFixed(1)}km`;
    metaHtml.push(`<span class="meta-item" style="background:#f1f5f9;color:#334155;">📍 ${dist}</span>`);
    document.getElementById('panel-meta').innerHTML = metaHtml.join('');
    
    // ルート案内リンク
    document.getElementById('panel-navigate').href = 
        `https://www.google.com/maps/dir/?api=1&destination=${target.latitude},${target.longitude}&travelmode=driving`;
    
    // 詳細ページリンク
    document.getElementById('panel-detail').href = target.url;
    
    panel.classList.add('visible');
}

function closePanel() {
    document.getElementById('info-panel').classList.remove('visible');
}
```

### 7. WMMデータの更新
```javascript
// wmmacc2.jsのCOFデータは2020-2025版
// 2025年以降は精度が落ちるので、WMM2025への更新が望ましい
// ただし2025年中は2020版でも実用的な精度がある
// まずは既存のwmm.jsをそのまま使い、後日更新する

// wmm.jsとしてコピー（export文をそのまま使用）
```

### 8. iOS対応（DeviceOrientation許可）
```javascript
// 既存コードのpermitDeviceOrientationForSafariをそのまま活用
document.getElementById('start-ar-btn').addEventListener('click', async () => {
    document.getElementById('loading').style.display = 'flex';
    
    try {
        // iOS: DeviceOrientationの許可
        if (typeof DeviceOrientationEvent !== 'undefined' && 
            typeof DeviceOrientationEvent.requestPermission === 'function') {
            const response = await DeviceOrientationEvent.requestPermission();
            if (response !== 'granted') {
                alert('コンパスの使用許可が必要です');
                return;
            }
        }
        
        // カメラ + GPS起動
        await initCamera();
        await initGeolocation();
        initThreeJS();
        
        // UI切り替え
        document.getElementById('permission-screen').style.display = 'none';
        document.getElementById('ar-container').style.display = 'block';
        document.getElementById('loading').style.display = 'none';
        
        // アニメーション開始
        startARLoop();
        
    } catch (err) {
        document.getElementById('loading').style.display = 'none';
        alert('カメラまたは位置情報の許可が必要です: ' + err.message);
    }
});
```

### 9. フィルタートグル
```javascript
let showParking = true;
let showShop = true;

function toggleFilter(type) {
    if (type === 'parking') {
        showParking = !showParking;
        document.querySelector('[data-type="parking"]').className = 
            showParking ? 'filter-btn active-parking' : 'filter-btn inactive';
    } else {
        showShop = !showShop;
        document.querySelector('[data-type="shop"]').className = 
            showShop ? 'filter-btn active-shop' : 'filter-btn inactive';
    }
    // マーカーの表示/非表示を更新
    updateMarkerVisibility();
}
```

### 10. コンパス表示の更新
```javascript
// 既存のcompassHeading計算をそのまま活用
function updateCompass(heading) {
    const directions = ['N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW'];
    const index = Math.round(heading / 45) % 8;
    document.getElementById('compass').textContent = `${directions[index]} ${Math.round(heading)}°`;
}
```

---

## 導線の設置

### 1. 駐車場マップ（/parking）にARボタン追加
```blade
{{-- parking/index.blade.php のマップ上に --}}
<a href="{{ route('ar.index') }}" 
   class="absolute top-4 left-4 bg-black/70 text-white px-4 py-2.5 rounded-xl flex items-center gap-2 text-xs font-bold hover:bg-black/90 transition z-[1000] backdrop-blur-sm">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
        <circle cx="12" cy="13" r="4"/>
    </svg>
    ARで探す
</a>
```

### 2. ナビゲーションにAR追加
```blade
{{-- components/navigation.blade.php --}}
<a href="{{ route('ar.index') }}" class="... sm:hidden">
    📸 AR
</a>
```
※ ARはモバイル専用なのでPC表示では非表示

### 3. トップページのバナー（コミュニティタブ or 探すタブ）
```blade
<a href="{{ route('ar.index') }}" class="block bg-gradient-to-r from-gray-900 to-gray-800 rounded-2xl p-6 text-white hover:shadow-xl transition-shadow">
    <div class="flex items-center gap-4">
        <div class="text-3xl">📸</div>
        <div>
            <span class="text-[10px] bg-green-500 text-white px-2 py-0.5 rounded font-bold">NEW</span>
            <h3 class="text-lg font-black mt-1">ARで駐車場を探す</h3>
            <p class="text-xs text-gray-400 mt-1">カメラをかざすだけ。近くの駐車場とショップが見える。</p>
        </div>
    </div>
</a>
```

---

## 確認してほしいファイル
- backend/resources/views/parking/index.blade.php（ARボタン追加先）
- backend/resources/views/components/navigation.blade.php（ナビ追加）
- backend/routes/web.php（ルート追加）
- backend/routes/api.php（店舗API追加）
- backend/public/js/ar/（新規ディレクトリ）

## 実装手順
1. wmmacc2.jsをbackend/public/js/ar/wmm.jsとしてコピー
2. ar-main.jsを作成（既存コードをリファクタリング）
3. ArController + Bladeテンプレート作成
4. 店舗近隣APIの追加
5. 駐車場マップにARボタン追加
6. ナビゲーションにAR追加
7. モバイル実機テスト（iOS Safari + Android Chrome）

## 注意事項
- ARはHTTPS必須（カメラ・位置情報APIの制約）
- iOS SafariではDeviceOrientationの許可ダイアログが必要
- Android Chromeではdeviceorientationabsoluteイベントを使う（既存コード通り）
- PC表示では「スマートフォンでアクセスしてください」と案内
- 初回は位置情報取得に数秒かかるのでローディング表示必須
