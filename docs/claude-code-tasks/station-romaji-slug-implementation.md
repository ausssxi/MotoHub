# 駅slugをローマ字に変換する実装指示書

## 目的

現在、9,032駅中：
- 主要30駅：ローマ字slug（`tokyo`, `shinjuku` 等）
- その他9,002駅：自動採番slug（`st-003908` 等）

となっており、URLがバラバラ。全駅でローマ字slugに統一したい。

## 現状の問題

### URLの一貫性がない
```
✅ /parking/station/tokyo           主要駅（綺麗）
❌ /parking/station/st-003908       下北沢駅（醜い）
```

### SEO的に弱い
- 検索結果に `st-XXXXXX` で表示 → クリック率低下
- アンカーテキストとURLの関連性が弱い

## 目標

### 全駅でローマ字slugに変換
```
✅ /parking/station/tokyo          既存（維持）
✅ /parking/station/shimokitazawa  新規（下北沢）
✅ /parking/station/sangenjaya     新規（三軒茶屋）
```

### 既存URLは301リダイレクトで保護
```
/parking/station/st-003908  →  /parking/station/shimokitazawa (301)
```

## 実装ステップ

### Step 1: マイグレーション

`stations` テーブルに `old_slug` カラムを追加（リダイレクト用）。

```bash
docker compose exec app php artisan make:migration add_old_slug_to_stations
```

```php
// database/migrations/xxxx_add_old_slug_to_stations.php

public function up()
{
    Schema::table('stations', function (Blueprint $table) {
        $table->string('old_slug', 50)->nullable()->after('slug');
        $table->index('old_slug');
    });
}

public function down()
{
    Schema::table('stations', function (Blueprint $table) {
        $table->dropIndex(['old_slug']);
        $table->dropColumn('old_slug');
    });
}
```

### Step 2: ローマ字変換ライブラリの導入

推奨ライブラリ：
- `wanasit/chrono` ではなく、以下のいずれか
- **`mb_convert_kana` + 独自辞書**（推奨、依存なし）
- または `ci-tree-labo/jp-romaji`（Composer、シンプル）

**推奨アプローチ：独自の変換ロジック**

PHP単体で実装（外部ライブラリ依存を最小化）：

```php
// app/Services/Station/JapaneseToRomajiConverter.php

namespace App\Services\Station;

class JapaneseToRomajiConverter
{
    /**
     * 駅名辞書（よく使われる駅名の正しいローマ字表記）
     * MeCab等なしで精度を出すため、主要駅名を辞書化
     */
    private array $dictionary = [
        '下北沢' => 'shimokitazawa',
        '三軒茶屋' => 'sangenjaya',
        '明治神宮前' => 'meiji-jingumae',
        '表参道' => 'omotesando',
        '代官山' => 'daikanyama',
        // ... 主要駅を辞書化
    ];
    
    public function convert(string $japaneseName): string
    {
        // 1. 辞書にあればそれを使用
        if (isset($this->dictionary[$japaneseName])) {
            return $this->dictionary[$japaneseName];
        }
        
        // 2. 辞書にない場合、ひらがな→ローマ字変換
        return $this->convertByRule($japaneseName);
    }
    
    private function convertByRule(string $name): string
    {
        // カタカナ→ひらがな変換
        $hiragana = mb_convert_kana($name, 'c');
        
        // ひらがな→ローマ字変換（ヘボン式）
        $romaji = $this->hiraganaToRomaji($hiragana);
        
        return $romaji;
    }
    
    // ひらがな→ローマ字変換テーブル
    private function hiraganaToRomaji(string $hiragana): string
    {
        $map = [
            'あ' => 'a', 'い' => 'i', 'う' => 'u', 'え' => 'e', 'お' => 'o',
            'か' => 'ka', 'き' => 'ki', 'く' => 'ku', 'け' => 'ke', 'こ' => 'ko',
            'が' => 'ga', 'ぎ' => 'gi', 'ぐ' => 'gu', 'げ' => 'ge', 'ご' => 'go',
            'さ' => 'sa', 'し' => 'shi', 'す' => 'su', 'せ' => 'se', 'そ' => 'so',
            'ざ' => 'za', 'じ' => 'ji', 'ず' => 'zu', 'ぜ' => 'ze', 'ぞ' => 'zo',
            'た' => 'ta', 'ち' => 'chi', 'つ' => 'tsu', 'て' => 'te', 'と' => 'to',
            'だ' => 'da', 'ぢ' => 'ji', 'づ' => 'zu', 'で' => 'de', 'ど' => 'do',
            'な' => 'na', 'に' => 'ni', 'ぬ' => 'nu', 'ね' => 'ne', 'の' => 'no',
            'は' => 'ha', 'ひ' => 'hi', 'ふ' => 'fu', 'へ' => 'he', 'ほ' => 'ho',
            'ば' => 'ba', 'び' => 'bi', 'ぶ' => 'bu', 'べ' => 'be', 'ぼ' => 'bo',
            'ぱ' => 'pa', 'ぴ' => 'pi', 'ぷ' => 'pu', 'ぺ' => 'pe', 'ぽ' => 'po',
            'ま' => 'ma', 'み' => 'mi', 'む' => 'mu', 'め' => 'me', 'も' => 'mo',
            'や' => 'ya', 'ゆ' => 'yu', 'よ' => 'yo',
            'ら' => 'ra', 'り' => 'ri', 'る' => 'ru', 'れ' => 're', 'ろ' => 'ro',
            'わ' => 'wa', 'を' => 'o', 'ん' => 'n',
            // 拗音
            'きゃ' => 'kya', 'きゅ' => 'kyu', 'きょ' => 'kyo',
            'しゃ' => 'sha', 'しゅ' => 'shu', 'しょ' => 'sho',
            'ちゃ' => 'cha', 'ちゅ' => 'chu', 'ちょ' => 'cho',
            'にゃ' => 'nya', 'にゅ' => 'nyu', 'にょ' => 'nyo',
            'ひゃ' => 'hya', 'ひゅ' => 'hyu', 'ひょ' => 'hyo',
            'みゃ' => 'mya', 'みゅ' => 'myu', 'みょ' => 'myo',
            'りゃ' => 'rya', 'りゅ' => 'ryu', 'りょ' => 'ryo',
            'ぎゃ' => 'gya', 'ぎゅ' => 'gyu', 'ぎょ' => 'gyo',
            'じゃ' => 'ja', 'じゅ' => 'ju', 'じょ' => 'jo',
            'びゃ' => 'bya', 'びゅ' => 'byu', 'びょ' => 'byo',
            'ぴゃ' => 'pya', 'ぴゅ' => 'pyu', 'ぴょ' => 'pyo',
            // 長音
            'ー' => '',
        ];
        
        // 長い拗音から先にマッチ
        $result = $hiragana;
        foreach (['きゃ', 'きゅ', 'きょ', 'しゃ', 'しゅ', 'しょ', ...] as $key) {
            if (isset($map[$key])) {
                $result = str_replace($key, $map[$key], $result);
            }
        }
        
        // 単音
        foreach ($map as $key => $value) {
            if (mb_strlen($key) === 1) {
                $result = str_replace($key, $value, $result);
            }
        }
        
        return $result;
    }
}
```

