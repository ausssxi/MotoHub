<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BikeModel;
use App\Models\Manufacturer;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 重複 BikeModel の検出＋統合（dedup）。
 *
 * 既定は read-only レポート。統合は破壊的なので二重ゲート:
 *   - `--execute` を付けないと一切書き込まない（既定はレポート）。
 *   - `--execute` には `--i-have-a-backup`（DBバックアップ取得済みの明示確認）が必須。
 *   - さらに**グループ単位の手動承認**（y/スキップ/以降すべて/中止）。--limit/--group で範囲制限。
 *
 * 検出: 名前の表記ゆれ（全角/半角・空白・ダッシュ類/中黒の有無）を畳んだ正規化キー × manufacturer_id でグルーピング。
 *   key = mb_strtolower( ダッシュ類/中黒除去( 全空白除去( mb_convert_kana(name,'as') ) ) )
 *   ※長音符 U+30FC(ー) は除去しない（「モンキー」≠「モンキ」を保つため）。
 * これ以上は正規化しない（語/サフィックスは削らない＝CB400 と CB400SF は別キーのまま）。
 * FPガード: 非null の displacement が割れたら別車種疑い＝manual（auto対象外）。
 *           category_id 不一致はデータ不安定なため auto可・統合時に canonical のカテゴリへ寄せる。
 *
 * 使い方:
 *   php artisan model:dedup                                  # 検出レポート（read-only）
 *   php artisan model:dedup --group=590                      # レブル群だけレポート
 *   php artisan model:dedup --execute --i-have-a-backup --group=590   # Phase1: 単体統合（手動承認）
 *   php artisan model:dedup --execute --i-have-a-backup --limit=20    # Phase2: 上位20群を順に承認
 * 統合後: scout:sync-flagged / bikes:update-market-stats / cache:warm-models --all / comparison:generate-pairs
 */
final class DedupBikeModels extends Command
{
    protected $signature = 'model:dedup
        {--dry-run : 検出のみ（既定動作。明示用）}
        {--execute : 実際に統合する（破壊的）。--i-have-a-backup 必須}
        {--i-have-a-backup : DBバックアップ取得済みの明示確認（--execute の必須ゲート）}
        {--force : 排気量不一致(manual)グループも統合許可（--group 指定時のみ推奨）}
        {--group= : 指定した bike_model_id を含むグループだけ対象}
        {--limit=30 : レポート表示数／execute時は処理グループ数の上限（在庫合計の多い順）}';

    protected $description = '重複BikeModelの検出＋統合（既定read-only。--execute+--i-have-a-backup+手動承認で統合）';

    /** canonical 選定のスペック充実度に使う列 */
    private const SPEC_FIELDS = ['displacement', 'weight', 'seat_height', 'max_power', 'engine_type', 'tank_capacity'];

    public function handle(): int
    {
        $evaluated = $this->evaluateGroups();

        if ($this->option('execute')) {
            return $this->executeMerges($evaluated);
        }

        $this->printReport($evaluated);

        return self::SUCCESS;
    }

