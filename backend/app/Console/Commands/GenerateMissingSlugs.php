<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BikeModel;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Transliterator;

/**
 * slug が null の bike_models を埋める／過去にローマ字変換で生成した
 * 低品質スラッグを英語名ベースで作り直すコマンド。
 *
 * slug:generate は日本語のみ（ASCII部分が数字だけ等）の車種をスキップするため、
 * かつてここで intl Transliterator を使ってカナ・漢字をローマ字へ変換し
 * 残った null slug を一掃した。ただし「レブル250 → reburu-250」のように
 * 読みのローマ字になり SEO 的に弱いスラッグが多数生成された。
 *
 * そこで KANA_WORD_MAP（カタカナ→英語）辞書を用意し、
 * 車種名に含まれるカタカナ語を英語へ置換してから slug 化する。
 *   例: レブル250 → rebel-250 / リード・ex → lead-ex / セロー250 → serow-250
 *
 * 使い方:
 *   php artisan slug:generate-missing                  ← slug=null のものを生成
 *   php artisan slug:generate-missing --dry-run        ← 実行せず確認だけ
 *   php artisan slug:generate-missing --regenerate     ← 過去のローマ字slugを英語名へ作り直す
 *   php artisan slug:generate-missing --regenerate --dry-run
 */
final class GenerateMissingSlugs extends Command
{
    protected $signature = 'slug:generate-missing
                            {--dry-run : 実行せず確認だけ}
                            {--regenerate : 過去にローマ字変換で生成した低品質slugを英語名で作り直す}';

    protected $description = 'slugがnullの車種を生成／ローマ字slugを英語名ベースで再生成（slug:generate の補完）';

