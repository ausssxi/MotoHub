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

        /* AR Container */
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

        /* Permission Screen */
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

        /* Header */
        #ar-header {
            position: fixed;
            top: 0; left: 0; right: 0;
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

        /* Compass */
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

        /* Filter */
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

        /* Info Panel */
        #info-panel {
            position: fixed;
            bottom: 0; left: 0; right: 0;
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

        /* Status */
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

        /* Loading */
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
    {{-- Permission Screen --}}
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
            通常のマップで探す &rarr;
        </a>
    </div>

    {{-- AR View --}}
    <div id="ar-container" style="display: none;">
        <canvas id="ar-canvas"></canvas>

        {{-- Header --}}
        <div id="ar-header">
            <a href="{{ route('parking.index') }}" class="back-btn">
                &larr; マップ
            </a>
            <span class="logo">MotoHub AR</span>
        </div>

        {{-- Compass --}}
        <div id="compass">N 0&deg;</div>

        {{-- Filter --}}
        <div id="filter-bar">
            <button class="filter-btn active-parking" data-type="parking" onclick="toggleFilter('parking')">
                P 駐車場
            </button>
            <button class="filter-btn active-shop" data-type="shop" onclick="toggleFilter('shop')">
                S ショップ
            </button>
        </div>

        {{-- Status --}}
        <div id="status-bar">検索中...</div>

        {{-- Info Panel --}}
        <div id="info-panel">
            <div class="panel-content">
                <div id="panel-close" onclick="closePanel()" style="text-align:right;cursor:pointer;font-size:18px;color:#94a3b8;">&times;</div>
                <div class="panel-name" id="panel-name"></div>
                <div class="panel-address" id="panel-address"></div>
                <div class="panel-meta" id="panel-meta"></div>
                <div class="panel-actions">
                    <a href="#" id="panel-navigate" class="btn-navigate" target="_blank" rel="noopener">ルート案内</a>
                    <a href="#" id="panel-detail" class="btn-detail">詳細を見る</a>
                </div>
            </div>
        </div>
    </div>

    {{-- Loading --}}
    <div id="loading">
        <div style="text-align:center">
            <div style="font-size:32px;margin-bottom:8px;">&#128225;</div>
            位置情報を取得中...
        </div>
    </div>

    <script type="module" src="{{ asset('js/ar/ar-main.js') }}"></script>
</body>
</html>