    /**
     * 重複グループを評価（canonical 提案・auto/manual・notes）。在庫合計の多い順。
     *
     * @return Collection<int, array{members:Collection,canonical:BikeModel,total_stock:int,auto:bool,reasons:array,notes:array}>
     */
    private function evaluateGroups(): Collection
    {
        $models = BikeModel::query()
            ->whereNull('merged_into_id')
            ->with('manufacturer:id,name,slug')
            ->withCount(['listings' => fn ($q) => $q->active()])
            ->get();

        $groups = $models
            ->groupBy(fn (BikeModel $m) => $m->manufacturer_id . '|' . $this->normalizeKey((string) $m->name))
            ->filter(fn (Collection $g) => $g->count() > 1);

        if ($this->option('group') !== null) {
            $gid = (int) $this->option('group');
            $groups = $groups->filter(fn (Collection $g) => $g->contains(fn (BikeModel $m) => $m->id === $gid));
        }

        return $groups->map(function (Collection $g) {
            $canonical = $this->pickCanonical($g);
            $distinctDisp = $g->pluck('displacement')->reject(fn ($v) => $v === null)->unique();
            $distinctCat = $g->pluck('category_id')->reject(fn ($v) => $v === null)->unique();

            $manualReasons = [];
            if ($distinctDisp->count() > 1) {
                $manualReasons[] = '排気量不一致(' . $distinctDisp->sort()->implode('/') . ')';
            }
            $softNotes = [];
            if ($distinctCat->count() > 1) {
                $softNotes[] = 'カテゴリ不一致(' . $distinctCat->sort()->implode('/') . ')';
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
    }

    // ───────────────────────── 統合（破壊的） ─────────────────────────

    private function executeMerges(Collection $evaluated): int
    {
        if (! $this->option('i-have-a-backup')) {
            $this->error('🚫 破壊的操作です。DBバックアップを取得のうえ --i-have-a-backup を付けて再実行してください。');

            return self::FAILURE;
        }

        $limit = (int) $this->option('limit');
        $targets = $evaluated->take($limit);
        $merged = 0;
        $skipped = 0;
        $approveAll = false;

        foreach ($targets as $e) {
            $canonical = $e['canonical'];
            $dupes = $e['members']->reject(fn (BikeModel $m) => $m->id === $canonical->id)->values();

            if (! $e['auto'] && ! $this->option('force')) {
                $this->warn("スキップ(manual): {$canonical->name} [".implode(',', $e['reasons']).'] — --force で統合可');
                $skipped++;
                continue;
            }

            $this->printGroupPlan($e, $dupes);

            if (! $approveAll) {
                $ans = $this->choice(
                    'このグループを統合しますか？',
                    ['y' => '統合する', 'n' => 'スキップ', 'a' => '以降すべて統合', 'q' => '中止'],
                    'n'
                );
                if ($ans === 'q') {
                    $this->warn('中止しました。');
                    break;
                }
                if ($ans === 'n') {
                    $skipped++;
                    continue;
                }
                if ($ans === 'a') {
                    $approveAll = true;
                }
            }

            $this->mergeGroup($canonical, $dupes);
            $merged++;
            $this->info("✓ 統合: canonical id={$canonical->id} ← dupe ".$dupes->pluck('id')->implode(','));
        }

        $this->newLine();
        $this->info("完了: {$merged}群を統合 / {$skipped}群スキップ。");
        $this->line('次の手順（本番コンテナ内）:');
        $this->line('  php artisan scout:sync-flagged          # 付け替えlistingをMeilisearch差分同期');
        $this->line('  php artisan bikes:update-market-stats   # canonicalの相場/在庫再計算');
        $this->line('  php artisan cache:warm-models --all     # モデルページキャッシュ再生成');
        $this->line('  php artisan comparison:generate-pairs   # 比較ペア再生成（canonical/slug反映）');

        return self::SUCCESS;
    }

    /**
     * 1グループの統合を1トランザクションで実行（all-or-nothing）。
     */
    private function mergeGroup(BikeModel $canonical, Collection $dupes): void
    {
        $dupeIds = $dupes->pluck('id')->all();

        // キャッシュパージ用に統合前の (mfrSlug, slug/id) を控える
        $mfrSlug = $canonical->manufacturer?->slug ?? 'id';
        $cacheKeys = collect([$canonical])->merge($dupes)
            ->flatMap(fn (BikeModel $m) => array_filter([
                BikeModel::modelDetailCacheKey($mfrSlug, (string) $m->id),
                $m->slug ? BikeModel::modelDetailCacheKey($mfrSlug, $m->slug) : null,
            ]))
            ->unique()->all();

        DB::transaction(function () use ($canonical, $dupes, $dupeIds) {
            // model_id に unique 無 → blind UPDATE。listings は再インデックスフラグも立てる
            DB::table('listings')->whereIn('bike_model_id', $dupeIds)
                ->update(['bike_model_id' => $canonical->id, 'needs_reindex' => true]);
            // 複合unique無し（PRIMARYのみ）の参照テーブルは blind UPDATE で付け替え。
            // discussion_threads / model_questions もここ（付け替え漏れだった。放置すると
            // bike_models の行は削除されず merged_into_id が付くだけなので、FK違反は起きず
            // クチコミ・質問が静かに画面から消える）。
            foreach (['reviews', 'my_bikes', 'bike_news', 'bike_model_identifiers', 'discussion_threads', 'model_questions'] as $table) {
                DB::table($table)->whereIn('bike_model_id', $dupeIds)->update(['bike_model_id' => $canonical->id]);
            }

            // bike_model_market_stats: UNIQUE(bike_model_id) → dupe行は削除（後で再計算）
            DB::table('bike_model_market_stats')->whereIn('bike_model_id', $dupeIds)->delete();

            // model_region_price_stats: UNIQUE(bike_model_id, region_block) の集計 → dupe行は削除（後で再計算）。
            // bike_model_market_stats と同じ扱い。破棄する行は黙って消さず件数と対象IDをログに残す。
            $regionStatIds = DB::table('model_region_price_stats')->whereIn('bike_model_id', $dupeIds)->pluck('id')->all();
            if ($regionStatIds !== []) {
                Log::info('model:dedup drop model_region_price_stats', [
                    'canonical' => $canonical->id,
                    'count' => count($regionStatIds),
                    'ids' => $regionStatIds,
                ]);
                DB::table('model_region_price_stats')->whereIn('id', $regionStatIds)->delete();
            }

            // 複合uniqueのある関係: canonicalに既存ならスキップ削除、無ければ付け替え
            $this->repointWithUnique('market_price_logs', ['recorded_at'], $dupeIds, $canonical->id);
            $this->repointWithUnique('bike_model_videos', ['video_id'], $dupeIds, $canonical->id);
            $this->repointWithUnique('push_subscriptions', ['endpoint_hash'], $dupeIds, $canonical->id);
            // model_fitments: UNIQUE(bike_model_id, task, frame_code, year_range)。適合表（バッテリー/プラグ/オイル）で影響大。
            // frame_code / year_range は migration 上 NOT NULL DEFAULT ''（「型式区別なし」は空文字・NULL不使用）なので、
            // 実データは '' 同士が正常に衝突し、既定の repointWithUnique でも正しく重複排除できる。
            // それでも coalesceNull: true を付けるのは保険。もし raw insert 等で NULL が紛れても、既定パスは
            // NULL 行を衝突対象外に隔離して canonical に重複を残す（MySQL の UNIQUE が NULL を衝突扱いしないため）
            // ので、NULL='' とみなして1行へ正規化する。実データ（''）に対しては挙動不変（no-op）。
            $this->repointWithUnique('model_fitments', ['task', 'frame_code', 'year_range'], $dupeIds, $canonical->id, coalesceNull: true);

            // seo_compares: dupe参照ペアは無効化（§9 で comparison:generate-pairs が再生成）
            DB::table('seo_compares')
                ->where(fn ($q) => $q->whereIn('model1_id', $dupeIds)->orWhereIn('model2_id', $dupeIds))
                ->update(['is_active' => false]);

            // survivor に clean slug を寄せる（canonical が slug 無で dupe が持っている場合）
            $this->assignSurvivorSlug($canonical, $dupes);

            // dupe を統合済みに（= 301シグナル＋一覧除外）。slug は assignSurvivorSlug で必要分のみ空けた
            BikeModel::whereIn('id', $dupeIds)->update(['merged_into_id' => $canonical->id]);
        });

        foreach ($cacheKeys as $key) {
            Cache::forget($key);
        }

        Log::info('model:dedup merged', [
            'canonical' => $canonical->id,
            'dupes' => $dupeIds,
        ]);
    }

    /**
     * 複合unique(bike_model_id + $keyCols)のテーブルを安全に付け替える。
     *
     * canonical∪全dupe を $keyCols タプルで束ね、タプルごとに「最終的に canonical に残る行」を1行へ正規化:
     *   - canonical 既存行があればそれを残し、同値の dupe 行を全削除。
     *   - 無ければ dupe 行を1行だけ残し、同値の他 dupe 行を削除（dupe同士が共有しても衝突しない）。
     * canonical 自身の行は決して削除しない。正規化後に残った dupe 行のみ bulk repoint する。
     * （旧実装は canonical との衝突しか潰さず、canonical+複数dupeが同一ユニーク値を共有する群で
     *   (bike_model_id,$keyCols) 違反を投げていた。例: ホークIICB400Tの 562+3537+4848 が同一 video_id 共有）。
     */
    private function repointWithUnique(string $table, array $keyCols, array $dupeIds, int $canonicalId, bool $coalesceNull = false): void
    {
        $rows = DB::table($table)
            ->whereIn('bike_model_id', array_merge([$canonicalId], $dupeIds))
            ->get(array_merge(['id', 'bike_model_id'], $keyCols));

        $deleteIds = $rows
            ->groupBy(function ($r) use ($keyCols, $coalesceNull) {
                $vals = array_map(fn ($c) => $r->{$c}, $keyCols);

                // coalesceNull: NULL を空文字に畳んで比較する。MySQL の UNIQUE は NULL 同士を
                // 衝突扱いしないため、既定パスだと NULL を含むキー行が canonical に重複して残りうる。
                // NULL='' とみなして重複を1行へ正規化する（呼び出し側が明示したときだけ）。
                // 値が非NULLのときは既定パスと同じ文字列キーになるため挙動は変わらない。
                if ($coalesceNull) {
                    return implode("\0", array_map(fn ($v) => (string) ($v ?? ''), $vals));
                }

                // 既定: null を含むタプルは MySQL のユニーク制約上どの行とも衝突しない → 行単位で一意化し正規化対象外に
                return in_array(null, $vals, true) ? 'null:' . $r->id : implode("\0", $vals);
            })
            ->flatMap(function ($group) use ($canonicalId) {
                $hasCanonical = $group->contains(fn ($r) => (int) $r->bike_model_id === $canonicalId);
                $dupeRows = $group->reject(fn ($r) => (int) $r->bike_model_id === $canonicalId)
                    ->sortBy('id')->values();

                // canonical 既存ありなら dupe を全削除、無ければ先頭1行だけ残し他を削除
                return ($hasCanonical ? $dupeRows : $dupeRows->slice(1))->pluck('id');
            })
            ->all();

        if ($deleteIds !== []) {
            // 衝突により破棄する行は黙って消さず、テーブル・件数・対象IDをログに残す（気づけるように）。
            Log::info('model:dedup repoint drop', [
                'table' => $table,
                'canonical' => $canonicalId,
                'count' => count($deleteIds),
                'ids' => $deleteIds,
            ]);
            DB::table($table)->whereIn('id', $deleteIds)->delete();
        }
        DB::table($table)->whereIn('bike_model_id', $dupeIds)->update(['bike_model_id' => $canonicalId]);
    }

    /**
     * survivor が slug を持たない場合、dupe の clean slug を譲渡（unique(mfr_id,slug)回避のため先に空ける）。
     * `-N`（slug生成の連番衝突回避サフィックス）が付かない slug を優先。どの dupe も無ければ何もしない
     * （= slug 無のまま。後で slug:generate-missing が KANA 辞書で付与可能）。
     */
    private function assignSurvivorSlug(BikeModel $canonical, Collection $dupes): void
    {
        if (! empty($canonical->slug)) {
            return;
        }
        $donor = $dupes->filter(fn (BikeModel $m) => ! empty($m->slug))
            ->sortBy(fn (BikeModel $m) => preg_match('/-\d+$/', (string) $m->slug) ? 1 : 0)
            ->first();
        if (! $donor) {
            return;
        }

        DB::table('bike_models')->where('id', $donor->id)->update(['slug' => null]);
        DB::table('bike_models')->where('id', $canonical->id)->update(['slug' => $donor->slug]);
        $canonical->slug = $donor->slug; // 後続の seo_url 用に反映
    }

    // ───────────────────────── レポート（read-only） ─────────────────────────

    private function printReport(Collection $evaluated): void
    {
        $autoGroups = $evaluated->where('auto', true)->values();
        $manualGroups = $evaluated->where('auto', false)->values();

        $this->newLine();
        $this->line('===== model:dedup レポート（read-only・DB変更なし）=====');
        $this->line('重複グループ          : ' . $evaluated->count());
        $this->line('  auto-merge 可        : ' . $autoGroups->count());
        $this->line('  manual review 必要   : ' . $manualGroups->count());

        $this->reportMakerDuplicates();

        $autoDupeIds = $autoGroups->flatMap(
            fn ($e) => $e['members']->reject(fn (BikeModel $m) => $m->id === $e['canonical']->id)->pluck('id')
        )->all();
        $this->reportBlastRadius($autoDupeIds);

        $limit = (int) $this->option('limit');
        $this->newLine();
        $this->line("----- グループ詳細（在庫合計の多い順・上位{$limit}）-----");
        foreach ($evaluated->take($limit) as $e) {
            $this->printGroupPlan($e, $e['members']->reject(fn (BikeModel $m) => $m->id === $e['canonical']->id));
        }

        $this->newLine();
        $this->warn('※ 統合するには --execute --i-have-a-backup（＋グループ毎の手動承認）。実行前に必ず DB バックアップ。');
    }

    private function printGroupPlan(array $e, Collection $dupes): void
    {
        $tag = $e['auto'] ? 'AUTO ' : 'MANUAL';
        $reason = $e['reasons'] ? ' ['.implode(',', $e['reasons']).']' : '';
        $note = ! empty($e['notes']) ? ' {'.implode(',', $e['notes']).'}' : '';
        $this->newLine();
        $this->line("[{$tag}] 在庫合計 {$e['total_stock']}台{$reason}{$note}");
        foreach ($e['members']->sortByDesc('listings_count') as $m) {
            $mark = $m->id === $e['canonical']->id ? ' ★canonical' : '';
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
        foreach ($dupes as $g) {
            $this->line('    ' . $g->map(fn ($m) => "id={$m->id}:{$m->name}")->implode(' / '));
        }
    }

    private function reportBlastRadius(array $dupeIds): void
    {
        $this->newLine();
        $this->line('----- 付け替え影響（auto対象 dupe ' . count($dupeIds) . '件が参照される行数）-----');
        if ($dupeIds === []) {
            $this->line('    （対象なし）');

            return;
        }

        $singleCol = [
            'listings', 'reviews', 'my_bikes', 'push_subscriptions', 'bike_model_market_stats',
            'market_price_logs', 'bike_model_videos', 'bike_news', 'bike_model_identifiers',
        ];
        foreach ($singleCol as $table) {
            $n = DB::table($table)->whereIn('bike_model_id', $dupeIds)->count();
            $this->line(sprintf('    %-26s %d', "{$table}.bike_model_id", $n));
        }
        $seo = DB::table('seo_compares')
            ->whereIn('model1_id', $dupeIds)
            ->orWhereIn('model2_id', $dupeIds)
            ->count();
        $this->line(sprintf('    %-26s %d', 'seo_compares.model1/2_id', $seo));
    }

    // ───────────────────────── 共通 ─────────────────────────

    private function normalizeKey(string $name): string
    {
        $s = mb_convert_kana($name, 'as');
        $s = preg_replace('/[\s　]+/u', '', $s) ?? $s;
        // 区切り記号の有無で別グループにならないよう、ダッシュ類（ハイフン/マイナス/全角ハイフン/各種ダッシュ）と
        // 中黒（・/半角･）を除去する。例:「タクト・ベーシック」＝「タクトベーシック」、「v−ストローム250」＝「vストローム250」。
        // ★ U+30FC（長音符「ー」）は絶対に含めない。含めると「モンキー」が「モンキ」になり無関係な車種が統合される。
        $s = preg_replace('/[\x{002D}\x{2010}-\x{2015}\x{2212}\x{FF0D}\x{30FB}\x{FF65}]/u', '', $s) ?? $s;

        return mb_strtolower($s);
    }

    private function pickCanonical(Collection $members): BikeModel
    {
        return $members->sort(function (BikeModel $a, BikeModel $b) {
            if ($a->listings_count !== $b->listings_count) {
                return $b->listings_count <=> $a->listings_count;
            }
            $sa = $this->specScore($a);
            $sb = $this->specScore($b);
            if ($sa !== $sb) {
                return $sb <=> $sa;
            }

            return $a->id <=> $b->id;
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
}
