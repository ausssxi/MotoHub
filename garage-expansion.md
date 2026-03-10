# 愛車ガレージ拡張 Phase A: 公開ページ化

## 現状
- /garage は全てauth必須（ログインしないと見れない）
- my_bikes: 1件, fuel_logs: 2件, users: 3人
- ビュー: mybikes/index.blade.php, mybikes/show.blade.php
- コントローラー: MyBikeController（index, show, store, destroy, storeFuel, storeMaintenance）

## 目的
ガレージを公開ページにしてSEO流入を獲得する。
車種詳細ページ（model_detail）に「この車種のオーナー」を表示してUGC感を出す。

---

## Step 1: 公開ガレージ一覧ページ

### URL: /garage/public
ログイン不要で閲覧可能。全ユーザーの愛車を一覧表示。

### ルート追加
```php
// 公開ルート（auth不要）
Route::get('/garage/public', [GaragePublicController::class, 'index'])->name('garage.public');
Route::get('/garage/public/{myBike}', [GaragePublicController::class, 'show'])->name('garage.public.show');
```

### コントローラー
```php
// app/Http/Controllers/MyBike/GaragePublicController.php
class GaragePublicController extends Controller
{
    public function index()
    {
        $bikes = MyBike::with(['user', 'bikeModel.manufacturer'])
            ->latest()
            ->paginate(20);
        
        return view('mybikes.public_index', compact('bikes'));
    }

    public function show(MyBike $myBike)
    {
        $myBike->load(['user', 'bikeModel.manufacturer', 'fuelLogs', 'maintenanceLogs']);
        
        return view('mybikes.public_show', compact('myBike'));
    }
}
```

### 公開一覧ページ（mybikes/public_index.blade.php）
- 全ユーザーの愛車をカード形式で表示
- 車種名、メーカー、年式、オーナーのニックネーム
- 画像（あれば）
- SEO: title「みんなの愛車ガレージ | MotoHub」
- 車種別フィルター、新着順/人気順ソート

### 公開詳細ページ（mybikes/public_show.blade.php）
- バイクの基本情報（車種、年式、走行距離）
- オーナーのニックネーム
- 給油ログ（燃費グラフ）
- 整備記録
- SEO: title「{ユーザー名}の{車種名} | 愛車ガレージ | MotoHub」
- 「自分も愛車を登録する」CTAボタン

---

## Step 2: 車種詳細ページに「この車種のオーナー」セクション追加

### model_detail.blade.php に追加
車種詳細ページ（/bikes/{mfr}/{slug}）の関連車種セクションの上あたりに：

```blade
{{-- この車種のオーナー --}}
@if(isset($owners) && $owners->count() > 0)
<div class="bg-white rounded-3xl shadow-sm p-6 sm:p-8 border border-gray-100">
    <h2 class="text-xl font-black text-gray-900 mb-6 flex items-center gap-2">
        <span class="bg-pink-100 text-pink-600 p-2 rounded-lg">
            <i data-lucide="users" class="w-5 h-5"></i>
        </span>
        {{ $model->name }} のオーナー
        <span class="text-sm text-gray-400 font-bold">({{ $owners->count() }}人)</span>
    </h2>
    
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
        @foreach($owners as $owner)
        <a href="{{ route('garage.public.show', $owner->id) }}" 
           class="group block bg-gray-50 rounded-xl p-4 border border-gray-100 hover:border-pink-300 hover:shadow-md transition-all">
            <div class="aspect-[4/3] rounded-lg bg-gray-200 overflow-hidden mb-3">
                @if($owner->image_url)
                    <img src="{{ $owner->image_url }}" alt="{{ $owner->name }}" 
                         class="w-full h-full object-cover group-hover:scale-105 transition-transform" 
                         loading="lazy" decoding="async">
                @else
                    <div class="w-full h-full flex items-center justify-center text-gray-400">
                        <i data-lucide="bike" class="w-8 h-8"></i>
                    </div>
                @endif
            </div>
            <p class="text-xs font-bold text-gray-500">{{ $owner->user->name ?? '名無しライダー' }}</p>
            <p class="text-sm font-black text-gray-800">{{ $owner->name }}</p>
            @if($owner->model_year)
                <span class="text-[10px] text-gray-400">{{ $owner->model_year }}年式</span>
            @endif
        </a>
        @endforeach
    </div>
    
    <div class="mt-6 text-center">
        <a href="{{ route('garage.public') }}" class="text-xs font-bold text-pink-600 hover:underline">
            みんなの愛車をもっと見る →
        </a>
    </div>
</div>
@endif

{{-- オーナーがいなくても登録を促すCTA --}}
@if(!isset($owners) || $owners->count() === 0)
<div class="bg-gradient-to-r from-pink-50 to-rose-50 rounded-3xl p-6 sm:p-8 border border-pink-100 text-center">
    <i data-lucide="heart" class="w-8 h-8 text-pink-400 mx-auto mb-2"></i>
    <h3 class="text-lg font-black text-gray-900 mb-2">{{ $model->name }} に乗っていますか？</h3>
    <p class="text-xs text-gray-500 mb-4">愛車を登録して、燃費記録・整備ログを管理しましょう</p>
    <a href="{{ route('mybikes.index') }}" class="inline-block bg-pink-600 text-white font-bold text-sm px-6 py-3 rounded-xl hover:bg-pink-700 transition-colors">
        愛車を登録する
    </a>
</div>
@endif
```

