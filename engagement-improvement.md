# 滞在時間UP・回遊増加 - 3施策一括実装

## 現状の課題
- 滞在時間: 42秒（目標: 2分以上）
- リピーター: 0.04%
- 1ページだけ見て帰るユーザーが大半

---

## 施策A: 詳細ページの相互リンク強化

### 目的
車両→店舗→駐車場→車種が循環するリンク構造を作り、1人あたりPVを2〜3に増やす。

### 共通コンポーネント作成: cross-links.blade.php

```bash
# 確認: 既に作成済みか
ls backend/resources/views/components/cross-links.blade.php 2>/dev/null
```

なければ作成:

```blade
{{-- resources/views/components/cross-links.blade.php --}}
@props(['currentPage' => ''])

<div class="bg-gray-50 rounded-2xl p-6 mt-8">
    <h3 class="text-xs font-black text-gray-400 uppercase tracking-widest mb-4">MotoHubで探す</h3>
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        @if($currentPage !== 'search')
        <a href="{{ route('bikes.search') }}" class="flex items-center gap-2 text-sm font-bold text-gray-600 hover:text-blue-600 bg-white rounded-xl p-3 border border-gray-100 transition-colors">
            🔍 中古車検索
        </a>
        @endif
        @if($currentPage !== 'models')
        <a href="{{ route('bikes.models') }}" class="flex items-center gap-2 text-sm font-bold text-gray-600 hover:text-blue-600 bg-white rounded-xl p-3 border border-gray-100 transition-colors">
            🏍 車種一覧
        </a>
        @endif
        @if($currentPage !== 'parking')
        <a href="{{ route('parking.index') }}" class="flex items-center gap-2 text-sm font-bold text-gray-600 hover:text-blue-600 bg-white rounded-xl p-3 border border-gray-100 transition-colors">
            🅿️ 駐車場マップ
        </a>
        @endif
        @if($currentPage !== 'shops')
        <a href="{{ route('shops.map') }}" class="flex items-center gap-2 text-sm font-bold text-gray-600 hover:text-blue-600 bg-white rounded-xl p-3 border border-gray-100 transition-colors">
            🏪 店舗マップ
        </a>
        @endif
        <a href="{{ route('ar.index') }}" class="flex items-center gap-2 text-sm font-bold text-gray-600 hover:text-blue-600 bg-white rounded-xl p-3 border border-gray-100 transition-colors">
            📸 ARで探す
        </a>
        <a href="{{ route('garage.public') }}" class="flex items-center gap-2 text-sm font-bold text-gray-600 hover:text-blue-600 bg-white rounded-xl p-3 border border-gray-100 transition-colors">
            ❤️ みんなの愛車
        </a>
    </div>
</div>
```

### 各ページに追加

#### 車両詳細（bikes/show.blade.php）
フッターの上に追加:
```blade
<x-cross-links currentPage="search" />
```

さらに、既存のコンテンツ内に文脈に合ったリンクを追加:
```blade
{{-- 店舗情報セクションの中に --}}
@if($listing->shop)
<a href="{{ route('shops.show', $listing->shop->id) }}" 
   class="inline-flex items-center gap-1 text-xs font-bold text-blue-600 hover:underline mt-2">
    この店舗の他の在庫を見る →
</a>
@endif

{{-- 車種名の近くに --}}
@if($listing->bike_model_id && $bikeModelForUrl)
<a href="{{ $bikeModelForUrl->seo_url }}" 
   class="inline-flex items-center gap-1 text-xs font-bold text-blue-600 hover:underline">
    {{ $listing->bike_model_name }}の相場・スペックを見る →
</a>
@endif
```

#### 車種詳細（bikes/model_detail.blade.php）
```blade
<x-cross-links currentPage="models" />
```

#### 店舗詳細（shops/show.blade.php）
```blade
<x-cross-links currentPage="shops" />
```

#### 駐車場詳細（parking/show.blade.php）
```blade
<x-cross-links currentPage="parking" />
```

---