**⚠️ 重要：漢字→ひらがな変換の限界**

PHPには標準で漢字→ひらがな変換機能がない。以下のいずれかを選択：

**オプション1: 辞書ベース（推奨・確実）**
- 主要駅名を辞書に登録（1,000〜2,000駅）
- 辞書にない駅は既存の st-XXXXXX を維持

**オプション2: Yahoo! 日本語形態素解析API**
- 無料（1日50,000リクエストまで）
- 漢字→ひらがな変換可能
- APIキー取得と実装が必要

**オプション3: Composer パッケージ**
- `rsky/phpkakasi`（Kakasi PHP バインディング）
- Kakasi のインストールが別途必要

### Step 3: Slug生成コマンド

```php
// app/Console/Commands/GenerateStationSlugs.php

namespace App\Console\Commands;

use App\Models\Station;
use App\Services\Station\JapaneseToRomajiConverter;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class GenerateStationSlugs extends Command
{
    protected $signature = 'station:generate-slugs {--dry-run : 実行せずに変換結果だけ表示}';
    protected $description = '駅のslugをローマ字に変換';

    public function handle(JapaneseToRomajiConverter $converter)
    {
        $isDryRun = $this->option('dry-run');
        $stations = Station::all();
        $usedSlugs = [];
        $updated = 0;
        $skipped = 0;
        
        $bar = $this->output->createProgressBar($stations->count());
        
        foreach ($stations as $station) {
            // 主要駅は変更しない（既存slugを維持）
            if ($station->is_major) {
                $usedSlugs[$station->slug] = $station->id;
                $bar->advance();
                continue;
            }
            
            // 辞書にない場合はスキップ
            $romaji = $converter->convert($station->name);
            if ($romaji === null || $romaji === $station->name) {
                $skipped++;
                $bar->advance();
                continue;
            }
            
            // 重複回避
            $newSlug = $this->resolveUniqueSlug($romaji, $station, $usedSlugs);
            $usedSlugs[$newSlug] = $station->id;
            
            if ($isDryRun) {
                $this->newLine();
                $this->line("{$station->name} → {$newSlug}");
            } else {
                // old_slug を保存してからslug更新
                $station->update([
                    'old_slug' => $station->slug,
                    'slug' => $newSlug,
                ]);
                $updated++;
            }
            
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine();
        
        if ($isDryRun) {
            $this->info("Dry run 完了。変換対象: {$updated}駅、スキップ: {$skipped}駅");
        } else {
            $this->info("Slug更新完了: {$updated}駅、スキップ: {$skipped}駅");
        }
    }
    
    private function resolveUniqueSlug(string $baseSlug, Station $station, array &$usedSlugs): string
    {
        $slug = Str::slug($baseSlug);
        
        // 重複チェック
        if (!isset($usedSlugs[$slug])) {
            return $slug;
        }
        
        // 重複する場合、都道府県名を付与
        $prefSlug = $this->prefectureToSlug($station->prefecture);
        $withPref = "{$slug}-{$prefSlug}";
        
        if (!isset($usedSlugs[$withPref])) {
            return $withPref;
        }
        
        // それでも重複する場合、ID付与
        return "{$slug}-{$station->id}";
    }
    
    private function prefectureToSlug(string $prefecture): string
    {
        $map = [
            '北海道' => 'hokkaido',
            '青森県' => 'aomori',
            // ... 47都道府県
            '東京都' => 'tokyo',
            '京都府' => 'kyoto',
            '大阪府' => 'osaka',
            // ...
        ];
        
        return $map[$prefecture] ?? 'jp';
    }
}
```

