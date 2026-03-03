<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use App\Models\Manufacturer;
use App\Models\BikeModel;

/**
 * 既存の manufacturers / bike_models テーブルにスラッグを自動生成する
 * 
 * 使い方:
 *   php artisan slug:generate           ← 全件生成（slugがnullのもののみ）
 *   php artisan slug:generate --force   ← 既存スラッグも上書き
 *   php artisan slug:generate --dry-run ← 実行せず確認だけ
 */
class GenerateSlugs extends Command
{
    protected $signature = 'slug:generate 
                            {--force : 既存スラッグも上書きする}
                            {--dry-run : 実行せず確認だけ}';

    protected $description = 'manufacturers と bike_models のスラッグを自動生成';

    // ━━━ メーカーのスラッグマッピング ━━━
    private const MANUFACTURER_SLUGS = [
        'ホンダ'       => 'honda',
        'Honda'        => 'honda',
        'HONDA'        => 'honda',
        'ヤマハ'       => 'yamaha',
        'Yamaha'       => 'yamaha',
        'YAMAHA'       => 'yamaha',
        'カワサキ'     => 'kawasaki',
        'Kawasaki'     => 'kawasaki',
        'KAWASAKI'     => 'kawasaki',
        'スズキ'       => 'suzuki',
        'Suzuki'       => 'suzuki',
        'SUZUKI'       => 'suzuki',
        'ハーレーダビッドソン' => 'harley-davidson',
        'Harley-Davidson'     => 'harley-davidson',
        'ドゥカティ'   => 'ducati',
        'Ducati'       => 'ducati',
        'BMW'          => 'bmw',
        'トライアンフ' => 'triumph',
        'Triumph'      => 'triumph',
        'KTM'          => 'ktm',
        'アプリリア'   => 'aprilia',
        'Aprilia'      => 'aprilia',
        'ロイヤルエンフィールド' => 'royal-enfield',
        'Royal Enfield'        => 'royal-enfield',
        'インディアン' => 'indian',
        'Indian'       => 'indian',
        'モトグッツィ' => 'moto-guzzi',
        'Moto Guzzi'   => 'moto-guzzi',
        'MVアグスタ'   => 'mv-agusta',
        'MV Agusta'    => 'mv-agusta',
        'ベネリ'       => 'benelli',
        'Benelli'      => 'benelli',
        'SYM'          => 'sym',
        'キムコ'       => 'kymco',
        'KYMCO'        => 'kymco',
        'ピアジオ'     => 'piaggio',
        'Piaggio'      => 'piaggio',
        'ベスパ'       => 'vespa',
        'Vespa'        => 'vespa',
        'プジョー'     => 'peugeot',
        'Peugeot'      => 'peugeot',
        'ヒョースン'   => 'hyosung',
        'Hyosung'      => 'hyosung',
        'ハスクバーナ'           => 'husqvarna',
        'Husqvarna'              => 'husqvarna',
        'GasGas'       => 'gasgas',
        'GASGAS'       => 'gasgas',
    ];

