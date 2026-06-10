<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BikeModel;
use App\Models\Manufacturer;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * 重複 BikeModel の検出（Phase 0・read-only）。
 *
 * ⚠️ 本ビルドは「検出＋レポート」のみで DB を一切変更しない。
 *    付け替え/slug/301/無効化などの破壊的実行路は次スライスで実装する。
 *    スペックの鉄則: dry-run で規模確認 → レビュー → Phase 1 単体 → Phase 2 バッチ。
 *
 * 検出: 名前の表記ゆれ（全角/半角・空白）を畳んだ正規化キー × manufacturer_id でグルーピング。
 *   key = mb_strtolower( 全空白除去( mb_convert_kana(name,'as') ) )
 * これ以上は正規化しない（単語/サフィックスを削らない＝CB400 と CB400SF は別キーのまま）。
 *
 * 使い方:
 *   php artisan model:dedup --dry-run            # 全体スコープ
 *   php artisan model:dedup --dry-run --group=590  # model 590 を含むグループだけ
 *   php artisan model:dedup --dry-run --limit=30   # 表示グループ数
 */
final class DedupBikeModels extends Command
{
    protected $signature = 'model:dedup
        {--dry-run : 検出のみ（本ビルドは常に非破壊。実行路は未実装）}
        {--group= : 指定した bike_model_id を含むグループだけ表示}
        {--limit=30 : 表示するグループ数の上限（在庫合計の多い順）}';

    protected $description = '重複BikeModelの検出＋scopeレポート（Phase 0・read-only。統合は次スライス）';

    /** canonical 選定のスペック充実度に使う列 */
    private const SPEC_FIELDS = ['displacement', 'weight', 'seat_height', 'max_power', 'engine_type', 'tank_capacity'];

    public function handle(): int
    {
        if (! $this->option('dry-run')) {
            $this->warn('⚠️ 本ビルドは検出のみ（統合の実行路は次スライス）。--dry-run と同じ動作でレポートします。');
        }

        // 1. 統合済みを除く全モデルを読み込み、active 在庫数を付与
        $models = BikeModel::query()
            ->whereNull('merged_into_id')
            ->with('manufacturer:id,name,slug')
            ->withCount(['listings' => fn ($q) => $q->active()])
            ->get();

        // 2. 正規化キーで manufacturer_id ごとにグルーピング → count>1
        $groups = $models
            ->groupBy(fn (BikeModel $m) => $m->manufacturer_id . '|' . $this->normalizeKey((string) $m->name))
            ->filter(fn (Collection $g) => $g->count() > 1);

        // --group: 指定モデルを含むグループだけ
        if ($this->option('group') !== null) {
            $gid = (int) $this->option('group');
            $groups = $groups->filter(fn (Collection $g) => $g->contains(fn (BikeModel $m) => $m->id === $gid));
        }

        // 3. 各グループを評価（canonical 提案・auto/manual 判定）
        $evaluated = $groups->map(function (Collection $g) {
            $canonical = $this->pickCanonical($g);
            $distinctDisp = $g->pluck('displacement')->reject(fn ($v) => $v === null)->unique();
            $distinctCat = $g->pluck('category_id')->reject(fn ($v) => $v === null)->unique();

            // ハードガード（manual）= 排気量不一致のみ（別車種疑い）。
            // category_id はデータが不安定で、同名・同排気量でも分裂レコード間でズレることが多い
            // （レブル250/セロー250 等）。よって category 不一致は manual ブロックにせず、
            // ソフト注記（auto可・統合時に canonical のカテゴリへ寄せる）に降格する。
            $manualReasons = [];
            if ($distinctDisp->count() > 1) {
                $manualReasons[] = '排気量不一致(' . $distinctDisp->sort()->implode('/') . ')';
            }
            $softNotes = [];
            if ($distinctCat->count() > 1) {
                $softNotes[] = 'カテゴリ統一(' . $distinctCat->sort()->implode('/') . '→cat' . ($canonical->category_id ?? '-') . ')';
            }

            return [
                'members' => $g,
                'canonical' => $canonical,
                'total_stock' => (int) $g->sum('listings_count'),
                'auto' => $manualReasons === [],
                'reasons' => $manualReasons,
                'notes' => $softNotes,
            ];
        })->sortByDesc('total_stock')->values();

        $autoGroups = $evaluated->where('auto', true)->values();
        $manualGroups = $evaluated->where('auto', false)->values();

        // ── レポート ──────────────────────────────────────────
        $this->newLine();
        $this->line('===== model:dedup Phase 0 レポート（read-only・DB変更なし）=====');
        $this->line('スキャン対象モデル（統合済み除く）: ' . $models->count());
        $this->line('重複グループ                      : ' . $evaluated->count());
        $this->line('  auto-merge 可                  : ' . $autoGroups->count());
        $this->line('  manual review 必要             : ' . $manualGroups->count());

        // maker 重複の兆候（別案件）
        $this->reportMakerDuplicates();

        // 付け替え影響（blast radius）: auto 対象の dupe(非survivor) id 群
        $autoDupeIds = $autoGroups->flatMap(
            fn ($e) => $e['members']->reject(fn (BikeModel $m) => $m->id === $e['canonical']->id)->pluck('id')
        )->all();
        $this->reportBlastRadius($autoDupeIds);

        // 各グループ詳細（在庫合計の多い順・--limit）
        $limit = (int) $this->option('limit');
        $this->newLine();
        $this->line("----- グループ詳細（在庫合計の多い順・上位{$limit}）-----");
        foreach ($evaluated->take($limit) as $e) {
            $tag = $e['auto'] ? 'AUTO ' : 'MANUAL';
            $reason = $e['reasons'] ? ' ['.implode(',', $e['reasons']).']' : '';
            $note = ! empty($e['notes']) ? ' {'.implode(',', $e['notes']).'}' : '';
            $this->newLine();
            $this->line("[{$tag}] 在庫合計 {$e['total_stock']}台{$reason}{$note}");
            foreach ($e['members']->sortByDesc('listings_count') as $m) {
                $mark = $m->id === $e['canonical']->id ? ' ★canonical提案' : '';
                $this->line(sprintf(
                    '    id=%-6d 在庫%-5d slug=%-18s disp=%-5s cat=%-4s "%s"%s',
                    $m->id,
                    $m->listings_count,
                    $m->slug ?? '(無)',
                    $m->displacement ?? '-',
                    $m->category_id ?? '-',
                    $m->name,
                    $mark
                ));
            }
        }

        $this->newLine();
        $this->warn('※ 検出のみ。統合（付け替え/slug/301/無効化）は次スライス。実行前に必ず DB バックアップ。');

        return self::SUCCESS;
    }