    /**
     * カタカナ語 → 英語表記の辞書。
     * 読みのローマ字（reburu 等）ではなく、メーカー公式の英語表記を優先する。
     * 実行時に「長いキー優先」で適用するため定義順は問わない。
     */
    private const KANA_WORD_MAP = [
        // ━━━ 数字と紛らわしい複合語は長いものから（uksortで長さ順に並べ替えるので順不同でOK） ━━━

        // ── ホンダ ──
        'レブル' => 'rebel',
        'タクト' => 'tact',
        'ベーシック' => 'basic',
        'スタンダード' => 'standard',
        'フォルツァ' => 'forza',
        'ジャイロ' => 'gyro',
        'キャノピー' => 'canopy',
        'トゥデイ' => 'today',
        'リード' => 'lead',
        'ジョルノ' => 'giorno',
        'スポルト' => 'sport',
        'ダックス' => 'dax',
        'シャドウ' => 'shadow',
        'ファントム' => 'phantom',
        'モトラ' => 'motra',
        'ジャズ' => 'jazz',
        'ジェイド' => 'jade',
        'バイト' => 'bite',
        'ゼルビス' => 'xelvis',
        'スタイロ' => 'stylo',
        'ソロ' => 'solo',
        'ブレイド' => 'blade',
        'モード' => 'mode',
        'ベンリィ' => 'benly',
        'プロ' => 'pro',

        // ── ヤマハ ──
        'セロー' => 'serow',
        'トリシティ' => 'tricity',
        'シグナス' => 'cygnus',
        'グリファス' => 'gryphus',
        'ビーノ' => 'vino',
        'ニュース' => 'news',
        'ギア' => 'gear',
        'アクシス' => 'axis',
        'トレイサー' => 'tracer',
        'ブロンコ' => 'bronco',
        'マジェスティ' => 'majesty',
        'グランド' => 'grand',
        'フィラーノ' => 'filano',
        'ハイブリッド' => 'hybrid',
        'ボルト' => 'bolt',
        'ジョグ' => 'jog',
        'ビラーゴ' => 'virago',
        'ドラッグスター' => 'dragstar',
        'フォース' => 'force',

        // ── カワサキ ──
        'エリミネーター' => 'eliminator',
        'メグロ' => 'meguro',
        'バリオス' => 'balius',
        'ニンジャ' => 'ninja',
        'バルカン' => 'vulcan',
        'ツアラー' => 'tourer',
        'ミーンストリーク' => 'mean-streak',
        'ボイジャー' => 'voyager',

        // ── スズキ ──
        'レッツ' => 'lets',
        'カタナ' => 'katana',
        'スウィッシュ' => 'swish',
        'グラストラッカー' => 'grass-tracker',
        'ビッグボーイ' => 'big-boy',
        'イナズマ' => 'inazuma',
        'チョイノリ' => 'choinori',
        'セピア' => 'sepia',
        'バーディー' => 'birdie',
        'カーナ' => 'karna',
        'ラブ' => 'love',
        'モレ' => 'mole',
        'エポ' => 'epo',
        'マメタン' => 'mametan',
        'ギャグ' => 'gag',
        'アクセス' => 'access',
        'クロスオーバー' => 'crossover',

        // ── KTM ──
        'デューク' => 'duke',

        // ── ロイヤルエンフィールド ──
        'スヴァルトピレン' => 'svartpilen',
        'ヴィットピレン' => 'vitpilen',
        'ハンター' => 'hunter',
        'メテオ' => 'meteor',
        'ファイヤーボール' => 'fireball',
        'スーパーノヴァ' => 'supernova',
        'オーロラ' => 'aurora',
        'ステラ' => 'stella',
        'ゴアン' => 'goan',
        'ブリット' => 'bullet',
        'ショットガン' => 'shotgun',
        'スクラム' => 'scram',
        'コンチネンタル' => 'continental',
        'スーパーモト' => 'supermoto',
        'エンデューロ' => 'enduro',
        'ノーデン' => 'norden',

        // ── トライアンフ ──
        'トライデント' => 'trident',
        'スピードツイン' => 'speed-twin',
        'ストリートツイン' => 'street-twin',
        'ストリートカップ' => 'street-cup',
        'ストリートスクランブラー' => 'street-scrambler',
        'スピードトリプル' => 'speed-triple',
        'ストリートトリプル' => 'street-triple',
        'スピードマスター' => 'speedmaster',
        'デザートスレッド' => 'desert-sled',
        'ストリートファイター' => 'streetfighter',
        'ファイター' => 'fighter',
        'ハッシュタグ' => 'hashtag',
        'スクランブラー' => 'scrambler',
        'アイコン' => 'icon',
        'ラリー' => 'rally',
        'スリー' => 'three',
        'ボンネビル' => 'bonneville',
        'タイガー' => 'tiger',
        'ロケット' => 'rocket',
        'ストーム' => 'storm',
        'スプリント' => 'sprint',
        'スラクストン' => 'thruxton',
        'デイトナ' => 'daytona',
        'トリプル' => 'triple',
        'カップ' => 'cup',

        // ── ドゥカティ ──
        'パニガーレ' => 'panigale',
        'モンスター' => 'monster',
        'ディアベル' => 'diavel',
        'ハイパーモタード' => 'hypermotard',
        'ムルティストラーダ' => 'multistrada',
        'パンアメリカ' => 'pan-america',
        'スーパーレッジェーラ' => 'superleggera',
        'スーパースポーツ' => 'supersport',
        'パイクスピーク' => 'pikes-peak',
        'バイクスピーク' => 'pikes-peak',
        'フルスロットル' => 'full-throttle',
        'カフェレーサー' => 'cafe-racer',
        'ディーゼル' => 'diesel',
        'ステルビオ' => 'stelvio',
        'ベリッシマ' => 'bellissima',
        'チタニウム' => 'titanium',
        'カーボン' => 'carbon',

        // ── インディアン ──
        'スカウト' => 'scout',
        'チーフテン' => 'chieftain',
        'チーフ' => 'chief',
        'ビンテージ' => 'vintage',
        'ダークホース' => 'dark-horse',
        'スプリングフィールド' => 'springfield',
        'ロードマスター' => 'roadmaster',
        'チャレンジャー' => 'challenger',
        'トゥエンティ' => 'twenty',
        'シックスティ' => 'sixty',
        'ボバー' => 'bobber',
        'ボンバー' => 'bomber',
        'ヘイスト' => 'heist',

        // ── ハーレー ──
        'ライトニング' => 'lightning',
        'サイクロン' => 'cyclone',

        // ── SYM / ピアッジオ / アプリリア / その他輸入 ──
        'プリマベーラ' => 'primavera',
        'ジャンゴ' => 'django',
        'アローマ' => 'aroma',
        'トゥオーノ' => 'tuono',
        'トゥアレグ' => 'tuareg',
        'パガーニ' => 'pagani',
        'ピエガ' => 'piega',
        'アキタ' => 'akita',
        'クロスファイア' => 'crossfire',
        'ジェットパワー' => 'jet-power',
        'トップボーイ' => 'top-boy',
        'レイバーン' => 'rayburn',
        'フリーライド' => 'freeride',
        'ブルロック' => 'bullock',
        'モトグッチ' => 'moto-guzzi',
        'ブレバ' => 'breva',
        'スコルパ' => 'scorpa',
        'ビモータ' => 'bimota',
        'クロムウェル' => 'cromwell',
        'フェルスベルク' => 'felsberg',
        'ヌーダ' => 'nuda',
        'シティースター' => 'city-star',
        'サンレイ' => 'sunray',
        'モデナ' => 'modena',
        'アドベンチュラー' => 'adventurer',
        'エクスプローラー' => 'explorer',
        'ネクス' => 'nex',
        'ステルス' => 'stealth',

        // ── 共通グレード／装飾語 ──
        'スーパーカブ' => 'super-cub',
        'スーパーデューク' => 'super-duke',
        'スーパー' => 'super',
        'アドベンチャー' => 'adventure',
        'ストリート' => 'street',
        'クラシック' => 'classic',
        'スペシャル' => 'special',
        'リミテッド' => 'limited',
        'エディション' => 'edition',
        'マジック' => 'magic',
        'アーバン' => 'urban',
        'モタード' => 'motard',
        'スポーツ' => 'sport',
        'プラス' => 'plus',
        'ダーク' => 'dark',
        'ツイン' => 'twin',
        'モト' => 'moto',

        // ── メーカー名（「○○ その他」対策） ──
        'ホンダ' => 'honda',
        'ヤマハ' => 'yamaha',
        'カワサキ' => 'kawasaki',
        'スズキ' => 'suzuki',
        'ドゥカティ' => 'ducati',
        'ピアッジオ' => 'piaggio',
        'アプリリア' => 'aprilia',
        'ベネリ' => 'benelli',
        'キムコ' => 'kymco',
        'その他' => 'other',
        '他車種' => 'other',
        '国産' => 'domestic',

        // ── 漢字（ローマ字化すると中国語ピンイン化する語を明示変換） ──
        '新聞' => 'news',
        '重荷用' => 'cargo',
        '仕様' => '',
    ];

    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $isRegen = (bool) $this->option('regenerate');