    // ━━━ 日本語車種名 → スラッグの手動マッピング ━━━
    // 日本語名だけだとASCII部分が数字のみになってしまう車種を救済
    private const BIKE_MODEL_SLUGS = [
        // ━━━ ホンダ ━━━
        'スーパーカブ50'         => 'super-cub-50',
        'スーパーカブ50プロ'     => 'super-cub-50-pro',
        'スーパーカブ110'        => 'super-cub-110',
        'スーパーカブ110プロ'    => 'super-cub-110-pro',
        'スーパーカブc125'       => 'super-cub-c125',
        'スーパーカブ70'         => 'super-cub-70',
        'スーパーカブ90'         => 'super-cub-90',
        'クロスカブ50'           => 'cross-cub-50',
        'クロスカブ110'          => 'cross-cub-110',
        'モンキー125'            => 'monkey-125',
        'ダックス125'            => 'dax-125',
        'ダックス50'             => 'dax-50',
        'ダックス70'             => 'dax-70',
        'レブル 250'             => 'rebel-250',
        'レブル 500'             => 'rebel-500',
        'レブル 1100'            => 'rebel-1100',
        'レブル(-1999)'          => 'rebel-classic',
        'ホーネット250'          => 'hornet-250',
        'ホーネット600'          => 'hornet-600',
        'ホーネット900'          => 'hornet-900',
        'マグナ(vツインマグナ)'  => 'magna-v-twin',
        'マグナ250'              => 'magna-250',
        'マグナ50'               => 'magna-50',
        'フォルツァ si'          => 'forza-si',
        'フォルツァ(mf06)'       => 'forza-mf06',
        'フォルツァ(mf08)'       => 'forza-mf08',
        'フォルツァ(mf10)'       => 'forza-mf10',
        'フォルツァ(mf13)'       => 'forza-mf13',
        'フォルツァ(mf15)'       => 'forza-mf15',
        'フォルツァ 250'         => 'forza-250',
        'フォルツァ 350'         => 'forza-350',
        'フュージョン'           => 'fusion',
        'ジョルノ'               => 'giorno',
        'トゥデイ'               => 'today',
        'ディオ110'              => 'dio-110',
        'リード125'              => 'lead-125',
        'pcx125'                 => 'pcx-125',
        'pcx160'                 => 'pcx-160',
        'adv150'                 => 'adv-150',
        'adv160'                 => 'adv-160',
        'ゴリラ'                 => 'gorilla',
        'エイプ50'               => 'ape-50',
        'エイプ100'              => 'ape-100',
        'グロム'                 => 'grom',
        'cb400スーパーフォア'    => 'cb400sf',
        'cb400スーパーボルドール' => 'cb400sb',
        'cb1300スーパーフォア'   => 'cb1300sf',
        'cb1300スーパーボルドール' => 'cb1300sb',
        'cb1300スーパーツーリング' => 'cb1300st',
        'cb1000スーパーフォア(ビッグワン)' => 'cb1000sf',
        'cb400four (空冷)'       => 'cb400four-air',
        'cb400four (水冷)'       => 'cb400four-water',
        'cb400four (空冷/408cc)' => 'cb400four-408',
        'cb750フォア(cb750k)'    => 'cb750four',
        'cb500フォア'            => 'cb500four',
        'cb550フォア'            => 'cb550four',
        'ホーク cb250t'          => 'hawk-cb250t',
        'ホークii cb400t'        => 'hawk2-cb400t',
        'ホークiii cb400n'       => 'hawk3-cb400n',
        'ゴールドウイング f6b'   => 'goldwing-f6b',
        'ゴールドウイング'       => 'goldwing',
        'シャドウ400'            => 'shadow-400',
        'シャドウ750'            => 'shadow-750',
        'シャドウクラシック400'   => 'shadow-classic-400',
        'シャドウスラッシャー400' => 'shadow-slasher-400',
        'シャドウ ファントム750'  => 'shadow-phantom-750',
        'スティード400'          => 'steed-400',
        'スティード600'          => 'steed-600',
        'ブロス400'              => 'bros-400',
        'ブロス650'              => 'bros-650',
        'シルバーウイングgt400'   => 'silverwing-gt400',
        'シルバーウイング600'     => 'silverwing-600',
        'インテグラ700'          => 'integra-700',
        'シルクロード(ct250s)'    => 'silkroad-ct250s',
        'フォーサイトse'         => 'foresight-se',
        'タラニス110'            => 'taranis-110',
        'ベンリィ e1'            => 'benly-e1',
        'ベンリィ e1 プロ'       => 'benly-e1-pro',
        'ベンリィ e2'            => 'benly-e2',
        'ベンリィ e2 プロ'       => 'benly-e2-pro',
        'クレアスクーピーdx'     => 'crea-scoopy-dx',
        'ナイトホーク250'        => 'nighthawk-250',
        'cb450セニア'            => 'cb450-senior',

        // ━━━ ヤマハ ━━━
        'セロー 250'             => 'serow-250',
        'セロー225'              => 'serow-225',
        'ドラッグスター 250'     => 'dragstar-250',
        'ドラッグスター 400'     => 'dragstar-400',
        'ドラッグスター1100'     => 'dragstar-1100',
        'ビラーゴ250(xv250)'     => 'virago-250',
        'ビラーゴ400'            => 'virago-400',
        'ビラーゴ750'            => 'virago-750',
        'マジェスティ250(sg03j)' => 'majesty-250-sg03j',
        'マジェスティ250(sg20j)' => 'majesty-250-sg20j',
        'マジェスティ400'        => 'majesty-400',
        'マジェスティs'          => 'majesty-s',
        'グランドマジェスティ 250' => 'grand-majesty-250',
        'グランドマジェスティ 400' => 'grand-majesty-400',
        'ジョグ (2サイクル)'     => 'jog-2st',
        'ジョグzr'               => 'jog-zr',
        'ビーノ(2サイクル)'      => 'vino-2st',
        'ビーノ(4サイクル)'      => 'vino-4st',
        'ビーノ'                 => 'vino',
        'シグナスgt'             => 'cygnus-gt',
        'シグナスx fi'           => 'cygnus-x-fi',
        'シグナスray zr'         => 'cygnus-ray-zr',
        'シグナスray zr streetrally' => 'cygnus-ray-zr-streetrally',
        'シグナス180'            => 'cygnus-180',
        'トリシティ 125'         => 'tricity-125',
        'トリシティ 155'         => 'tricity-155',
        'トリシティ 300'         => 'tricity-300',
        'トレーサー900'          => 'tracer-900',
        'トレーサー9 gt'         => 'tracer-9-gt',
        'トレーサー9 gt+ y-amt'  => 'tracer-9-gt-plus',
        'ファッシーノ 115'       => 'fascino-115',
        'ファッシーノ 125'       => 'fascino-125',
        'ランツァ (dt230)'       => 'lanza-dt230',
        'トレイシー150 cz150r'   => 'treicy-150',
        'e ビーノ (電動バイク)'  => 'e-vino',

        // ━━━ カワサキ ━━━
        'ニンジャ 150rr'         => 'ninja-150rr',
        'ニンジャ 250'           => 'ninja-250',
        'ニンジャ 250 abs'       => 'ninja-250-abs',
        'ニンジャ 250r'          => 'ninja-250r',
        'ニンジャ 250sl'         => 'ninja-250sl',
        'ニンジャ 400'           => 'ninja-400',
        'ニンジャ 400r'          => 'ninja-400r',
        'ニンジャ 650'           => 'ninja-650',
        'ニンジャ 650r'          => 'ninja-650r',
        'ニンジャ 1000 (z1000sx)' => 'ninja-1000',
        'ニンジャ 1000sx'        => 'ninja-1000sx',
        'ニンジャ 1100sx'        => 'ninja-1100sx',
        'ニンジャ h2'            => 'ninja-h2',
        'ニンジャ h2 carbon'     => 'ninja-h2-carbon',
        'ニンジャ h2 sx'         => 'ninja-h2-sx',
        'ニンジャ h2 sx se'      => 'ninja-h2-sx-se',
        'ニンジャ e-1'           => 'ninja-e1',
        'ゼファー400'            => 'zephyr-400',
        'ゼファー550'            => 'zephyr-550',
        'ゼファー750'            => 'zephyr-750',
        'ゼファー750rs'          => 'zephyr-750rs',
        'ゼファー1100'           => 'zephyr-1100',
        'ゼファー1100rs'         => 'zephyr-1100rs',
        'エリミネーター400(-1999)' => 'eliminator-400-classic',
        'エリミネーター400(2023-)' => 'eliminator-400',
        'エリミネーター250'      => 'eliminator-250',
        'バルカン400'            => 'vulcan-400',
        'バルカンクラシック400'   => 'vulcan-classic-400',
        'バルカンドリフター400'   => 'vulcan-drifter-400',
        'バルカン900'            => 'vulcan-900',
        'バルカン1500 クラシックツアラー' => 'vulcan-1500-classic-tourer',
        'バルカン1500 ミーンストリーク'   => 'vulcan-1500-mean-streak',
        'バルカン1700 ボイジャーカスタム' => 'vulcan-1700-voyager',
        'バルカン2000'           => 'vulcan-2000',
        'メグロsg'               => 'meguro-sg',
        'ksrプロ'                => 'ksr-pro',

        // ━━━ スズキ ━━━
        'アドレス50'             => 'address-50',
        'アドレスv50 (4サイクル)' => 'address-v50',
        'アドレスv50(2サイクル)' => 'address-v50-2st',
        'アドレスv100'           => 'address-v100',
        'アドレスv125'           => 'address-v125',
        'アドレスz125'           => 'address-z125',
        'アドレス110'            => 'address-110',
        'アドレス125'            => 'address-125',
        'レッツ(4サイクル)'      => 'lets',
        'レッツ2 (2サイクル)'    => 'lets2',
        'バーディー 50 (4サイクル)' => 'birdie-50',
        'ジクサー 150'           => 'gixxer-150',
        'ジクサー sf'            => 'gixxer-sf',
        'ジクサー 250'           => 'gixxer-250',
        'ジクサー sf250'         => 'gixxer-sf250',
        'ウルフ200'              => 'wolf-200',
        'ウルフ250'              => 'wolf-250',
        'バンディット250'        => 'bandit-250',
        'バンディット400'        => 'bandit-400',
        'バンディット1200'       => 'bandit-1200',
        'バンディット1200s'      => 'bandit-1200s',
        'バンディット1250f'      => 'bandit-1250f',
        'バンディット1250s'      => 'bandit-1250s',
        'イナズマ400'            => 'inazuma-400',
        'イナズマ1200(gsx1200fs)' => 'inazuma-1200',
        'イントルーダー250'      => 'intruder-250',
        'イントルーダークラシック400' => 'intruder-classic-400',
        'インパルス400'          => 'impulse-400',
        'ストリートマジック 110'  => 'street-magic-110',
        'スカイウェイブ ss'       => 'skywave-ss',
        'スカイウェイブ250 リミテッド' => 'skywave-250-limited',
        'スカイウェイブ250 ウィンターバージョン' => 'skywave-250-winter',
        'スカイウェイブ400リミテッド' => 'skywave-400-limited',
        'グース350'              => 'goose-350',
        'vストロームsx'          => 'v-strom-sx',
        'e-レッツ'               => 'e-lets',

        // ━━━ ハーレー（日本語部分のみのもの） ━━━
        // ハーレーはほとんど英語名なので自動生成で対応可能

        // ━━━ その他 ━━━
        'トライク(〜50cc)'       => 'trike-50',
        'トライク(51〜125cc)'    => 'trike-125',
        'トライク(126〜250cc)'   => 'trike-250',
        'トライク(1001cc〜)'     => 'trike-1001',
    ];

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        $isForce  = $this->option('force');

