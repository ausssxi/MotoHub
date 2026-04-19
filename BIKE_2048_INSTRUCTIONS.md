# バイク2048パズル — Claude Code実装指示書

## 概要

MotoHubのゲーム第3弾。通常の2048パズルのタイル（数字）をバイクに置き換えたゲーム。
カブ50から始めてタイルを合体させ、最終目標のゴールドウイングを目指す。

URL: `/puzzle` （motohub.jp/puzzle）

技術スタック: React（クイズ・わらしべと同じ島アーキテクチャ）
Bladeテンプレート: `resources/views/puzzle/index.blade.php`
Reactコンポーネント: `resources/js/components/BikePuzzle.jsx`

---

## ゲームルール（通常の2048と同じ）

- 4×4のグリッド
- 上下左右のスワイプ（PC: 矢印キー）でタイル全体が移動
- 同じレベルのタイルが隣接するとぶつかって合体、1レベルアップ
- 合体時にスコア加算
- 毎ターン、空きマスにランダムでレベル1（90%）かレベル2（10%）が出現
- 移動できなくなったらゲームオーバー
- レベル11（ゴールドウイング）を作ったらクリア

---

## 11段階のバイク構成

| レベル | 数値 | バイク名 | 排気量 | タイル背景色 |
|--------|------|----------|--------|-------------|
| 1 | 2 | スーパーカブ50 | 50cc | #FFF3E0 |
| 2 | 4 | モンキー125 | 125cc | #FFF9C4 |
| 3 | 8 | PCX | 160cc | #E3F2FD |
| 4 | 16 | レブル250 | 250cc | #E0E0E0 |
| 5 | 32 | Ninja400 | 400cc | #C8E6C9 |
| 6 | 64 | GB350 | 350cc | #D7CCC8 |
| 7 | 128 | Z900RS | 900cc | #FFE0B2 |
| 8 | 256 | CB1300SF | 1300cc | #FFCDD2 |
| 9 | 512 | ハヤブサ | 1340cc | #CFD8DC |
| 10 | 1024 | H2 | 998cc | #B2EBF2 |
| 11 | 2048 | ゴールドウイング | 1833cc | #FFD54F |

---

## 画像

各バイクのイラスト画像を使用。画像は `public/images/puzzle/` に配置。

ファイル名:
- `public/images/puzzle/level-1.png` （スーパーカブ50）
- `public/images/puzzle/level-2.png` （モンキー125）
- ...
- `public/images/puzzle/level-11.png` （ゴールドウイング）

画像サイズ: 正方形、200×200px推奨（タイル内に収まるサイズ）

※画像は後から配置するので、最初はバイク名テキスト＋背景色のみで実装してOK。
画像がある場合は表示、ない場合はテキストフォールバック:

```jsx
{imageExists ? (
  <img src={`/images/puzzle/level-${level}.png`} alt={bikeName} />
) : (
  <span className="text-xs font-bold">{bikeName}</span>
)}
```

---

## UI構成

### レイアウト（モバイルファースト）

```
┌─────────────────────────┐
│  🏍️ バイク2048          │  ← ヘッダー
├─────────────────────────┤
│  スコア: 12400  BEST: 28800 │  ← スコアバー
├─────────────────────────┤
│                         │
│   ┌──┬──┬──┬──┐        │
│   │  │  │  │  │        │  ← 4×4 グリッド
│   ├──┼──┼──┼──┤        │
│   │  │  │  │  │        │
│   ├──┼──┼──┼──┤        │
│   │  │  │  │  │        │
│   ├──┼──┼──┼──┤        │
│   │  │  │  │  │        │
│   └──┴──┴──┴──┘        │
│                         │
├─────────────────────────┤
│  🔄 リセット  ↩ 1手戻す  │  ← コントロール
├─────────────────────────┤
│  次のバイク: Ninja400    │  ← 次の合体で出るバイク
│  ┌──┬──┬──┬──┬──┬...    │  ← 進化チャート（横スクロール）
│  │🛵│🏍│🏍│  │  │      │     到達済みはカラー、未到達はグレー
│  └──┴──┴──┴──┴──┴...    │
├─────────────────────────┤
│  MotoHubで中古バイクを探す →  │  ← CTA
└─────────────────────────┘
```