## 施策B: 車種詳細のセクションナビ（スティッキータブ）

### 目的
model_detailページはセクションが多く長い。
スティッキーなセクションナビを追加して、目的の情報にすぐ到達できるようにする。
SEO的にはタブで非表示にするのではなく、アンカーリンクのナビゲーション。

### 確認
```bash
grep -n "id=" backend/resources/views/bikes/model_detail.blade.php | head -20
```

### 実装

model_detail.blade.php のメインコンテンツの上部（パンくずリストの下）に追加:

```blade
{{-- セクションナビ（スティッキー） --}}
<div id="section-nav" class="sticky top-[64px] bg-white/95 backdrop-blur-sm border-b border-gray-100 z-20 -mx-4 px-4 transition-shadow">
    <div class="max-w-7xl mx-auto">
        <nav class="flex gap-1 overflow-x-auto py-2 scrollbar-hide">
            <a href="#section-overview" class="section-nav-link text-xs font-bold text-gray-400 whitespace-nowrap py-1.5 px-3 rounded-lg hover:bg-gray-100 hover:text-gray-700 transition-colors">
                概要
            </a>
            <a href="#section-specs" class="section-nav-link text-xs font-bold text-gray-400 whitespace-nowrap py-1.5 px-3 rounded-lg hover:bg-gray-100 hover:text-gray-700 transition-colors">
                スペック
            </a>
            <a href="#section-resale" class="section-nav-link text-xs font-bold text-gray-400 whitespace-nowrap py-1.5 px-3 rounded-lg hover:bg-gray-100 hover:text-gray-700 transition-colors">
                買取相場
            </a>
            <a href="#section-price" class="section-nav-link text-xs font-bold text-gray-400 whitespace-nowrap py-1.5 px-3 rounded-lg hover:bg-gray-100 hover:text-gray-700 transition-colors">
                中古価格
            </a>
            <a href="#section-listings" class="section-nav-link text-xs font-bold text-gray-400 whitespace-nowrap py-1.5 px-3 rounded-lg hover:bg-gray-100 hover:text-gray-700 transition-colors">
                販売中
            </a>
            <a href="#section-reviews" class="section-nav-link text-xs font-bold text-gray-400 whitespace-nowrap py-1.5 px-3 rounded-lg hover:bg-gray-100 hover:text-gray-700 transition-colors">
                レビュー
            </a>
            <a href="#section-faq" class="section-nav-link text-xs font-bold text-gray-400 whitespace-nowrap py-1.5 px-3 rounded-lg hover:bg-gray-100 hover:text-gray-700 transition-colors">
                FAQ
            </a>
            <a href="#section-owners" class="section-nav-link text-xs font-bold text-gray-400 whitespace-nowrap py-1.5 px-3 rounded-lg hover:bg-gray-100 hover:text-gray-700 transition-colors">
                オーナー
            </a>
        </nav>
    </div>
</div>
```

各セクションのdivにidを付与（既にあればそのまま）:
```blade
<div id="section-overview" class="...">概要セクション</div>
<div id="section-specs" class="...">スペックセクション</div>
<div id="section-resale" class="...">買取相場セクション</div>
<div id="section-price" class="...">中古価格セクション</div>
<div id="section-listings" class="...">販売中セクション</div>
<div id="section-reviews" class="...">レビューセクション</div>
<div id="section-faq" class="...">FAQセクション</div>
<div id="section-owners" class="...">オーナーセクション</div>
```

### JavaScript（スクロール連動ハイライト + スムーススクロール）

