---
title: "個人開発のバイクポータルにライダーズマップを実装した話（Leaflet + OSRM + POI 48,887件）"
emoji: "🗺️"
type: "tech"
topics: ["Laravel", "Leaflet", "OpenStreetMap", "個人開発", "PHP"]
published: false
---

## はじめに

[MotoHub](https://motohub.jp) という中古バイク検索ポータルを個人で開発・運営しています。GooBike・BDS・Webikeの3サイトから在庫データを集約し、車種カタログ・レビュー・ショップ検索などを提供するサービスです。

今回はその中で開発した**ライダーズマップ**について書きます。ツーリング計画に必要な情報——バイクショップ、駐車場、ガソリンスタンド、コンビニ、道の駅——を1つの地図上にまとめ、ルート描画や沿線スポット検索までできるようにしました。

<!-- TODO: スクリーンショット（マップ全体像） -->
![ライダーズマップの全体像](placeholder-map-overview.png)

## 作ったもの

ライダーズマップの主な機能は以下の通りです。

- **7レイヤー切り替え**: ショップ / 駐車場 / GS / コンビニ / 道の駅 / 記事 / お気に入り
- **現在地からの距離フィルタ**: 5km / 10km / 20km 圏内でフィルタリング
- **ルート描画**: 地図クリックでウェイポイントを追加、OSRM経由でルート計算
- **沿線POI検索**: ルートから300m圏内のGS・コンビニ・道の駅を検索
- **ブログ×マップ連携**: 地図上から直接ツーリングガイド記事を作成
- **お気に入りスポット保存**: 気になる場所をピン留めして後から確認

技術スタックは Laravel 12（PHP 8.3）+ Leaflet.js + OpenStreetMap。バックエンドからフロントまで全部1人で書いています。

<!-- TODO: スクリーンショット（レイヤー切り替えUI） -->
![レイヤー切り替え](placeholder-layers.png)

## POIデータの収集

### Overpass APIで48,887件取得

ガソリンスタンド・コンビニ・道の駅のデータは OpenStreetMap の [Overpass API](https://wiki.openstreetmap.org/wiki/Overpass_API) から取得しました。日本全国を8地方に分割してバッチ取得しています。

```php
// app/Console/Commands/FetchPois.php

private const REGIONS = [
    '北海道' => [41.3, 139.3, 45.6, 145.8],
    '東北'   => [36.8, 139.0, 41.5, 141.7],
    '関東'   => [34.8, 138.4, 37.0, 140.9],
    '中部'   => [34.6, 136.0, 37.8, 139.9],
    '近畿'   => [33.4, 134.5, 35.8, 136.9],
    '中国'   => [33.7, 130.8, 35.7, 134.4],
    '四国'   => [32.7, 132.0, 34.4, 134.8],
    '九州沖縄' => [24.0, 122.9, 34.3, 131.9],
];

private const TYPE_QUERIES = [
    'gas_station' => '[out:json][timeout:120];(node["amenity"="fuel"]({bbox});way["amenity"="fuel"]({bbox}););out center;',
    'convenience_store' => '[out:json][timeout:120];(node["shop"="convenience"]["name"~"セブン|ローソン|ファミリーマート|ミニストップ|デイリーヤマザキ|セイコーマート|ポプラ|NewDays"]({bbox}););out center;',
    'michi_no_eki' => '[out:json][timeout:120];(node["name"~"道の駅"]({bbox});way["name"~"道の駅"]({bbox}););out center;',
];
```

Overpass APIのクエリは OverpassQL で書きます。コンビニは主要チェーンの名前でフィルタリングして、個人商店などのノイズを排除しています。

ポイントは**地方ごとに分割してリクエスト**すること。日本全国を一括で取ると Overpass API のタイムアウト（120秒）に引っかかります。各リクエスト間に10秒のスリープも入れてAPIサーバーに負荷をかけないようにしています。

```php
foreach (self::REGIONS as $regionName => $bbox) {
    $query = str_replace('{bbox}', implode(',', $bbox), self::TYPE_QUERIES[$type]);

    $response = Http::timeout(120)
        ->withHeaders(['User-Agent' => 'MotoHub/1.0 (https://motohub.jp)'])
        ->withBody('data=' . urlencode($query), 'application/x-www-form-urlencoded')
        ->post(self::OVERPASS_URL);

    $elements = $response->json()['elements'] ?? [];

    foreach ($elements as $element) {
        $lat = $element['lat'] ?? $element['center']['lat'] ?? null;
        $lon = $element['lon'] ?? $element['center']['lon'] ?? null;
        if (!$lat || !$lon) continue;

        Poi::updateOrCreate(
            ['osm_id' => $element['id'], 'type' => $type],
            [
                'name'          => $tags['name'] ?? $tags['brand'] ?? '名称不明',
                'latitude'      => $lat,
                'longitude'     => $lon,
                'address'       => $this->buildAddress($tags),
                'brand'         => $tags['brand'] ?? $tags['operator'] ?? null,
                'opening_hours' => $tags['opening_hours'] ?? null,
            ]
        );
    }

    sleep(10); // API負荷軽減
}
```

`updateOrCreate` で `osm_id` + `type` をキーにしているので、何度実行しても冪等です。週次スケジュールで回しておけば勝手にデータが最新化されます。

### 逆ジオコーディング（住所の補完）

OSMのデータは `addr:*` タグが不完全なことが多く、住所フィールドが空のレコードが大量にできます。そこで別コマンドで逆ジオコーディングを走らせて住所を埋めました。

2段階のフォールバック構成にしています。

1. **国土地理院API**（メイン）: レート制限がゆるく、日本国内の精度が高い
2. **Nominatim**（フォールバック）: 国土地理院で取れなかった場合の補完

```php
// app/Console/Commands/GeocodePois.php

// 1. 国土地理院APIで試行
$address = $this->tryGsi($poi);
if ($address !== null) {
    $poi->update(['address' => $address]);
    usleep(200000); // 0.2秒
    continue;
}

// 2. フォールバック: Nominatim (OpenStreetMap)
$address = $this->tryNominatim($poi);
if ($address !== null) {
    $poi->update(['address' => $address]);
    sleep(1); // Nominatimのレート制限: 1リクエスト/秒
    continue;
}
```

国土地理院のAPIは「市区町村コード + 丁目」という形式で返してくるので、別途公開されている市区町村コードテーブル（`muni.js`）をパースして都道府県名付きの住所に変換しています。

```php
private function tryGsi(Poi $poi): ?string
{
    $response = Http::timeout(10)->get(self::GSI_GEOCODE_URL, [
        'lat' => $poi->latitude,
        'lon' => $poi->longitude,
    ]);

    $results = $response->json()['results'] ?? null;
    if (!$results || empty($results['muniCd'])) return null;

    $muniCd = ltrim($results['muniCd'], '0');
    $lv01Nm = $results['lv01Nm'] ?? '';
    $muniName = $this->muniMap[$muniCd] ?? '';

    return $muniName ? $muniName . $lv01Nm : null;
}
```

約48,000件のジオコーディングは `--limit=5000` で分割実行。国土地理院APIだけで9割以上カバーでき、Nominatim のフォールバックが必要なのは全体の数%でした。

## マップの実装

### Leaflet + OpenStreetMap

マップライブラリは [Leaflet](https://leafletjs.com/)。Google Maps API を使わない理由は明快で、**無料だから**です。個人開発では Maps API の課金が地味に痛い。OpenStreetMap + Leaflet なら完全無料で商用利用もOK。

```js
// public/js/riders/map.js

const layerConfig = {
    shop:              { endpoint: '/shops/api/area',                color: '#2563eb', label: '🏍️', title: 'ショップ' },
    parking:           { endpoint: '/parking/api/search',            color: '#16a34a', label: '🅿️', title: '駐車場' },
    gas_station:       { endpoint: '/api/pois?type=gas_station',     color: '#dc2626', label: '⛽',  title: 'GS' },
    convenience_store: { endpoint: '/api/pois?type=convenience_store', color: '#ea580c', label: '🏪', title: 'コンビニ' },
    michi_no_eki:      { endpoint: '/api/pois?type=michi_no_eki',    color: '#9333ea', label: '🛣️', title: '道の駅' },
    blog:              { endpoint: '/api/blog/map-pins',             color: '#0891b2', label: '✍️', title: '記事' },
    saved_spots:       { endpoint: '/api/spots',                     color: '#f59e0b', label: '⭐',  title: 'お気に入り' },
};
```

各レイヤーの設計ポイントは「**設定オブジェクト1つに集約**」すること。エンドポイント、色、アイコン、タイトルを `layerConfig` にまとめておけば、レイヤーの追加・変更が簡単です。新しいレイヤーを足す場合も、この1箇所にエントリを足すだけ。

### マーカーの描画

マーカーは Leaflet のデフォルトではなく、`L.divIcon` でカスタム描画しています。画像を使わず CSS だけで丸いアイコンを作れるので、レイヤーごとの色分けが楽です。

```js
function createIcon(color, label) {
    return L.divIcon({
        className: '',
        html: '<div style="width:30px;height:30px;border-radius:50%;background:#fff;' +
              'display:flex;align-items:center;justify-content:center;font-size:16px;' +
              'border:3px solid ' + color + ';box-shadow:0 2px 4px rgba(0,0,0,.3);">' +
              label + '</div>',
        iconSize: [30, 30],
        iconAnchor: [15, 15],
    });
}
```

### fetchのレースコンディション対策

マップの移動やレイヤー切り替えのたびに全レイヤーを再fetchしますが、前のfetchが完了する前に次のfetchが走ると、古いレスポンスが新しい状態を上書きしてしまいます。

これは**世代カウンター**で解決しました。

```js
let fetchGeneration = 0;

function fetchAllLayers() {
    var gen = ++fetchGeneration;

    // ... fetch処理 ...

    promises.push(
        fetch(url, fetchOpts)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (gen !== fetchGeneration) return; // 古い世代は破棄
                processLayerData(key, items, group);
            })
    );

    Promise.all(promises).then(function() {
        if (gen !== fetchGeneration) return; // 古い世代は破棄
        updateCards();
    });
}
```

`fetchAllLayers` が呼ばれるたびにカウンターをインクリメントし、fetchのコールバック内でカウンターが一致するか確認します。一致しなければ既に次のfetchが走っているので、結果を捨てます。シンプルだけど確実に効く手法です。

### POI APIのキャッシュ戦略

POI検索のAPIは `Cache::remember()` で結果をキャッシュしています。緯度経度を小数点3桁に丸めてキャッシュキーを生成することで、近い範囲のリクエストをヒットさせやすくしています。

```php
// app/Http/Controllers/Api/PoiApiController.php

$cacheKey = sprintf(
    'pois:%s:%.3f:%.3f:%.3f:%.3f',
    implode(',', $types),
    $swLat, $swLng, $neLat, $neLng
);

$pois = Cache::remember($cacheKey, 3600, function () use (...) {
    return Poi::inBounds($swLat, $swLng, $neLat, $neLng)
        ->ofType($types)
        ->limit(200)
        ->get();
});
```

Eloquent のスコープ `inBounds` / `ofType` でクエリを組み立てています。

```php
// app/Models/Poi.php

public function scopeInBounds(Builder $query, ...): Builder
{
    return $query
        ->whereBetween('latitude', [$swLat, $neLat])
        ->whereBetween('longitude', [$swLng, $neLng]);
}
```

`latitude` と `longitude` に複合インデックスを貼っているので、48,000件のテーブルでもバウンズ検索は十分高速です。

## ルート描画機能

### Leaflet Routing Machine + OSRM

ルート描画には [Leaflet Routing Machine](https://www.liedman.net/leaflet-routing-machine/) を使い、ルーティングエンジンは [OSRM](https://project-osrm.org/) の公開デモサーバーを利用しています。

地図をクリックしてウェイポイントを追加し、2点以上でルートが自動計算されます。ウェイポイントはドラッグで移動可能です。

```js
// public/js/riders/route.js

routingControl = L.Routing.control({
    waypoints: wpLatLngs,
    routeWhileDragging: false,
    addWaypoints: false,
    draggableWaypoints: false,
    show: false,
    createMarker: function() { return null; }, // 独自マーカーを使うので非表示
    lineOptions: {
        styles: [{ color: '#ec4899', weight: 5, opacity: 0.8 }],
    },
    router: L.Routing.osrmv1({
        serviceUrl: 'https://router.project-osrm.org/route/v1',
        profile: 'car',
    }),
}).addTo(map);
```

`createMarker: function() { return null; }` でデフォルトマーカーを消して、Start(緑)/Goal(赤)/中間点(ピンク) の独自マーカーを別途描画しています。Leaflet Routing Machine のデフォルトUIは日本語環境だと微妙なので、非表示にして自前で情報バーを作りました。

<!-- TODO: スクリーンショット（ルート描画） -->
![ルート描画](placeholder-route.png)

### 沿線POI検索（ルートから300m圏内）

ルートが描画されたら「沿線スポット表示」ボタンが出現し、ルート沿いのGS・コンビニ・道の駅を検索できます。ここが一番工夫したところです。

**問題**: OSRMが返すルート座標は数千〜数万ポイントにもなる。全座標をサーバーに送ると重い。

**解決**: クライアント側でルート座標を**500m間隔に間引き**してからAPIに送る。

```js
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
    // 最後のポイントは必ず含める
    result.push(coords[coords.length - 1]);
    return result;
}
```

サーバー側では、受け取った座標から**バウンディングボックスで候補を絞り**、各POIとルートセグメントの**点-線分間距離**を計算してフィルタリングします。

```php
// app/Http/Controllers/Api/PoiApiController.php

// 1. バウンディングボックスで粗い候補を取得
$candidates = Poi::inBounds($swLat, $swLng, $neLat, $neLng)
    ->ofType($types)
    ->limit(2000)
    ->get();

// 2. 各POIとルートセグメントの距離を正確に計算
foreach ($candidates as $poi) {
    $minDist = PHP_FLOAT_MAX;
    for ($i = 0; $i < count($coordinates) - 1; $i++) {
        $d = $this->pointToSegmentDistance(
            (float) $poi->latitude, (float) $poi->longitude,
            $coordinates[$i][0], $coordinates[$i][1],
            $coordinates[$i + 1][0], $coordinates[$i + 1][1]
        );
        $minDist = min($minDist, $d);
        if ($minDist <= $bufferM) break; // 閾値以下なら早期打ち切り
    }

    if ($minDist <= $bufferM) {
        $results[] = ['poi' => $poi, 'distance' => $minDist];
    }
}
```

### point-to-segment 距離計算

POIが「ルートからXm以内にあるか」を判定するには、単純な2点間距離ではなく**点から線分への最短距離**を計算する必要があります。

```php
private function pointToSegmentDistance(
    float $pLat, float $pLng,
    float $aLat, float $aLng,
    float $bLat, float $bLng
): float {
    $dAB_lat = $bLat - $aLat;
    $dAB_lng = $bLng - $aLng;
    $lenSq = $dAB_lat * $dAB_lat + $dAB_lng * $dAB_lng;

    if ($lenSq < 1e-12) {
        return $this->haversineMeters($pLat, $pLng, $aLat, $aLng);
    }

    // 線分上の最近点を投影で求める
    $t = (($pLat - $aLat) * $dAB_lat + ($pLng - $aLng) * $dAB_lng) / $lenSq;
    $t = max(0.0, min(1.0, $t)); // 線分の範囲にクランプ

    $projLat = $aLat + $t * $dAB_lat;
    $projLng = $aLng + $t * $dAB_lng;

    return $this->haversineMeters($pLat, $pLng, $projLat, $projLng);
}
```

ベクトルの内積で最近点を求めてからHaversine公式で距離を出す、というよくあるアルゴリズムです。`$t` を 0〜1 にクランプすることで、線分の外側に投影されるのを防いでいます。

東京→箱根のルート（約100km）でも、座標の間引き + 早期打ち切りのおかげで200ms程度で結果が返ります。

## ブログ×マップ連携

ツーリングガイドとマップを連携させて、**地図上から直接記事を作成**できるようにしました。

ライダーズマップで「ガイドを書く」ボタンを押すと、地図クリックでピンが立ちます。国土地理院の逆ジオコーディングAPIで住所を取得し、そのまま記事作成画面に遷移します。

```js
// public/js/riders/blog-pin.js

function placePin(latlng) {
    pinMarker = L.marker([lat, lng], {
        icon: L.divIcon({
            html: '<div style="...background:#0891b2;...">✍️</div>',
        }),
    }).addTo(map);

    // 国土地理院APIで逆ジオコーディング
    reverseGeocode(lat, lng, function(address) {
        pinMarker.setPopupContent(buildPopup(lat, lng, address));
    });
}
```

逆ジオコーディングのロジックはサーバー側のバッチ処理（`GeocodePois`）と同じ国土地理院APIをクライアント側JSからも呼んでいます。CORSが通るので直接fetchできるのがありがたい。

公開されたツーリングガイド記事は、マップ上に「記事」レイヤーとしてピン表示されます。記事をクリックすると詳細パネルに概要が表示され、そこから記事に遷移できます。

## スポット保存機能

ログインユーザーがマップ上で気になる場所を「お気に入り」として保存できる機能も追加しました。

「ピン留め」ボタンを押して地図をクリックすると、保存フォームがポップアップ表示されます。スポット名（逆ジオコーディングで自動入力）、カテゴリ（ツーリングスポット / 休憩所 / 絶景ポイント / その他）、メモを入力して保存。

```php
// app/Http/Controllers/Api/SavedSpotController.php

public function store(Request $request): JsonResponse
{
    $validated = $request->validate([
        'name'      => 'required|string|max:255',
        'latitude'  => 'required|numeric|between:-90,90',
        'longitude' => 'required|numeric|between:-180,180',
        'memo'      => 'nullable|string|max:1000',
        'category'  => 'nullable|in:touring_spot,rest_area,scenic_point,other',
    ]);

    $spot = UserSavedSpot::create([
        'user_id' => $request->user()->id,
        ...$validated,
    ]);

    return response()->json([...], 201);
}
```

保存されたスポットは「お気に入り」レイヤーとして地図上に星マーカーで表示されます。他のレイヤーと同じく `layerConfig` に追加するだけで、カード表示・詳細パネル・トグルが全部動きます。

このあたりは最初の設計で `layerConfig` オブジェクトに機能を集約しておいたのが効いていて、新レイヤーの追加コストが低くなっています。

<!-- TODO: スクリーンショット（ピン留めフォーム） -->
![ピン留めフォーム](placeholder-spot-pin.png)

### z-index の罠

実装中にハマったのが Leaflet ポップアップの z-index 問題。マップコンテナに `z-index: 10` を設定すると、内部のポップアップが絶対配置のコントロールボタン（`z-index: 40`）の**後ろに隠れてしまう**。

CSS のスタッキングコンテキストが原因で、マップ内の要素はどれだけ z-index を上げても、マップコンテナの z-index（10）を超えられません。

解決策は、ポップアップ表示中だけマップの z-index を引き上げるクラスを付与すること。

```css
#map.has-spot-popup { z-index: 45 !important; }
```

```js
// ピン配置時
document.getElementById('map').classList.add('has-spot-popup');
// ポップアップ閉じ時
document.getElementById('map').classList.remove('has-spot-popup');
```

## 技術スタック

| カテゴリ | 技術 |
|---------|------|
| バックエンド | Laravel 12 / PHP 8.3 |
| DB | MySQL 8.0 |
| キャッシュ | Redis |
| フロントエンド | Blade + Alpine.js + Tailwind CSS |
| 地図 | Leaflet 1.9 + OpenStreetMap |
| ルーティング | Leaflet Routing Machine + OSRM |
| POIデータ | Overpass API (OSM) |
| 逆ジオコーディング | 国土地理院API + Nominatim |
| 検索 | Meilisearch |
| インフラ | Docker / nginx / php-fpm |

外部の有料APIは一切使っていません。地図も検索もルーティングも、全部OSSか無料APIで賄っています。

## まとめ

個人開発でもここまでのマップ機能が実装できる時代です。OSMのエコシステム（Overpass API / OSRM / Nominatim / Leaflet）のおかげで、Google Maps APIに課金しなくても十分なクオリティの地図アプリが作れます。

実装のポイントをまとめると:

1. **POIデータ**: Overpass API + 地方分割バッチで48,887件を安定取得
2. **逆ジオコーディング**: 国土地理院API（メイン）+ Nominatim（フォールバック）の2段構え
3. **沿線POI検索**: 座標間引き + point-to-segment距離で実用的な速度を実現
4. **レイヤー設計**: 設定オブジェクト集約で拡張コストを最小化
5. **レースコンディション**: 世代カウンターでstale fetchを破棄

### 今後の展望

- ルートの保存・共有機能（URLパラメータでルートを復元）
- ユーザー投稿型のスポットデータベース
- GPXファイルのインポート/エクスポート
- オフライン地図対応（Service Worker + タイルキャッシュ）

質問やフィードバックがあれば、コメントで気軽にどうぞ。

---

*この記事で紹介した機能は [MotoHub ライダーズマップ](https://motohub.jp/riders-map) で実際に使えます。*
