# ショップページ強化 - SEO + 回遊率改善

## 背景
Google検索からショップページへのアクセスが急増中。
今このタイミングで強化すれば、さらにアクセスを伸ばせる。

## 現状確認（まずこれを実行）
```bash
cat backend/resources/views/shops/show.blade.php | head -80
grep -A 30 "function show" backend/app/Http/Controllers/Shop/ShopController.php
grep -n "shops" backend/routes/web.php
```

---

## 実装する改善（6つ）

### 1. JSON-LD構造化データ（LocalBusiness / MotorcycleDealer）

Google検索結果にリッチスニペット（住所・電話・営業時間）が表示されるようになる。
**これが最もSEO効果が高い。**

show.blade.phpの<head>内またはページ下部に追加:

```blade
<script type="application/ld+json">
{
    "@@context": "https://schema.org",
    "@@type": "MotorcycleDealer",
    "name": "{{ $shop->name }}",
    "url": "{{ route('shops.show', $shop->id) }}",
    @if($shop->address)
    "address": {
        "@@type": "PostalAddress",
        "streetAddress": "{{ $shop->address }}",
        "addressRegion": "{{ $shop->prefecture ?? '' }}",
        "addressCountry": "JP"
    },
    @endif
    @if($shop->tel)
    "telephone": "{{ $shop->tel }}",
    @endif
    @if($shop->latitude && $shop->longitude)
    "geo": {
        "@@type": "GeoCoordinates",
        "latitude": {{ $shop->latitude }},
        "longitude": {{ $shop->longitude }}
    },
    @endif
    "image": "{{ $shop->image_url ?? '' }}",
    "priceRange": "¥"
}
</script>
```

※ Bladeの@はJSON-LD内で @@context, @@type にエスケープ必要

### 2. パンくずリスト追加

```blade
<nav class="bg-white border-b border-gray-100">
    <div class="max-w-7xl mx-auto px-4 py-3">
        <ol class="flex items-center gap-1.5 text-xs text-gray-400 font-bold overflow-x-auto whitespace-nowrap">
            <li><a href="/" class="hover:text-blue-600">トップ</a></li>
            <li class="text-gray-300">›</li>
            @if($shop->prefecture)
            <li><a href="{{ route('bikes.search', ['prefecture' => $shop->prefecture]) }}" class="hover:text-blue-600">{{ $shop->prefecture }}</a></li>
            <li class="text-gray-300">›</li>
            @endif
            <li class="text-gray-700">{{ $shop->name }}</li>
        </ol>
    </div>
</nav>
```

パンくずのJSON-LDも追加:
```blade
<script type="application/ld+json">
{
    "@@context": "https://schema.org",
    "@@type": "BreadcrumbList",
    "itemListElement": [
        {
            "@@type": "ListItem",
            "position": 1,
            "name": "トップ",
            "item": "{{ url('/') }}"
        },
        @if($shop->prefecture)
        {
            "@@type": "ListItem",
            "position": 2,
            "name": "{{ $shop->prefecture }}",
            "item": "{{ route('bikes.search', ['prefecture' => $shop->prefecture]) }}"
        },
        @endif
        {
            "@@type": "ListItem",
            "position": {{ $shop->prefecture ? 3 : 2 }},
            "name": "{{ $shop->name }}"
        }
    ]
}
</script>
```

### 3. 店舗情報カードの改善

既存の店舗情報表示を統一デザインに改善:

