<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BikeModel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * 人が目視確認済みの「残す側 canonical / 消す側 dupe」の id 組（CSV）を受け取り、統合する実行コマンド。
 *
 * 判定と実行の分離が目的。検出は models:detect-name-dupes（カタカナ↔ローマ字の表記体系差など model:dedup が
 * 拾えない重複）で行い、人が確認した組だけを、実績ある DedupBikeModels の統合処理に流す。
 * canonical はコマンドが選び直さない（pickCanonical 不使用）＝人が指定した survivor を必ず残す。
 *
 * 安全設計（DedupBikeModels と同じ二重ゲート）:
 *   - 既定は dry-run。--execute を明示しない限り DB を一切変更しない。
 *   - --execute には --i-have-a-backup が必須。片方だけなら拒否して終了。
 *   - 実行前に対象件数を出して確認プロンプト。--no-interaction 時は実行しない。
 *   - 1組ごとに DB::transaction ＋ try/catch で隔離（1組の失敗が他組へ波及しない）。
 *
 * 統合本体は DedupBikeModels::mergePair（本タスクで追加した public 入口）を再利用する。再実装しない。
 */
final class MergeConfirmedModelPairs extends Command
{
    protected $signature = 'model:merge-pairs
        {csv : canonical_id,dupe_id のCSVパス（本番の/tmp。1行1組・先頭#と空行は無視）}
        {--execute : 実際に統合する（破壊的）。--i-have-a-backup 必須}
        {--i-have-a-backup : DBバックアップ取得済みの明示確認（--execute の必須ゲート）}';

    protected $description = '確認済みの canonical,dupe 組(CSV)を既存マージ処理で統合（既定dry-run。--execute+--i-have-a-backupで実行）';

    /**
     * dry-run で「付け替え行数」を数える対象。mergeGroup が実際に触る bike_model_id 参照テーブル。
     * （market_stats/region_stats は再計算のため削除、seo_compares は別途下で集計）
     */
    private const REPOINT_TABLES = [
        'listings', 'reviews', 'my_bikes', 'bike_news', 'bike_model_identifiers',
        'discussion_threads', 'model_questions', 'bike_model_market_stats',
        'model_region_price_stats', 'market_price_logs', 'bike_model_videos',
        'push_subscriptions', 'model_fitments',
    ];

    public function handle(): int
    {
        $path = (string) $this->argument('csv');
        if (! is_file($path) || ! is_readable($path)) {
            $this->error("CSVを読めません: {$path}");

            return self::FAILURE;
        }

        $execute = (bool) $this->option('execute');
        if ($execute && ! $this->option('i-have-a-backup')) {
            $this->error('🚫 破壊的操作です。DBバックアップを取得のうえ --execute に --i-have-a-backup を併せて再実行してください。');

            return self::FAILURE;
        }

        // ── CSV パース（形式不正はスキップ理由に積む） ──
        [$parsed, $skips] = $this->parseCsv($path);
        if ($parsed === []) {
            $this->warn('有効な行がありません（#と空行を除くと0件、または形式不正のみ）。');
            $this->printSkips($skips);

            return self::SUCCESS;
        }

        // ── 参照 id をまとめて1クエリで取得（メーカー名も） ──
        $ids = collect($parsed)->flatMap(fn ($p) => [$p['canonical_id'], $p['dupe_id']])->unique()->all();
        $models = BikeModel::query()
            ->with('manufacturer:id,name,slug')
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        // ── バリデーション（存在しない/同一/統合済みはスキップ） ──
        $valid = [];
        foreach ($parsed as $p) {
            $c = $models->get($p['canonical_id']);
            $d = $models->get($p['dupe_id']);
            $ln = $p['line'];

            if ($c === null || $d === null) {
                $miss = [];
                if ($c === null) {
                    $miss[] = "canonical={$p['canonical_id']}";
                }
                if ($d === null) {
                    $miss[] = "dupe={$p['dupe_id']}";
                }
                $skips[] = "L{$ln}: 存在しないid（".implode(' / ', $miss).'）';

                continue;
            }
            if ((int) $c->id === (int) $d->id) {
                $skips[] = "L{$ln}: canonical と dupe が同一 (id={$c->id})";

                continue;
            }
            if ($c->merged_into_id !== null) {
                $skips[] = "L{$ln}: canonical(id={$c->id}) は既に統合済み (merged_into_id={$c->merged_into_id})";

                continue;
            }
            if ($d->merged_into_id !== null) {
                $skips[] = "L{$ln}: dupe(id={$d->id}) は既に統合済み (merged_into_id={$d->merged_into_id})";

                continue;
            }

            $valid[] = ['canonical' => $c, 'dupe' => $d, 'line' => $ln];
        }

        // ── ドライラン出力（＝計画。実行時も同じ計画を先に見せる） ──
        $this->newLine();
        $this->line('===== model:merge-pairs '.($execute ? '実行計画' : 'ドライラン（DB変更なし）').' =====');
        $this->comment('※ canonical はCSV指定を必ず残す（pickCanonical 不使用）。統合本体は DedupBikeModels::mergePair を再利用。');

        $crossMaker = 0;
        $totalRepoint = 0;
        foreach ($valid as $v) {
            /** @var BikeModel $c */
            $c = $v['canonical'];
            /** @var BikeModel $d */
            $d = $v['dupe'];

            $cross = (int) $c->manufacturer_id !== (int) $d->manufacturer_id;
            if ($cross) {
                $crossMaker++;
            }
            $slugToNull = ! empty($c->slug) && $d->slug === $c->slug;

            $counts = $this->repointCounts((int) $d->id);
            $pairRepoint = array_sum($counts);
            $totalRepoint += $pairRepoint;

            $this->newLine();
            $this->line('■ L'.$v['line'].($cross ? '  ⚠️メーカーが異なる（跨ぎ統合）' : ''));
            $this->line('    canonical: '.$this->describe($c));
            $this->line('    dupe     : '.$this->describe($d));
            $nonZero = array_filter($counts, fn ($n) => $n > 0);
            $breakdown = $nonZero === [] ? '（参照行なし）'
                : implode(', ', array_map(fn ($t, $n) => "{$t}={$n}", array_keys($nonZero), array_values($nonZero)));
            $this->line(sprintf(
                '    操作     : 付け替え行数 計%d [%s] / slugをNULL=%s',
                $pairRepoint,
                $breakdown,
                $slugToNull ? 'する（canonicalと同一slug=「'.$d->slug.'」→301不要）' : 'しない'.($d->slug ? '（dupe slug=「'.$d->slug.'」を301用に維持）' : '（dupe slug 無）'),
            ));
        }

        // ── 集計 ──
        $this->newLine();
        $this->line('----- 集計 -----');
        $this->line(sprintf('  対象組数            : %d', count($valid)));
        $this->line(sprintf('  スキップ数          : %d', count($skips)));
        $this->line(sprintf('  メーカーをまたぐ組数: %d', $crossMaker));
        $this->line(sprintf('  付け替え総行数      : %d', $totalRepoint));
        $this->printSkips($skips);

        if ($valid === []) {
            $this->newLine();
            $this->warn('有効な統合対象がありません。');

            return self::SUCCESS;
        }

        // ── ドライランはここまで ──
        if (! $execute) {
            $this->newLine();
            $this->warn('※ ドライランです。統合するには：');
            $this->line("  php artisan model:merge-pairs {$path} --execute --i-have-a-backup");

            return self::SUCCESS;
        }

        // ── 実行（確認プロンプト。非対話では実行しない） ──
        if (! $this->input->isInteractive()) {
            $this->error('🚫 --no-interaction では実行しません（統合前の確認が必要です）。');

            return self::FAILURE;
        }
        $this->newLine();
        if (! $this->confirm(count($valid).' 組を統合します。DB を変更します。よろしいですか？', false)) {
            $this->warn('中止しました。DB は変更していません。');

            return self::SUCCESS;
        }

        $dedup = app(DedupBikeModels::class);
        $merged = 0;
        $failed = 0;
        foreach ($valid as $v) {
            /** @var BikeModel $c */
            $c = $v['canonical'];
            /** @var BikeModel $d */
            $d = $v['dupe'];
            try {
                // 1組=1トランザクション。mergePair 内の slug NULL 化と mergeGroup の付け替えを atomic に。
                DB::transaction(fn () => $dedup->mergePair($c, collect([$d])));
                $merged++;
                $this->info("✓ 統合 canonical id={$c->id} ← dupe id={$d->id}");
            } catch (Throwable $ex) {
                $failed++;
                $this->error("✗ 失敗 canonical id={$c->id} dupe id={$d->id}: ".$ex->getMessage());
            }
        }

        $this->newLine();
        $this->info("完了: {$merged}組統合 / {$failed}組失敗 / ".count($skips).'件スキップ');
        $this->line('次の手順（本番コンテナ内・DedupBikeModels と同じ）:');
        $this->line('  php artisan scout:sync-flagged          # 付け替えlistingをMeilisearch差分同期');
        $this->line('  php artisan bikes:update-market-stats   # canonicalの相場/在庫再計算');
        $this->line('  php artisan cache:warm-models --all     # モデルページキャッシュ再生成');
        $this->line('  php artisan comparison:generate-pairs   # 比較ペア再生成（canonical/slug反映）');

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * CSV を読み、[有効行(canonical_id,dupe_id,line), スキップ理由] を返す。
     * 先頭 # と空行は無視。列不足・非数値はスキップ理由に積む（読み取りのみ）。
     *
     * @return array{0: array<int, array{canonical_id:int, dupe_id:int, line:int}>, 1: array<int, string>}
     */
    private function parseCsv(string $path): array
    {
        $pairs = [];
        $skips = [];
        $lines = file($path, FILE_IGNORE_NEW_LINES) ?: [];
        foreach ($lines as $i => $raw) {
            $lineNo = $i + 1;
            $t = trim((string) $raw);
            if ($t === '' || str_starts_with($t, '#')) {
                continue;
            }
            $cols = array_map('trim', explode(',', $t));
            if (count($cols) < 2 || ! ctype_digit($cols[0]) || ! ctype_digit($cols[1])) {
                $skips[] = "L{$lineNo}: 形式不正「{$t}」（canonical_id,dupe_id を正の整数で）";

                continue;
            }
            $pairs[] = ['canonical_id' => (int) $cols[0], 'dupe_id' => (int) $cols[1], 'line' => $lineNo];
        }

        return [$pairs, $skips];
    }

    /** dupe を参照する行数をテーブル別に数える（読み取りのみ）。seo_compares は model1/2_id 両方。 */
    private function repointCounts(int $dupeId): array
    {
        $counts = [];
        foreach (self::REPOINT_TABLES as $table) {
            $counts[$table] = (int) DB::table($table)->where('bike_model_id', $dupeId)->count();
        }
        $counts['seo_compares'] = (int) DB::table('seo_compares')
            ->where(fn ($q) => $q->where('model1_id', $dupeId)->orWhere('model2_id', $dupeId))
            ->count();

        return $counts;
    }

    /** 1レコードを「id / name / slug / メーカー / 在庫 / 適合行数」で1行に整形。 */
    private function describe(BikeModel $m): string
    {
        $stock = (int) DB::table('listings')->where('bike_model_id', $m->id)->where('is_sold_out', false)->count();
        $fitments = (int) DB::table('model_fitments')->where('bike_model_id', $m->id)->count();

        return sprintf(
            'id=%d / "%s" / slug=%s / %s / 在庫%d / 適合%d',
            $m->id,
            $m->name,
            $m->slug ?? '(無)',
            $m->manufacturer?->name ?? '(メーカー不明)',
            $stock,
            $fitments,
        );
    }

    /** @param  array<int, string>  $skips */
    private function printSkips(array $skips): void
    {
        if ($skips === []) {
            return;
        }
        $this->newLine();
        $this->warn('----- スキップ ('.count($skips).'件) -----');
        foreach ($skips as $s) {
            $this->line('    '.$s);
        }
    }
}