        if ($isDryRun) {
            $this->info('🔍 ドライランモード（変更は保存されません）');
        }

        $this->generateManufacturerSlugs($isForce, $isDryRun);
        $this->generateBikeModelSlugs($isForce, $isDryRun);

        if (!$isDryRun) {
            $this->newLine();
            $this->info('✅ スラッグ生成が完了しました！');
        }

        return Command::SUCCESS;
    }

    /**
     * メーカースラッグの生成
     */
    private function generateManufacturerSlugs(bool $force, bool $dryRun): void
    {
        $this->info('');
        $this->info('━━━ メーカー (manufacturers) ━━━');

        $query = Manufacturer::query();
        if (!$force) {
            $query->whereNull('slug');
        }

        $manufacturers = $query->get();
        $updated = 0;
        $skipped = 0;

        foreach ($manufacturers as $mfr) {
            $slug = self::MANUFACTURER_SLUGS[$mfr->name] ?? null;

            if (!$slug) {
                $slug = $this->generateSlug($mfr->name);
            }

            if (!$slug) {
                $this->line("  ⏭ スキップ: {$mfr->name} (ID:{$mfr->id})");
                $skipped++;
                continue;
            }

            $slug = $this->ensureUniqueManufacturerSlug($slug, $mfr->id);

            if ($dryRun) {
                $this->line("  📝 {$mfr->name} → <fg=green>{$slug}</>");
            } else {
                $mfr->update(['slug' => $slug]);
                $this->line("  ✓ {$mfr->name} → <fg=green>{$slug}</>");
            }
            $updated++;
        }

        $this->info("  結果: {$updated}件更新, {$skipped}件スキップ");
    }

    /**
     * 車種スラッグの生成
     */
    private function generateBikeModelSlugs(bool $force, bool $dryRun): void
    {
        $this->info('');
        $this->info('━━━ 車種 (bike_models) ━━━');

        $query = BikeModel::with('manufacturer');
        if (!$force) {
            $query->whereNull('slug');
        }

        $models = $query->get();
        $updated  = 0;
        $skipped  = 0;
        $problems = [];

        foreach ($models as $model) {
            // 1. 手動マッピングを最優先で探す
            $slug = self::BIKE_MODEL_SLUGS[$model->name] ?? null;

            // 2. マッピングになければ自動生成
            if (!$slug) {
                $slug = $this->generateSlug($model->name);
            }

            // 日本語名はスラッグなし（IDフォールバック）
            if (!$slug) {
                $skipped++;
                continue;
            }

            // 同一メーカー内での重複チェック
            $slug = $this->ensureUniqueBikeModelSlug($slug, $model->manufacturer_id, $model->id);

            if ($dryRun) {
                $mfrName = $model->manufacturer?->name ?? '??';
                $this->line("  📝 [{$mfrName}] {$model->name} → <fg=green>{$slug}</>");
            } else {
                $model->update(['slug' => $slug]);
            }
            $updated++;
        }

        if (!$dryRun && $updated > 0) {
            $this->line("  （大量のため個別ログは省略）");
        }

        $this->info("  結果: {$updated}件更新, {$skipped}件スキップ（日本語名）");
    }

    /**
     * 名前からスラッグを生成
     */
    private function generateSlug(string $name): ?string
    {
        // 全角英数を半角に変換
        $name = mb_convert_kana($name, 'a', 'UTF-8');

        // ASCII文字（英数字・ハイフン・スペース・ドット・括弧）を抽出
        $ascii = preg_replace('/[^\x20-\x7E]/', '', $name);
        $ascii = trim($ascii);

        // ASCII部分が短すぎる or 空
        if (mb_strlen($ascii) < 2) {
            return null;
        }

        // スラッグ化
        $slug = Str::slug($ascii);

        if (empty($slug)) {
            return null;
        }

        // ★ 数字だけのスラッグは無意味なので除外
        // 例: "ホーネット250" → "250" は意味がない
        if (preg_match('/^\d+(-\d+)*$/', $slug)) {
            return null;
        }

        // ★ スラッグが短すぎる場合も除外（1-2文字は大抵ゴミ）
        // 例: "マグナ(vツインマグナ)" → "v" は意味がない
        if (mb_strlen($slug) <= 2) {
            return null;
        }

        return $slug;
    }

    /**
     * メーカースラッグの重複回避
     */
    private function ensureUniqueManufacturerSlug(string $slug, int $excludeId): string
    {
        $original = $slug;
        $counter = 2;

        while (Manufacturer::where('slug', $slug)->where('id', '!=', $excludeId)->exists()) {
            $slug = $original . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * 車種スラッグの重複回避（同一メーカー内）
     */
    private function ensureUniqueBikeModelSlug(string $slug, ?int $manufacturerId, int $excludeId): string
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