```blade
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
    <h2 class="text-lg font-black text-gray-900 mb-4 flex items-center gap-2">
        <span class="bg-indigo-100 text-indigo-600 p-2 rounded-lg">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                <polyline points="9 22 9 12 15 12 15 22"/>
            </svg>
        </span>
        店舗情報
    </h2>
    <div class="space-y-3">
        @if($shop->address)
        <div class="flex items-start gap-3">
            <svg class="w-4 h-4 text-gray-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/>
                <circle cx="12" cy="10" r="3"/>
            </svg>
            <p class="text-sm text-gray-700">{{ $shop->address }}</p>
        </div>
        @endif
        
        @if($shop->tel)
        <div class="flex items-center gap-3">
            <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/>
            </svg>
            <a href="tel:{{ $shop->tel }}" class="text-sm text-blue-600 font-bold hover:underline">{{ $shop->tel }}</a>
        </div>
        @endif
        
        @if($shop->business_hours)
        <div class="flex items-center gap-3">
            <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
            </svg>
            <p class="text-sm text-gray-700">{{ $shop->business_hours }}</p>
        </div>
        @endif

        @if($shop->holiday)
        <div class="flex items-center gap-3">
            <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
            </svg>
            <p class="text-sm text-gray-700">定休日: {{ $shop->holiday }}</p>
        </div>
        @endif
    </div>
    
    {{-- 地図リンクボタン --}}
    @if($shop->latitude && $shop->longitude)
    <div class="flex gap-2 mt-4">
        <a href="https://www.google.com/maps/dir/?api=1&destination={{ $shop->latitude }},{{ $shop->longitude }}" 
           target="_blank" 
           class="flex-1 bg-blue-600 text-white text-center font-bold text-sm py-2.5 rounded-xl hover:bg-blue-700 transition-colors">
            ルート案内
        </a>
        <a href="https://www.google.com/maps?q={{ $shop->latitude }},{{ $shop->longitude }}" 
           target="_blank"
           class="flex-1 bg-gray-100 text-gray-700 text-center font-bold text-sm py-2.5 rounded-xl hover:bg-gray-200 transition-colors">
            Google Mapで見る
        </a>
    </div>
    @endif
</div>
```

### 4. 近くのバイク駐車場セクション追加

ShopController の show メソッドに追加:
```php
// 近くの駐車場（店舗の座標から半径約1km）
$nearbyParkings = collect();
if ($shop->latitude && $shop->longitude) {
    $nearbyParkings = \App\Models\BikeParking::where('is_active', 1)
        ->whereBetween('latitude', [$shop->latitude - 0.01, $shop->latitude + 0.01])
        ->whereBetween('longitude', [$shop->longitude - 0.01, $shop->longitude + 0.01])
        ->orderByRaw("ABS(latitude - ?) + ABS(longitude - ?)", [$shop->latitude, $shop->longitude])
        ->limit(5)
        ->get();
}
```

compactに 'nearbyParkings' を追加。

Blade:
```blade
@if($nearbyParkings->count() > 0)
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mt-6">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-black text-gray-900 flex items-center gap-2">
            <span class="bg-green-100 text-green-600 p-2 rounded-lg">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <rect x="1" y="3" width="15" height="13" rx="2"/><path d="M16 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>
                </svg>
            </span>
            近くのバイク駐車場
        </h2>
        @if($shop->latitude && $shop->longitude)
        <a href="{{ route('parking.index', ['lat' => $shop->latitude, 'lng' => $shop->longitude]) }}" 
           class="text-xs font-bold text-green-600 hover:underline">マップで見る →</a>
        @endif
    </div>
    
    @foreach($nearbyParkings as $parking)
    <a href="{{ route('parking.show', $parking->id) }}" 
       class="flex items-center justify-between py-3 border-b border-gray-50 last:border-0 hover:bg-gray-50 rounded-lg px-2 transition-colors">
        <div class="flex-1 min-w-0">
            <p class="text-sm font-bold text-gray-800 truncate">{{ $parking->name }}</p>
            <p class="text-[10px] text-gray-400 truncate">{{ $parking->address }}</p>
            @if($parking->available_hours)
            <p class="text-[10px] text-gray-400">{{ $parking->available_hours }}</p>
            @endif
        </div>
        <div class="text-right shrink-0 ml-4">
            @if($parking->price_detail)
            <p class="text-xs font-bold text-green-600">{{ Str::limit($parking->price_detail, 20) }}</p>
            @endif
            @if($parking->capacity)
            <p class="text-[10px] text-gray-400">{{ $parking->capacity }}台</p>
            @endif
        </div>
    </a>
    @endforeach
</div>
@endif
```

### 5. 同エリアの他の店舗セクション追加

ShopControllerに追加:
```php
// 同じ都道府県の他の店舗
$nearbyShops = \App\Models\Shop::where('prefecture', $shop->prefecture)
    ->where('id', '!=', $shop->id)
    ->inRandomOrder()
    ->limit(5)
    ->get();
```

compactに 'nearbyShops' を追加。

