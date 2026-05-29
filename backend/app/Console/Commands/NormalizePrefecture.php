<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Shop;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * shops.prefecture の表記揺れ（「京都」→「京都府」等）を正式名に統一するコマンド。
 *
 * 背景:
 *   DB には「京都」「京都府」「東京」「東京都」のように 都/道/府/県 サフィックスの
 *   有無が混在している。ショップエリアページ等は full name で完全一致するため、
 *   短縮形のレコードが拾えない不具合の原因になっている。正式名（full name）に統一する。
 *
 * listings テーブルには prefecture カラムが無い（Meilisearch インデックスは
 * shops.prefecture を toSearchableArray でコピーしている）ため、本コマンドで shops を
 * 修正したあとに `php artisan scout:import "App\Models\Listing"` 等で再インデックスする。
 *
 * 使い方:
 *   php artisan prefecture:normalize --dry-run   ← 変更せず影響件数と表記揺れ一覧を表示
 *   php artisan prefecture:normalize             ← 実際に統一を実行（トランザクション）
 */
final class NormalizePrefecture extends Command
{
    protected $signature = 'prefecture:normalize {--dry-run : 変更を行わず、表記揺れ一覧と影響件数のみ表示する}';

    protected $description = 'shops.prefecture の表記揺れ（京都→京都府等）を正式名に統一する';

    /**
     * 47都道府県の正式名（統一先）。
     */
    private const CANONICAL = [
        '北海道', '青森県', '岩手県', '宮城県', '秋田県', '山形県', '福島県',
        '茨城県', '栃木県', '群馬県', '埼玉県', '千葉県', '東京都', '神奈川県',
        '新潟県', '富山県', '石川県', '福井県', '山梨県', '長野県',
        '岐阜県', '静岡県', '愛知県', '三重県',
        '滋賀県', '京都府', '大阪府', '兵庫県', '奈良県', '和歌山県',
        '鳥取県', '島根県', '岡山県', '広島県', '山口県',
        '徳島県', '香川県', '愛媛県', '高知県',
        '福岡県', '佐賀県', '長崎県', '熊本県', '大分県', '宮崎県', '鹿児島県', '沖縄県',
    ];

    public function handle(): int
    {
        if (! Schema::hasColumn('shops', 'prefecture')) {
            $this->error('shops テーブルに prefecture カラムがありません。');

            return Command::FAILURE;
        }

        $isDryRun = (bool) $this->option('dry-run');
        $shortToFull = $this->buildShortToFullMap();

        // --- 1. 現在の表記揺れ全パターンを一覧出力 ---
        $distinct = Shop::query()
            ->select('prefecture', DB::raw('COUNT(*) as cnt'))
            ->groupBy('prefecture')
            ->orderByDesc('cnt')
            ->get();

        $this->info('▼ 現在の shops.prefecture 値（表記揺れ全パターン）');
        $allRows = $distinct->map(function ($r) use ($shortToFull) {
            $target = $this->normalize($r->prefecture, $shortToFull);

            return [
                $r->prefecture ?? '(NULL)',
                number_format((int) $r->cnt),
                $target ?? '— (対象外/不明)',
                $this->classify($r->prefecture, $target),
            ];
        })->all();

        $this->table(['現在値', '件数', '統一先', '判定'], $allRows);

        // --- 2. 変更が必要なパターン / 正規化できなかった値を抽出 ---
        $changes = [];      // [from => ['to' => ..., 'count' => ...]]
        $unknown = [];      // 正規化不能（NULL・空・未知の値）
        $affectedRows = 0;

        foreach ($distinct as $r) {
            $from = $r->prefecture;
            $target = $this->normalize($from, $shortToFull);

            if ($target === null) {
                $unknown[] = [$from ?? '(NULL)', number_format((int) $r->cnt)];

                continue;
            }
            if ($target !== $from) {
                $changes[] = [$from, $target, (int) $r->cnt];
                $affectedRows += (int) $r->cnt;
            }
        }

        $this->newLine();
        if (empty($changes)) {
            $this->info('✓ 表記揺れによる変更対象はありません（既に統一済み）。');
        } else {
            $this->warn('▼ 統一が必要な表記揺れパターン');
            $this->table(
                ['変更前', '変更後', '対象件数'],
                array_map(fn ($c) => [$c[0], $c[1], number_format($c[2])], $changes),
            );
            $this->line('変更対象レコード合計: '.number_format($affectedRows).' 件 / '.count($changes).' パターン');
        }

        if (! empty($unknown)) {
            $this->newLine();
            $this->warn('▼ 正規化できなかった値（手動確認が必要・本コマンドでは変更しません）');
            $this->table(['値', '件数'], $unknown);
        }

        // --- 3. dry-run なら終了。実行モードなら更新 ---
        if ($isDryRun) {
            $this->newLine();
            $this->info('[dry-run] 変更は行っていません。実行するには --dry-run を外してください。');

            return Command::SUCCESS;
        }

        if (empty($changes)) {
            return Command::SUCCESS;
        }

        $this->newLine();
        $updated = 0;
        DB::transaction(function () use ($changes, &$updated) {
            foreach ($changes as [$from, $to]) {
                // $from は DB から取得した実値そのまま（前後空白等もそのまま完全一致）
                $updated += Shop::where('prefecture', $from)->update(['prefecture' => $to]);
            }
        });

        $this->info('✓ 統一完了: '.number_format($updated).' 件の shops.prefecture を更新しました。');
        $this->newLine();
        $this->warn('※ Meilisearch インデックス（listings）は shops.prefecture をコピーしています。');
        $this->warn('  反映するには再インデックスしてください:');
        $this->line('    php artisan scout:import "App\\Models\\Listing"');

        return Command::SUCCESS;
    }

    /**
     * 都/道/府/県 を剥がした短縮名 → 正式名 のマップを構築する。
     * 例: 京都 => 京都府, 大阪 => 大阪府, 東京 => 東京都, 北海道(=北海) => 北海道
     */
    private function buildShortToFullMap(): array
    {
        $map = [];
        foreach (self::CANONICAL as $full) {
            $short = str_replace(['都', '道', '府', '県'], '', $full);
            $map[$short] = $full;   // 北海道 → 北海, それ以外は県/都/府を除いた地名
            $map[$full] = $full;    // 正式名そのものも受理
        }

        return $map;
    }

    /**
     * 任意の prefecture 値を正式名へ正規化する。判定不能なら null。
     */
    private function normalize(?string $raw, array $shortToFull): ?string
    {
        if ($raw === null) {
            return null;
        }
        $trimmed = trim($raw);
        if ($trimmed === '') {
            return null;
        }
        if (isset($shortToFull[$trimmed])) {
            return $shortToFull[$trimmed];
        }
        $short = str_replace(['都', '道', '府', '県'], '', $trimmed);

        return $shortToFull[$short] ?? null;
    }

    private function classify(?string $from, ?string $target): string
    {
        if ($target === null) {
            return '対象外/不明';
        }
        if ($target === $from) {
            return '統一済み';
        }

        return '要変更';
    }
}