    /**
     * 表記ゆれ吸収の正規化キー。保守的（語の削除はしない）。
     * 全角英数→半角・全角空白→半角 → 全空白除去 → 小文字。
     */
    private function normalizeKey(string $name): string
    {
        $s = mb_convert_kana($name, 'as');           // 全角英数/空白 → 半角
        $s = preg_replace('/[\s　]+/u', '', $s) ?? $s; // 全空白除去（ASCII + 全角）

        return mb_strtolower($s);
    }

    private function pickCanonical(Collection $members): BikeModel
    {
        return $members->sort(function (BikeModel $a, BikeModel $b) {
            if ($a->listings_count !== $b->listings_count) {
                return $b->listings_count <=> $a->listings_count; // 在庫最多
            }
            $sa = $this->specScore($a);
            $sb = $this->specScore($b);
            if ($sa !== $sb) {
                return $sb <=> $sa; // スペック充実
            }

            return $a->id <=> $b->id; // id小（安定）
        })->first();
    }

    private function specScore(BikeModel $m): int
    {
        $score = 0;
        foreach (self::SPEC_FIELDS as $f) {
            if ($m->{$f} !== null && $m->{$f} !== '') {
                $score++;
            }
        }

        return $score;
    }

    /**
     * メーカー名の表記ゆれ重複の兆候を警告（dedup の前提が崩れる別案件）。
     */
    private function reportMakerDuplicates(): void
    {
        $dupes = Manufacturer::query()
            ->get(['id', 'name'])
            ->groupBy(fn (Manufacturer $m) => $this->normalizeKey((string) $m->name))
            ->filter(fn (Collection $g) => $g->count() > 1);

        $this->newLine();
        if ($dupes->isEmpty()) {
            $this->line('maker重複の兆候: なし');

            return;
        }
        $this->warn('⚠️ maker重複の兆候あり（別案件・本dedupの前提に影響）:');
        foreach ($dupes as $key => $g) {
            $this->line('    ' . $g->map(fn ($m) => "id={$m->id}:{$m->name}")->implode(' / '));
        }
    }

    /**
     * auto対象 dupe id 群が各 FK テーブルから何行参照されているか（付け替え規模）。
     */
    private function reportBlastRadius(array $dupeIds): void
    {
        $this->newLine();
        $this->line('----- 付け替え影響（auto対象 dupe ' . count($dupeIds) . '件が参照される行数）-----');
        if ($dupeIds === []) {
            $this->line('    （対象なし）');

            return;
        }

        // bike_model_id を参照する FK（live schema で確定した11カラム）
        $singleCol = [
            'listings' => 'bike_model_id',
            'reviews' => 'bike_model_id',
            'my_bikes' => 'bike_model_id',
            'push_subscriptions' => 'bike_model_id',
            'bike_model_market_stats' => 'bike_model_id',
            'market_price_logs' => 'bike_model_id',
            'bike_model_videos' => 'bike_model_id',
            'bike_news' => 'bike_model_id',
            'bike_model_identifiers' => 'bike_model_id',
        ];
        foreach ($singleCol as $table => $col) {
            $n = DB::table($table)->whereIn($col, $dupeIds)->count();
            $this->line(sprintf('    %-26s %d', "{$table}.{$col}", $n));
        }
        // seo_compares は model1_id / model2_id の両方
        $seo = DB::table('seo_compares')
            ->whereIn('model1_id', $dupeIds)
            ->orWhereIn('model2_id', $dupeIds)
            ->count();
        $this->line(sprintf('    %-26s %d', 'seo_compares.model1/2_id', $seo));
    }
}