Blade:
```blade
@if($nearbyShops->count() > 0)
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mt-6">
    <h2 class="text-lg font-black text-gray-900 mb-4 flex items-center gap-2">
        <span class="bg-indigo-100 text-indigo-600 p-2 rounded-lg">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
            </svg>
        </span>
        {{ $shop->prefecture }}の他のバイクショップ
    </h2>
    
    @foreach($nearbyShops as $otherShop)
    <a href="{{ route('shops.show', $otherShop->id) }}" 
       class="flex items-center justify-between py-3 border-b border-gray-50 last:border-0 hover:bg-gray-50 rounded-lg px-2 transition-colors">
        <div>
            <p class="text-sm font-bold text-gray-800">{{ $otherShop->name }}</p>
            <p class="text-[10px] text-gray-400">{{ $otherShop->address ?? $otherShop->city ?? '' }}</p>
        </div>
        <svg class="w-4 h-4 text-gray-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
            <polyline points="9 18 15 12 9 6"/>
        </svg>
    </a>
    @endforeach
</div>
@endif
```

### 6.「この店舗に行ったことがありますか？」ボタン

レビューの代わりに、ワンタップで「行ったことある」を記録。
駐車場の used_count と同じ仕組み。

マイグレーション:
```php
Schema::table('shops', function (Blueprint $table) {
    $table->unsignedInteger('visited_count')->default(0)->after('reviews_count');
});
```

ルート追加:
```php
Route::post('/shops/{shop}/visited', [ShopController::class, 'visited'])->name('shops.visited');
```

コントローラー:
```php
public function visited(Shop $shop)
{
    $shop->increment('visited_count');
    return response()->json(['count' => $shop->visited_count]);
}
```

Blade:
```blade
<div class="bg-indigo-50 rounded-2xl p-4 mt-4 border border-indigo-100 text-center">
    <p class="text-sm font-bold text-gray-800 mb-3">この店舗に行ったことはありますか？</p>
    <button onclick="markVisited({{ $shop->id }})" id="visited-btn"
        class="bg-indigo-600 text-white font-bold text-sm px-6 py-2.5 rounded-xl hover:bg-indigo-700 transition-colors">
        🏍 行ったことある！
    </button>
    <p class="text-xs text-gray-400 mt-2" id="visited-count">
        {{ $shop->visited_count ?? 0 }}人が訪問済み
    </p>
</div>

<script>
function markVisited(shopId) {
    fetch(`/shops/${shopId}/visited`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Content-Type': 'application/json'
        }
    })
    .then(r => r.json())
    .then(data => {
        document.getElementById('visited-btn').textContent = '✅ 訪問済み！';
        document.getElementById('visited-btn').disabled = true;
        document.getElementById('visited-btn').classList.replace('bg-indigo-600', 'bg-gray-400');
        document.getElementById('visited-count').textContent = data.count + '人が訪問済み';
    });
}
</script>
```

---

## SEO meta タグの改善

```blade
<title>{{ $shop->name }} - バイクショップ {{ $shop->prefecture ?? '' }} | MotoHub</title>
<meta name="description" content="{{ $shop->name }}の店舗情報。{{ $shop->address ?? '' }} {{ $shop->tel ? 'TEL:'.$shop->tel : '' }} バイクの在庫・価格情報をチェック。">
<meta property="og:title" content="{{ $shop->name }} | MotoHub">
<meta property="og:description" content="{{ $shop->name }}のバイク在庫・店舗情報">
<meta property="og:type" content="website">
<meta property="og:url" content="{{ route('shops.show', $shop->id) }}">
```

---

## 確認してほしいファイル
- backend/resources/views/shops/show.blade.php
- backend/app/Http/Controllers/Shop/ShopController.php
- backend/app/Models/Shop.php（$fillable にvisited_count追加）
- backend/routes/web.php

## 実装順序
1. まず既存のshow.blade.phpとShopControllerを確認
2. JSON-LD構造化データ追加（SEO最重要）
3. パンくずリスト追加
4. 店舗情報カードの改善
5. nearbyParkings + nearbyShops のコントローラー追加
6. 近くの駐車場セクション追加
7. 同エリア店舗セクション追加
8. visited_count マイグレーション + ボタン追加
9. SEO metaタグ改善

各ステップ完了ごとに確認してから次に進んでください。