### グリッドのスタイル

```css
.grid-container {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 8px;
  background: #bbada0;  /* 2048の伝統的な背景色 */
  border-radius: 12px;
  padding: 8px;
  max-width: 400px;
  margin: 0 auto;
  aspect-ratio: 1;
}

.tile {
  border-radius: 8px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  font-weight: bold;
  transition: transform 0.15s ease, opacity 0.15s ease;
}
```

---

## ゲームロジック（React）

### State構造

```jsx
const [grid, setGrid] = useState(initialGrid());      // 4x4 配列
const [score, setScore] = useState(0);                 // 現在のスコア
const [bestScore, setBestScore] = useState(0);         // ハイスコア（localStorage）
const [gameOver, setGameOver] = useState(false);        // ゲームオーバー
const [gameWon, setGameWon] = useState(false);          // クリア（レベル11到達）
const [history, setHistory] = useState([]);             // 1手戻す用
const [maxLevel, setMaxLevel] = useState(1);            // 到達最大レベル
```

### 初期化

```jsx
function initialGrid() {
  const grid = Array(4).fill(null).map(() => Array(4).fill(0));
  addRandomTile(grid);
  addRandomTile(grid);
  return grid;
}

function addRandomTile(grid) {
  const empty = [];
  for (let r = 0; r < 4; r++) {
    for (let c = 0; c < 4; c++) {
      if (grid[r][c] === 0) empty.push([r, c]);
    }
  }
  if (empty.length === 0) return;
  const [r, c] = empty[Math.floor(Math.random() * empty.length)];
  grid[r][c] = Math.random() < 0.9 ? 1 : 2;  // レベル1(90%) or レベル2(10%)
}
```

### 移動ロジック

```jsx
function move(direction) {
  // 1. historyに現在のgridとscoreを保存（1手戻す用）
  // 2. directionに応じてタイルを移動・合体
  // 3. 合体時にスコア加算（合体後のレベルに対応するスコア）
  // 4. 移動が発生した場合のみ新タイル追加
  // 5. ゲームオーバー判定
  // 6. レベル11到達判定
}
```

スコア計算: 合体するとそのレベルの2のべき乗を加算
- レベル1+1→2: +4点
- レベル2+2→3: +8点
- レベル10+10→11: +2048点

### 操作

```jsx
// キーボード（PC）
useEffect(() => {
  const handleKeyDown = (e) => {
    if (['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'].includes(e.key)) {
      e.preventDefault();
      move(e.key.replace('Arrow', '').toLowerCase());
    }
  };
  window.addEventListener('keydown', handleKeyDown);
  return () => window.removeEventListener('keydown', handleKeyDown);
}, [grid]);

// スワイプ（モバイル）
// touchstart/touchendの座標差から方向を判定
// 閾値30px以上のスワイプで移動
```

---

## アニメーション（CSS）

### タイル移動

```css
.tile {
  transition: transform 0.15s ease-in-out;
}
```

### 合体時のポップ

```css
@keyframes pop {
  0% { transform: scale(1); }
  50% { transform: scale(1.2); }
  100% { transform: scale(1); }
}
.tile-merged {
  animation: pop 0.2s ease;
}
```

### 新タイル出現

```css
@keyframes appear {
  0% { opacity: 0; transform: scale(0); }
  100% { opacity: 1; transform: scale(1); }
}
.tile-new {
  animation: appear 0.2s ease;
}
```

---

## 効果音

わらしべ・クイズと同じく `public/audio/puzzle/` に配置。