        if ($isDryRun) {
            $this->info('🔍 ドライランモード（変更は保存されません）');
        }

        $transliterator = Transliterator::create('Any-Latin; Latin-ASCII; Lower()');
        if ($transliterator === null) {
            $this->error('Transliterator を初期化できませんでした（intl拡張を確認してください）');
            return Command::FAILURE;
        }

        return $isRegen
            ? $this->regenerate($transliterator, $isDryRun)
            : $this->fillNullSlugs($transliterator, $isDryRun);
    }

    /**
     * slug=null の車種を辞書ベースで生成する（従来の補完処理）。
     */
    private function fillNullSlugs(Transliterator $transliterator, bool $isDryRun): int
    {
        $models = BikeModel::with('manufacturer')->whereNull('slug')->get();
        $this->info("対象: {$models->count()}件のslug=null車種");

        $updated = 0;
        $fallback = 0;

        foreach ($models as $model) {
            $slug = $this->improvedSlug($transliterator, $model->name);
            if ($slug === null) {
                $slug = 'model-' . $model->id;
                $fallback++;
            }
            $slug = $this->ensureUniqueSlug($slug, $model->manufacturer_id, $model->id);

            if ($isDryRun) {
                $mfrName = $model->manufacturer?->name ?? '??';
                $this->line("  📝 [{$mfrName}] {$model->name} (ID:{$model->id}) → <fg=green>{$slug}</>");
            } else {
                $model->update(['slug' => $slug]);
            }
            $updated++;
        }

        $this->newLine();
        $verb = $isDryRun ? '結果(予定)' : '✅ 完了';
        $this->info("{$verb}: {$updated}件（うちフォールバック {$fallback}件）");

        return Command::SUCCESS;
    }

    /**
     * 過去にローマ字変換で生成した低品質slugだけを対象に、英語名ベースで作り直す。
     *
     * 「自分が生成したものか」は次の2条件で厳密に判定し、
     * 手動マッピング（vino, super-cub-50 等）や英語名スラッグには触れない:
     *   1. 車種名のASCII部分だけでは slug:generate が null を返す（＝日本語専用名）
     *   2. 現在の slug が旧アルゴリズムのローマ字出力と一致する（-2等の重複サフィックス込み）
     *
     * active掲載数の多い順に処理し、人気車種にクリーンなslugを優先的に割り当てる。
     */
    private function regenerate(Transliterator $transliterator, bool $isDryRun): int
    {
        $models = BikeModel::with('manufacturer')
            ->whereNotNull('slug')
            ->withCount(['listings as active_count' => fn ($q) => $q->active()])
            ->orderByDesc('active_count')
            ->get();

        $targets = 0;
        $changed = 0;
        $unchanged = 0;
        $promoted = 0;

        foreach ($models as $model) {
            // 条件1: 日本語専用名（ASCIIだけでは slug 化できない）でなければ対象外
            if (!$this->asciiSlugIsNull($model->name)) {
                continue;
            }

            // 条件2: 現在のslugが旧ローマ字アルゴリズムの出力と一致するか
            $legacy = $this->legacyRomajiSlug($transliterator, $model->name);
            if ($legacy === null) {
                continue;
            }
            $base = preg_replace('/-\d+$/', '', $model->slug);
            if ($model->slug !== $legacy && $base !== $legacy) {
                continue; // 手動マッピング等 → 触れない
            }

            $targets++;

            $oldSlug = $model->slug;
            $desired = $this->improvedSlug($transliterator, $model->name) ?? ('model-' . $model->id);

            // クリーンなslugが同一メーカーの別車種に取られている場合、
            // active掲載数が多い方（＝主要な重複行）にクリーンslugを与え、
            // 少ない方を「-N」へ退避させる（重複モデルのSEO最適化）。
            // 永続化（重複行の降格＋スワップ含む）はヘルパ内で完結する。
            $newSlug = $this->resolveWithPromotion($model, $desired, $isDryRun, $promoted);

            if ($newSlug === $oldSlug) {
                $unchanged++;
                continue;
            }

            $mfrName = $model->manufacturer?->name ?? '??';
            $this->line(sprintf(
                '  %s [%s] %s (ID:%d, %d台)  %s → <fg=green>%s</>',
                $isDryRun ? '📝' : '✓',
                $mfrName,
                $model->name,
                $model->id,
                $model->active_count,
                $oldSlug,
                $newSlug,
            ));
            $changed++;
        }

        $this->newLine();
        $verb = $isDryRun ? '結果(予定)' : '✅ 完了';
        $this->info("{$verb}: 対象 {$targets}件中 {$changed}件を改善、{$unchanged}件は変更なし、{$promoted}件で重複行を降格");

        return Command::SUCCESS;
    }

    /**
     * 希望slugが同一メーカーの別車種に取られている場合の割り当てを解決する。
     *
     * - 空いていればそのまま使う。
     * - 取られていて、こちらの方が active掲載数が多い → 相手を「-N」へ降格し、こちらがクリーンslugを得る。
     * - 取られていて、相手の方が多い → こちらが「-N」を得る（相手は不変）。
     *
     * (manufacturer_id, slug) のユニーク制約があるため、永続化はこのメソッド内で
     * スワップ衝突を避ける順序（必要なら一時slugを経由）で行い、最終slugを返す。
     */
    private function resolveWithPromotion(BikeModel $model, string $desired, bool $isDryRun, int &$promoted): string
    {
        $holder = BikeModel::where('slug', $desired)
            ->where('manufacturer_id', $model->manufacturer_id)
            ->where('id', '!=', $model->id)
            ->withCount(['listings as active_count' => fn ($q) => $q->active()])
            ->first();

        // 空いている → そのまま採用
        if ($holder === null) {
            if (!$isDryRun) {
                $model->update(['slug' => $desired]);
            }
            return $desired;
        }

        // 相手の方が人気 → こちらがサフィックスを得る（相手は不変）
        if (($model->active_count ?? 0) <= ($holder->active_count ?? 0)) {
            $suffixed = $this->ensureUniqueSlug($desired, $model->manufacturer_id, $model->id);
            if (!$isDryRun) {
                $model->update(['slug' => $suffixed]);
            }
            return $suffixed;
        }

        // 主要行を昇格：保持者を「-N」へ退避させ、こちらがクリーンslugを得る
        $demoted = $this->firstFreeSuffixedSlug($desired, $model->manufacturer_id, [$holder->id, $model->id]);

        $this->line(sprintf(
            '    ↳ 重複降格: [%s] %s (ID:%d, %d台)  %s → <fg=yellow>%s</>',
            $model->manufacturer?->name ?? '??',
            $holder->name,
            $holder->id,
            $holder->active_count,
            $holder->slug,
            $demoted,
        ));
        $promoted++;

        if (!$isDryRun) {
            // スワップ衝突回避：こちらの現slugが降格先と同じ場合は一時slugを経由する
            if ($model->slug === $demoted) {
                $temp = 'tmp-swap-' . $model->id;
                $holder->update(['slug' => $temp]);   // desired を解放
                $model->update(['slug' => $desired]); // こちらが desired を取得（同時に demoted を解放）
                $holder->update(['slug' => $demoted]); // 相手を最終 demoted へ
            } else {
                $holder->update(['slug' => $demoted]);
                $model->update(['slug' => $desired]);
            }
        }

        return $desired;
    }

    /**
     * base-2, base-3 ... のうち、指定IDを除いて未使用の最初のものを返す。
     *
     * @param array<int> $excludeIds
     */
    private function firstFreeSuffixedSlug(string $base, ?int $manufacturerId, array $excludeIds): string
    {
        $counter = 2;
        while (true) {
            $candidate = $base . '-' . $counter;
            $exists = BikeModel::where('slug', $candidate)
                ->where('manufacturer_id', $manufacturerId)
                ->whereNotIn('id', $excludeIds)
                ->exists();
            if (!$exists) {
                return $candidate;
            }
            $counter++;
        }
    }

    /**
     * 車種名から英語名優先のスラッグを生成する。生成できなければ null。
     */
    private function improvedSlug(Transliterator $transliterator, string $name): ?string
    {
        // 全角英数 → 半角、ASCIIを小文字化（辞書キー＝カタカナはそのまま）
        $work = mb_strtolower(mb_convert_kana($name, 'a', 'UTF-8'), 'UTF-8');

        // 辞書置換（長いキーから先に適用して「スーパーモト」等の分割を防ぐ）
        foreach ($this->sortedDictionary() as $kana => $eng) {
            if (mb_strpos($work, $kana) !== false) {
                $replacement = $eng === '' ? ' ' : ' ' . $eng . ' ';
                $work = str_replace($kana, $replacement, $work);
            }
        }

        // 残ったカナ・漢字 → ローマ字
        $romaji = $transliterator->transliterate($work);
        if ($romaji === false) {
            $romaji = $work;
        }

        // 英字(2文字以上)と数字の境界にハイフン（s1, z1, x4 は分割しない）
        $romaji = preg_replace('/(?<=[a-z]{2})(?=[0-9])/', '-', $romaji);
        $romaji = preg_replace('/(?<=[0-9])(?=[a-z]{2})/', '-', $romaji);

        $slug = Str::slug($romaji);

        return $slug === '' ? null : $slug;
    }

    /**
     * 旧アルゴリズム（辞書なし・単純ローマ字化）のスラッグを再現する。
     * 過去に生成したslugかどうかの判定に使う。
     */
    private function legacyRomajiSlug(Transliterator $transliterator, string $name): ?string
    {
        $name = mb_convert_kana($name, 'a', 'UTF-8');

        $romaji = $transliterator->transliterate($name);
        if ($romaji === false) {
            return null;
        }

        $romaji = preg_replace('/(?<=[a-z])(?=[0-9])/i', '-', $romaji);
        $romaji = preg_replace('/(?<=[0-9])(?=[a-z])/i', '-', $romaji);

        $slug = Str::slug($romaji);

        return $slug === '' ? null : $slug;
    }

    /**
     * slug:generate::generateSlug と同じ条件で「日本語専用名（ASCIIだけでは生成不可）」かを判定する。
     */
    private function asciiSlugIsNull(string $name): bool
    {
        $name = mb_convert_kana($name, 'a', 'UTF-8');

        $ascii = trim(preg_replace('/[^\x20-\x7E]/', '', $name));
        if (mb_strlen($ascii) < 2) {
            return true;
        }

        $slug = Str::slug($ascii);
        if ($slug === '') {
            return true;
        }
        if (preg_match('/^\d+(-\d+)*$/', $slug)) {
            return true;
        }

        return mb_strlen($slug) <= 2;
    }

    /**
     * 辞書をキー長の降順で返す（長い複合語を優先マッチさせるため）。
     *
     * @return array<string, string>
     */
    private function sortedDictionary(): array
    {
        static $sorted = null;
        if ($sorted !== null) {
            return $sorted;
        }

        $sorted = self::KANA_WORD_MAP;
        uksort($sorted, static fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));

        return $sorted;
    }

    /**
     * 同一メーカー内でのスラッグ重複を回避する。
     */
    private function ensureUniqueSlug(string $slug, ?int $manufacturerId, int $excludeId): string
    {
        $original = $slug;
        $counter = 2;

        while (
            BikeModel::where('slug', $slug)
                ->where('manufacturer_id', $manufacturerId)
                ->where('id', '!=', $excludeId)
                ->exists()
        ) {
            $slug = $original . '-' . $counter;
            $counter++;
        }

        return $slug;
    }
}