```blade
<script>
document.addEventListener('DOMContentLoaded', () => {
    const navLinks = document.querySelectorAll('.section-nav-link');
    const sectionIds = Array.from(navLinks).map(link => link.getAttribute('href').substring(1));
    const navBar = document.getElementById('section-nav');
    
    // スムーススクロール
    navLinks.forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            const targetId = link.getAttribute('href').substring(1);
            const target = document.getElementById(targetId);
            if (target) {
                const navHeight = navBar.offsetHeight + 64; // ナビバー + セクションナビの高さ
                const targetPosition = target.offsetTop - navHeight;
                window.scrollTo({ top: targetPosition, behavior: 'smooth' });
            }
        });
    });
    
    // スクロール連動ハイライト
    const updateActiveNav = () => {
        const navHeight = navBar.offsetHeight + 64 + 20; // オフセット
        let currentSection = '';
        
        sectionIds.forEach(id => {
            const section = document.getElementById(id);
            if (section && window.scrollY >= section.offsetTop - navHeight) {
                currentSection = id;
            }
        });
        
        navLinks.forEach(link => {
            const linkTarget = link.getAttribute('href').substring(1);
            if (linkTarget === currentSection) {
                link.classList.remove('text-gray-400');
                link.classList.add('text-blue-600', 'bg-blue-50');
            } else {
                link.classList.remove('text-blue-600', 'bg-blue-50');
                link.classList.add('text-gray-400');
            }
        });
    };
    
    // パッシブスクロールリスナー
    window.addEventListener('scroll', updateActiveNav, { passive: true });
    updateActiveNav();
    
    // セクションナビにシャドウ追加（スクロール時）
    window.addEventListener('scroll', () => {
        if (window.scrollY > 200) {
            navBar.classList.add('shadow-sm');
        } else {
            navBar.classList.remove('shadow-sm');
        }
    }, { passive: true });
});
</script>
```

### CSS追加
```blade
<style>
.scrollbar-hide::-webkit-scrollbar { display: none; }
.scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
</style>
```

---

## 施策C: 「この車種を見た人はこれも見ています」セクション

### 目的
Amazonの「この商品を見た人はこれも見ています」と同じ仕組み。
同じカテゴリ・同じ価格帯の車種を表示して回遊を促す。

### 車両詳細（bikes/show.blade.php）に追加

#### BikeController の show メソッドに追加:
```php
// この車種を見た人はこれも見ています
$alsoViewed = collect();
if ($listing->bike_model_id) {
    $alsoViewed = \App\Models\Listing::where('is_sold_out', 0)
        ->where('id', '!=', $listing->id)
        ->where(function($query) use ($listing) {
            // 同じカテゴリ or 同じ価格帯（±20%）
            $query->where('category_id', $listing->category_id)
                  ->orWhereBetween('total_price', [
                      $listing->total_price * 0.8,
                      $listing->total_price * 1.2
                  ]);
        })
        ->whereNotNull('total_price')
        ->where('total_price', '>', 0)
        ->inRandomOrder()
        ->limit(6)
        ->get();
}
```

compactに 'alsoViewed' を追加。

#### Blade:
```blade
{{-- この車種を見た人はこれも見ています --}}
@if($alsoViewed->count() > 0)
<div class="mt-8">
    <h2 class="text-lg font-black text-gray-900 mb-4 flex items-center gap-2">
        <span class="bg-purple-100 text-purple-600 p-2 rounded-lg">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                <circle cx="12" cy="12" r="3"/>
            </svg>
        </span>
        この車両を見た人はこれも見ています
    </h2>
    
    <div class="flex gap-4 overflow-x-auto pb-4 snap-x snap-mandatory scrollbar-hide">
        @foreach($alsoViewed as $item)
        <a href="{{ route('bikes.show', $item->id) }}" 
           class="snap-start shrink-0 w-[200px] sm:w-[240px] bg-white rounded-xl border border-gray-100 overflow-hidden hover:shadow-md transition-shadow group">
            <div class="aspect-[4/3] bg-gray-100 overflow-hidden">
                @if($item->image_url)
                <img src="{{ $item->image_url }}" alt="{{ $item->name }}" 
                     class="w-full h-full object-cover group-hover:scale-105 transition-transform"
                     loading="lazy" decoding="async">
                @else
                <div class="w-full h-full flex items-center justify-center text-gray-300">
                    <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1">
                        <rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/>
                    </svg>
                </div>
                @endif
            </div>
            <div class="p-3">
                @if($item->total_price)
                <p class="text-sm font-black text-red-600 mb-1">{{ number_format($item->total_price / 10000, 1) }}万円</p>
                @endif
                <p class="text-xs font-bold text-gray-800 line-clamp-2">{{ $item->name }}</p>
                <div class="flex items-center gap-2 mt-1 text-[10px] text-gray-400">
                    @if($item->model_year)<span>{{ $item->model_year }}年</span>@endif
                    @if($item->mileage)<span>{{ number_format($item->mileage) }}km</span>@endif
                    @if($item->prefecture)<span>{{ $item->prefecture }}</span>@endif
                </div>
            </div>
        </a>
        @endforeach
    </div>
</div>
@endif
```