| イベント | ファイル | 説明 |
|----------|---------|------|
| 移動 | move.mp3 | タイルがスライドする音 |
| 合体 | merge.mp3 | 合体時の「カチッ」音 |
| レベルアップ | levelup.mp3 | 新しいバイクに到達 |
| ゲームオーバー | gameover.mp3 | 終了音 |
| クリア | clear.mp3 | ゴールドウイング到達時のファンファーレ |

※音声ファイルは後から配置。まずは音なしで実装してOK。

```jsx
const playSound = (name) => {
  try {
    const audio = new Audio(`/audio/puzzle/${name}.mp3`);
    audio.volume = 0.5;
    audio.play().catch(() => {});
  } catch (e) {}
};
```

---

## ゲームオーバー / クリア画面

### ゲームオーバー

```
┌─────────────────────────┐
│                         │
│      GAME OVER          │
│                         │
│   スコア: 12,400        │
│   最高レベル: Z900RS     │
│                         │
│   [もう一度]  [シェア]   │
│                         │
│   MotoHubでZ900RSを探す →│
│                         │
└─────────────────────────┘
```

### クリア（レベル11到達）

```
┌─────────────────────────┐
│                         │
│   🎉 おめでとう！       │
│   ゴールドウイング到達！  │
│                         │
│   スコア: 48,200        │
│                         │
│   [続ける]  [シェア]     │
│                         │
│   MotoHubでゴールドウイング│
│   の相場を見る →         │
│                         │
└─────────────────────────┘
```

クリア後も「続ける」でプレイ継続可能（通常の2048と同じ）。

---

## MotoHub連携

### 最高レベルバイクへの導線

ゲームオーバー時・クリア時に、到達した最高レベルのバイクの
車種モデルページへのリンクを表示。

```jsx
const BIKE_URLS = {
  1: '/bikes/honda/super-cub-50',
  2: '/bikes/honda/monkey-125',
  3: '/bikes/honda/pcx',
  4: '/bikes/honda/rebel-250',
  5: '/bikes/kawasaki/ninja-400',
  6: '/bikes/honda/gb350',
  7: '/bikes/kawasaki/z900rs',
  8: '/bikes/honda/cb1300sf',
  9: '/bikes/suzuki/hayabusa',
  10: '/bikes/kawasaki/ninja-h2',
  11: '/bikes/honda/goldwing',
};
```

### シェア機能

```jsx
const shareText = gameWon
  ? `バイク2048でゴールドウイングに到達！スコア: ${score}点 🏍️🏆\n#バイク2048 #MotoHub\nhttps://motohub.jp/puzzle`
  : `バイク2048で${getBikeName(maxLevel)}まで到達！スコア: ${score}点 🏍️\n#バイク2048 #MotoHub\nhttps://motohub.jp/puzzle`;
```

X（Twitter）シェアボタン:
```jsx
<a href={`https://twitter.com/intent/tweet?text=${encodeURIComponent(shareText)}`}
   target="_blank">Xでシェア</a>
```

---

## データ保存（localStorage）

```jsx
const STORAGE_KEY = 'motohub_puzzle';

// 保存
function saveGame() {
  localStorage.setItem(STORAGE_KEY, JSON.stringify({
    grid, score, bestScore, maxLevel,
  }));
}

// 復元
function loadGame() {
  const saved = localStorage.getItem(STORAGE_KEY);
  if (saved) {
    const data = JSON.parse(saved);
    setGrid(data.grid);
    setScore(data.score);
    setBestScore(data.bestScore);
    setMaxLevel(data.maxLevel);
  }
}
```

毎ターン自動保存。ページを閉じても続きから再開可能。

---

## Bladeテンプレート

```blade
{{-- resources/views/puzzle/index.blade.php --}}
@extends('layouts.app')

@section('title', 'バイク2048パズル | MotoHub')

