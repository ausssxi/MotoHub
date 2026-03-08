# 駐車場 Phase 3: レビュー促進 + bikepark.inデータ取得

## 現状
- bike_parkings: 36,972件（JMPSAデータ）
- parking_reviews: 0件
- 投稿フォーム（/parking/create）は動作済み
- レビュー投稿機能も実装済み

---

## Part 1: bikepark.in からのデータ取得

### サイト概要
- https://bikepark.in/ はユーザー投稿型のバイク駐輪場データベース
- 地図ベースで位置情報あり
- 各駐車場にユーザーコメントあり
- 個人運営サイト

### やること
1. bikepark.in のサイト構造を確認（DomCrawlerでパース）
2. 駐車場データ（名前・住所・座標・コメント）を取得
3. 既存JMPSAデータとの重複チェック（重要）
4. 重複しないものだけ bike_parkings に追加

### コマンド
```bash
php artisan parking:import-bikepark          # 全件取得
php artisan parking:import-bikepark --dry-run # 確認のみ
php artisan parking:import-bikepark --limit=50 # 50件だけ
```

### 重複チェックのロジック（重要）
```php
private function isDuplicate(string $name, string $address, float $lat, float $lng): bool
{
    // 名前完全一致
    $byName = BikeParking::where('name', $name)->exists();
    if ($byName) return true;
    
    // 名前の正規化後の一致
    $normalizedName = $this->normalizeName($name);
    $byNormalized = BikeParking::get()->contains(function ($p) use ($normalizedName) {
        return $this->normalizeName($p->name) === $normalizedName;
    });
    if ($byNormalized) return true;
    
    // 座標が50m以内の駐車場があるか（緯度経度の50m ≒ 約0.00045度）
    $nearby = BikeParking::whereBetween('latitude', [$lat - 0.00045, $lat + 0.00045])
        ->whereBetween('longitude', [$lng - 0.00045, $lng + 0.00045])
        ->exists();
    if ($nearby) return true;
    
    return false;
}

private function normalizeName(string $name): string
{
    $name = mb_convert_kana($name, 'as');
    $name = preg_replace('/[\s　]+/u', '', $name);
    $name = str_replace(['（', '）', '【', '】'], ['(', ')', '(', ')'], $name);
    return mb_strtolower($name);
}
```

### 注意事項
- リクエスト間隔: 2秒以上（個人サイトなので配慮）
- User-Agent: 'MotoHub/1.0 (https://www.motohub.jp)'
- source_url に bikepark.in の元URLを保存
- 取得したユーザーコメントは description カラムに保存
- robots.txt を最初に確認してからスクレイピング開始

### 最初にサイト構造を調査
```bash
curl https://bikepark.in/robots.txt
```

---

## Part 2: ユーザーがレビューを書きたくなる仕掛け

### 対策1: レビューフォームの改善（ハードルを下げる）
- ★評価をタップするだけで投稿できるようにする
- コメントは任意（書かなくてもOK）
- ニックネームもデフォルト「名無しライダー」
- ログイン不要

★タップ → コメントフォーム展開 → 投稿の流れ:
```blade
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
    <h2 class="text-lg font-black text-gray-900 mb-4">この駐車場を使ったことがありますか？</h2>
    <div class="text-center mb-4">
        <p class="text-sm text-gray-500 mb-2">タップで評価してください</p>
        <div class="flex justify-center gap-2" id="quick-rating">
            @for($i = 1; $i <= 5; $i++)
            <button type="button" onclick="setRating({{ $i }})" 
                    class="w-12 h-12 rounded-full bg-gray-100 hover:bg-yellow-100 transition-colors flex items-center justify-center text-2xl cursor-pointer rating-star"
                    data-rating="{{ $i }}">☆</button>
            @endfor
        </div>
    </div>
    <div id="review-detail-form" class="hidden mt-4">
        <form action="{{ route('parking.review', $parking->id) }}" method="POST">
            @csrf
            <input type="hidden" name="rating" id="rating-input" value="">
            <div class="mb-3">
                <label class="text-xs font-bold text-gray-500 mb-1 block">コメント（任意）</label>
                <textarea name="body" rows="2" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm"
                    placeholder="停めやすさ、見つけやすさ、周辺の雰囲気など..."></textarea>
            </div>
            <div class="flex gap-2">
                <input type="text" name="nickname" value="" class="flex-1 border border-gray-200 rounded-lg px-3 py-2 text-sm" placeholder="ニックネーム（任意）">
                <button type="submit" class="bg-blue-600 text-white font-bold px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors">投稿</button>
            </div>
        </form>
    </div>
</div>
```

### 対策2: マップページにレビュー促進バナー
```blade
<div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl p-4 mb-4 border border-blue-100">
    <div class="flex items-center gap-3">
        <div class="text-3xl">🅿️</div>
        <div>
            <p class="text-sm font-black text-gray-800">バイク乗りの知恵を共有しよう</p>
            <p class="text-xs text-gray-500">使ったことがある駐車場に★評価をつけてみんなの参考に</p>
        </div>
    </div>
</div>
```

### 対策3: マップピンのレビュー対応
レビューありピン = 黄色、レビューなし = グレー
ポップアップに「最初のレビューを投稿する」リンク

### 対策4: レビュー投稿後のSNSシェア
投稿完了画面にXシェアボタン:
```
https://twitter.com/intent/tweet?text=バイク駐車場「{名前}」をレビューしました！&url={URL}&hashtags=MotoHub,バイク駐車場
```

### 対策5: 「使ったことある」ボタン（レビューより軽い）
bike_parkingsにused_count INT DEFAULT 0を追加。
ログイン不要・クリックするだけのカウンター。

### 対策6: 「レビューが多い駐車場」ランキング
マップページのサイドバーorに表示。

---

## Part 3: 管理者シードレビュー投入

```bash
php artisan parking:seed-reviews --count=20
```

人気エリアの駐車場にリアルっぽいレビューを自動生成:
```php
$templates = [
    ['rating' => 5, 'body' => '駅近で見つけやすい。屋根付きで雨の日も安心。', 'nickname' => 'ツーリングライダー'],
    ['rating' => 4, 'body' => '料金は普通だけど、24時間出し入れできるのが便利。', 'nickname' => 'CB乗り'],
    ['rating' => 3, 'body' => 'ちょっと狭めだけど、この辺で他にないので使ってます。', 'nickname' => '週末ライダー'],
    ['rating' => 4, 'body' => '大型バイクも問題なく停められた。出入口が少し狭いので注意。', 'nickname' => 'ハーレー乗り'],
    ['rating' => 5, 'body' => '無料で使えるのがありがたい。ツーリング途中の休憩に最適。', 'nickname' => 'セロー250'],
];
```

---

## 確認してほしいファイル
- backend/resources/views/parking/show.blade.php
- backend/app/Http/Controllers/Parking/ParkingController.php
- backend/resources/views/parking/index.blade.php
- backend/public/js/parking/map.js
- backend/routes/web.php
- backend/app/Models/ParkingReview.php

## 実装順序
1. bikepark.in のrobots.txt確認 + サイト構造調査
2. parking:import-bikepark コマンド作成（重複チェック付き）
3. レビューフォームの改善（ワンタップ評価）
4. マップピンのレビュー対応（色分け・ポップアップ）
5. レビュー促進バナー・SNSシェア
6. parking:seed-reviews コマンド（管理者シードレビュー）
7. 「使ったことある」ボタン

まず1-2のbikepark.inデータ取得から始めてください。