### 車種詳細（model_detail.blade.php）にも追加

#### BikeController の modelDetail メソッドに追加:
```php
// 似た車種（同カテゴリ・同排気量帯）
$similarModels = \App\Models\BikeModel::where('id', '!=', $model->id)
    ->where(function($query) use ($model) {
        if ($model->category_id) {
            $query->where('category_id', $model->category_id);
        }
    })
    ->whereHas('listings', function($query) {
        $query->where('is_sold_out', 0);
    })
    ->withCount(['listings' => function($query) {
        $query->where('is_sold_out', 0);
    }])
    ->orderByDesc('listings_count')
    ->limit(6)
    ->get();
```

compactに 'similarModels' を追加。

#### Blade（model_detail.blade.php の関連車種セクション付近に追加）:
```blade
@if($similarModels->count() > 0)
<div class="bg-white rounded-2xl shadow-sm p-6 sm:p-8 border border-gray-100">
    <h2 class="text-lg font-black text-gray-900 mb-6 flex items-center gap-2">
        <span class="bg-purple-100 text-purple-600 p-2 rounded-lg">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                <circle cx="12" cy="12" r="3"/>
            </svg>
        </span>
        {{ $model->name }}を見た人はこの車種も見ています
    </h2>
    
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
        @foreach($similarModels as $similar)
        <a href="{{ $similar->seo_url ?? route('bikes.modelDetail', $similar->id) }}" 
           class="group bg-gray-50 rounded-xl p-4 border border-gray-100 hover:border-blue-300 hover:shadow-md transition-all">
            <div class="aspect-[4/3] rounded-lg bg-gray-200 overflow-hidden mb-3">
                @if($similar->image_url)
                <img src="{{ $similar->image_url }}" alt="{{ $similar->name }}" 
                     class="w-full h-full object-cover group-hover:scale-105 transition-transform"
                     loading="lazy" decoding="async">
                @else
                <div class="w-full h-full flex items-center justify-center text-gray-400">🏍</div>
                @endif
            </div>
            <p class="text-xs text-gray-400 font-bold">{{ $similar->manufacturer->name ?? '' }}</p>
            <p class="text-sm font-black text-gray-800 line-clamp-1">{{ $similar->name }}</p>
            <p class="text-xs text-blue-600 font-bold mt-1">{{ $similar->listings_count }}台販売中</p>
        </a>
        @endforeach
    </div>
</div>
@endif
```

---

## 確認してほしいファイル
- backend/resources/views/bikes/show.blade.php（車両詳細）
- backend/resources/views/bikes/model_detail.blade.php（車種詳細）
- backend/resources/views/shops/show.blade.php（店舗詳細）
- backend/resources/views/parking/show.blade.php（駐車場詳細）
- backend/app/Http/Controllers/Bike/BikeController.php（show, modelDetail）
- backend/resources/views/components/（cross-links.blade.php 新規作成）

## 実装順序
1. cross-links.blade.php 共通コンポーネント作成
2. 全4つの詳細ページに <x-cross-links> 追加
3. 車両詳細に「この車両を見た人はこれも見ています」追加
4. 車種詳細に「似た車種」追加
5. 車種詳細にセクションナビ（スティッキー）追加
6. 車両詳細に店舗・車種への文脈リンク追加
7. 動作確認

各ステップ完了ごとに確認してから次に進んでください。