@section('meta_description', 'カブ50からスタート！同じバイクを合わせてグレードアップ。ゴールドウイングを目指す2048パズルゲーム。')

@section('content')
<div id="bike-puzzle-root"></div>
@endsection

@push('scripts')
@vite(['resources/js/components/BikePuzzle.jsx'])
@endpush
```

---

## ルーティング

```php
// routes/web.php
Route::get('/puzzle', function () {
    return view('puzzle.index');
})->name('puzzle');
```

---

## ナビゲーション

ヘッダーの「その他」メニューに「パズル」を追加。
わらしべ・クイズと並べて表示。

---

## 進化チャート（画面下部）

到達済みのバイクはカラーで表示、未到達はグレーアウト。
横スクロールで11段階すべて見られる。

```jsx
<div className="flex gap-2 overflow-x-auto py-2">
  {BIKES.map((bike, i) => (
    <div key={i} className={`flex-shrink-0 w-14 h-14 rounded-lg flex flex-col items-center justify-center text-xs
      ${i + 1 <= maxLevel ? 'opacity-100' : 'opacity-30 grayscale'}`}
      style={{ backgroundColor: bike.color }}>
      <img src={`/images/puzzle/level-${i + 1}.png`} className="w-8 h-8" />
      <span className="text-[8px] font-bold">{bike.name}</span>
    </div>
  ))}
</div>
```

---

## 1手戻す機能

historyスタックに直前のgrid/scoreを保存。
「↩ 1手戻す」ボタンで1回だけ戻せる（連続使用不可、1ターンに1回）。

```jsx
function undo() {
  if (history.length === 0) return;
  const prev = history[history.length - 1];
  setGrid(prev.grid);
  setScore(prev.score);
  setHistory(history.slice(0, -1));
}
```

---

## テスト

1. 基本操作: 上下左右の移動が正しく動作するか
2. 合体: 同レベルのタイルが合体してレベルアップするか
3. 連鎖: 1回の移動で複数の合体が発生する場合の処理
4. ゲームオーバー: 全マス埋まって移動不可の判定
5. クリア: レベル11到達の判定
6. スコア: 合体時のスコア加算が正しいか
7. localStorage: 保存と復元が動作するか
8. スワイプ: モバイルでのタッチ操作
9. 1手戻す: 正しく1手前に戻るか

---

## 実装順序

### Phase 1: 基本ゲーム（テキストのみ）
1. Bladeテンプレート + ルーティング
2. BikePuzzle.jsx の基本構造
3. 4×4グリッドの描画（バイク名テキスト + 背景色）
4. 移動ロジック（キーボード）
5. 合体ロジック + スコア計算
6. ゲームオーバー / クリア判定
7. リセットボタン

### Phase 2: モバイル対応 + UX
8. タッチスワイプ操作
9. CSSアニメーション（移動・合体・出現）
10. 1手戻す機能
11. localStorage保存/復元
12. 進化チャート
13. レスポンシブ調整

### Phase 3: 仕上げ
14. 画像の配置（Gemini生成後）
15. 効果音の配置
16. シェア機能
17. MotoHub連携（車種ページリンク）
18. OGP画像
19. ナビゲーション追加

---

## デプロイ手順

```bash
# ローカル
docker compose exec app npm run build
git add -A
git commit -m "feat: バイク2048パズルゲーム"
git push

# VPS
cd /var/www/motohub
sudo chmod -R 777 backend/public/build/
git fetch origin && git reset --hard origin/main
cd backend
php artisan view:clear
php artisan config:cache
php artisan route:cache
```

Cloudflare Purge Everything。

---

## 注意事項

- わらしべ・クイズと同じビルドパイプラインを使う
- `@vite` でReactコンポーネントを読み込む
- 画像・音声は後から配置でOK（フォールバック実装必須）
- モバイルファーストで実装（PCは矢印キー追加のみ）
- localStorage のキーは 'motohub_puzzle' で他ゲームと衝突しない