### Step 4: リダイレクト設定

```php
// routes/web.php

// 既存の駅URL（st-XXXXXX）からローマ字slugへ301リダイレクト
Route::get('/parking/station/{oldSlug}', function ($oldSlug) {
    // パターン: st-XXXXXX の形式
    if (preg_match('/^st-\d+$/', $oldSlug)) {
        $station = Station::where('old_slug', $oldSlug)->first();
        if ($station) {
            return redirect("/parking/station/{$station->slug}", 301);
        }
    }
    
    // 通常のslugルートに処理を渡す
    return app(StationParkingController::class)->show($oldSlug);
})->where('oldSlug', '[^/]+');
```

または middleware で実装：

```php
// app/Http/Middleware/RedirectOldStationSlugs.php

public function handle($request, Closure $next)
{
    $path = $request->path();
    
    if (preg_match('#^parking/station/(st-\d+)$#', $path, $matches)) {
        $station = Station::where('old_slug', $matches[1])->first();
        if ($station) {
            return redirect("/parking/station/{$station->slug}", 301);
        }
    }
    
    return $next($request);
}
```

### Step 5: 動作確認

```bash
# Dry run で変換結果を確認
docker compose exec app php artisan station:generate-slugs --dry-run

# 問題なければ本実行
docker compose exec app php artisan station:generate-slugs

# テスト
docker compose exec app php artisan tinker
>>> Station::where('name', '下北沢')->first()->slug;
// 'shimokitazawa' が返る

# リダイレクトテスト
curl -I http://localhost:8080/parking/station/st-003908
# HTTP/1.1 301 Moved Permanently
# Location: /parking/station/shimokitazawa
```

## 注意事項

### 辞書の精度が重要

辞書ベースで進める場合、**最低でも主要1,000〜2,000駅**は辞書化したい。

辞書にない駅の対応：
- オプションA: `st-XXXXXX` のまま維持（slug更新せず）
- オプションB: Yahoo API で自動変換（要APIキー）
- オプションC: 手動登録を徐々に追加

### 既存URLの影響

- GoogleSearch Console にインデックス登録されているURL
- 外部からの被リンク（もしあれば）
- sitemap に含まれるURL

**全て301リダイレクトで救済**できるので、SEO資産は失われない。

### sitemap の再生成

```bash
docker compose exec app php artisan sitemap:generate
```

新しい slug で sitemap が再生成される。

### GSC対応

本番デプロイ後、GSC で：
1. 古いURLのインデックスステータスを確認
2. 新しいURLでsitemap再送信
3. 数週間で新URLに切り替わる

## 完了条件

- [ ] `old_slug` カラム追加完了
- [ ] JapaneseToRomajiConverter 実装完了
- [ ] GenerateStationSlugs コマンド実装完了
- [ ] 辞書に主要駅名を登録（最低500駅）
- [ ] Dry run で変換結果が適切
- [ ] 本実行で slug 更新成功
- [ ] 既存URL（st-XXXXXX）からのリダイレクト動作確認
- [ ] 新URL（shimokitazawa 等）で駅詳細ページ表示確認
- [ ] sitemap の再生成＆新slug反映確認
- [ ] 昨日実装した /parking/area/{pref}/{city}/{station} の動作確認

実装・動作確認完了後、結果を報告してほしい。

## MotoHub環境

- Laravel 12 / PHP 8.3
- Docker Compose（コンテナ名: motohub-app）
- Artisan: `docker compose exec app php artisan`
- 9,032駅のデータあり
- 既存slug: 主要30駅はローマ字、その他はst-XXXXXX