### BikeController.php の modelDetail メソッドに追加
```php
// オーナー一覧（この車種のMyBike）
$owners = \App\Models\MyBike::with('user')
    ->where('bike_model_id', $model->id)
    ->latest()
    ->limit(6)
    ->get();
```

compactに `'owners'` を追加。

---

## Step 3: ナビ・フッターに導線追加

### ナビゲーション
```blade
<a href="{{ route('garage.public') }}" class="...">
    <i data-lucide="heart" class="w-4 h-4"></i>
    みんなのガレージ
</a>
```

### フッター
```blade
<li>
    <a href="{{ route('garage.public') }}" class="footer-link">愛車ガレージ</a>
</li>
```

### トップページに「最近登録された愛車」セクション
```blade
<section class="mb-12">
    <h2 class="text-lg font-black text-gray-900 mb-4 flex items-center gap-2">
        <i data-lucide="heart" class="w-5 h-5 text-pink-500"></i>
        最近登録された愛車
    </h2>
    <div class="flex gap-4 overflow-x-auto pb-4 snap-x">
        @foreach($latestMyBikes as $myBike)
        <a href="{{ route('garage.public.show', $myBike->id) }}" class="snap-start shrink-0 w-48 group">
            <div class="aspect-[4/3] rounded-xl bg-gray-100 overflow-hidden mb-2">
                @if($myBike->image_url)
                    <img src="{{ $myBike->image_url }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform" loading="lazy">
                @endif
            </div>
            <p class="text-xs font-bold text-gray-500">{{ $myBike->bikeModel->manufacturer->name ?? '' }}</p>
            <p class="text-sm font-black text-gray-800 line-clamp-1">{{ $myBike->name }}</p>
        </a>
        @endforeach
    </div>
    
    @if(count($latestMyBikes) === 0)
    <div class="text-center py-8 bg-gray-50 rounded-xl">
        <p class="text-sm text-gray-500 font-bold">まだ愛車が登録されていません</p>
        <a href="{{ route('mybikes.index') }}" class="text-xs text-pink-600 font-bold hover:underline mt-2 inline-block">
            最初の1台を登録する →
        </a>
    </div>
    @endif
</section>
```

BikeController の index メソッドに追加:
```php
$latestMyBikes = \App\Models\MyBike::with(['bikeModel.manufacturer', 'user'])
    ->latest()
    ->limit(6)
    ->get();
```

---

## Step 4: SEO対策

### 公開ガレージ一覧
```blade
<title>みんなの愛車ガレージ - オーナーのバイクライフ | MotoHub</title>
<meta name="description" content="MotoHubユーザーの愛車コレクション。燃費記録・整備ログ・カスタム写真を公開中。">
```

### 公開ガレージ詳細
```blade
<title>{{ $myBike->user->name ?? '名無しライダー' }}の{{ $myBike->bikeModel->name ?? $myBike->name }} | 愛車ガレージ | MotoHub</title>
```

### サイトマップに追加
GenerateSitemap.php に /garage/public と各詳細ページを追加。
（現時点ではデータが少ないので、件数が増えてから対応でもOK）

---

## Step 5: 「愛車を登録する」導線の強化

ユーザーが愛車を登録したくなる仕掛け:

### 車両詳細ページ（show.blade.php）にCTA
```blade
{{-- 車両詳細の下部に --}}
<div class="bg-pink-50 rounded-xl p-4 mt-4 border border-pink-100 text-center">
    <p class="text-sm font-bold text-gray-800 mb-2">この車種に乗っていますか？</p>
    <a href="{{ route('mybikes.index') }}" class="text-xs font-bold text-pink-600 hover:underline">
        愛車ガレージに登録して燃費・整備を記録する →
    </a>
</div>
```

### 検索結果ページにもさりげなくCTA
```blade
{{-- 検索結果の途中に --}}
@if($loop->index === 5)
<div class="col-span-full bg-pink-50 rounded-xl p-4 text-center">
    <p class="text-sm font-bold text-gray-800">バイクを持っている方へ</p>
    <a href="{{ route('mybikes.index') }}" class="text-xs text-pink-600 font-bold">
        愛車ガレージで燃費・整備を記録しませんか？ →
    </a>
</div>
@endif
```

---

## 確認してほしいファイル
- backend/app/Http/Controllers/MyBike/MyBikeController.php
- backend/app/Models/MyBike.php
- backend/resources/views/mybikes/index.blade.php
- backend/resources/views/mybikes/show.blade.php
- backend/resources/views/bikes/model_detail.blade.php
- backend/resources/views/bikes/index.blade.php（トップ）
- backend/resources/views/bikes/show.blade.php（車両詳細）
- backend/resources/views/components/navigation.blade.php
- backend/resources/views/components/footer.blade.php
- backend/routes/web.php

## 実装順序
1. GaragePublicController + ルート作成
2. 公開一覧・詳細ページ作成
3. model_detailに「この車種のオーナー」追加
4. ナビ・フッターに導線追加
5. トップに「最近登録された愛車」追加
6. 各ページにCTA追加
7. SEO meta設定

まず既存のコードを確認してから、実装方針を提案してください。
