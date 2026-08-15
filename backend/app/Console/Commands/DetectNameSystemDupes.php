<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BikeModel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Transliterator;

/**
 * 「カタカナ表記」と「ローマ字表記」に分かれた同一車種の重複候補を洗い出す（読み取り専用・出力のみ）。
 *
 * model:dedup は空白・ダッシュ・中黒・全角半角の違いは吸収するが、表記体系そのものが違う
 * （例: id 1116「ハヤブサ」slug=hayabusa-1300 と id 3755「hayabusa」slug=hayabusa）ケースは拾えない。
 * これを把握するための調査コマンド。
 *
 * ★書き込みは一切しない（UPDATE/INSERT/DELETE も --execute も無い）。マージ判断は人間が別途行う。
 * ★カタカナ→ローマ字の変換は新しい辞書を書かず、既存 GenerateMissingSlugs::improvedSlug
 *   （KANA_WORD_MAP 辞書＋Transliterator）を reflection でそのまま再利用する（＝辞書も変換も単一の正本）。
 *   GenerateMissingSlugs は一切変更しない（挙動を壊さないため）。
 */
final class DetectNameSystemDupes extends Command
{
    protected $signature = 'models:detect-name-dupes
        {--csv : 統合用CSV(canonical_id,dupe_id)だけを標準出力へ出す（人向け一覧は出さない）}
        {--min-listings=0 : 組の在庫合計がこの値未満の組をCSV出力から除外（既定0=全部出す）}';

    protected $description = '同一メーカー内でカタカナ表記とローマ字表記に分かれた同一車種の重複候補を洗い出す（読み取り専用・出力のみ）';

    public function handle(): int
    {
        // 変換は既存 GenerateMissingSlugs::improvedSlug を reflection で再利用（辞書・変換を二重化しない）。
        $transliterator = Transliterator::create('Any-Latin; Latin-ASCII; Lower()');
        if ($transliterator === null) {
            $this->error('Transliterator を初期化できませんでした（intl拡張を確認してください）');

            return self::FAILURE;
        }
        $generator = new GenerateMissingSlugs();
        $improvedSlug = new ReflectionMethod($generator, 'improvedSlug');
        $improvedSlug->setAccessible(true);

        // 比較用キー: 既存変換でローマ字スラッグ（空白・中黒・長音符・全角半角を吸収済み）を得て、
        // ダッシュ類を除いた小文字の連続文字列にする。生成不能は null。
        $matchKey = static function (string $name) use ($generator, $improvedSlug, $transliterator): ?string {
            $slug = $improvedSlug->invoke($generator, $transliterator, $name);

            return $slug === null ? null : str_replace('-', '', $slug);
        };

        // マージ済み（merged_into_id あり）は除外。active 在庫数を付与（SELECT のみ）。
        $models = BikeModel::query()
            ->whereNull('merged_into_id')
            ->withCount(['listings as active_listings_count' => fn ($q) => $q->active()])
            ->get(['id', 'manufacturer_id', 'name', 'slug', 'created_at']);

        // model_fitments の行数を車種ごとに1クエリで取得。
        $fitmentCounts = DB::table('model_fitments')
            ->select('bike_model_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('bike_model_id')
            ->pluck('cnt', 'bike_model_id');

        // メーカーごと → 比較キーごとにグルーピング。同一キーが2件以上なら候補の組。
        $pairs = [];
        foreach ($models->groupBy('manufacturer_id') as $group) {
            $byKey = [];
            foreach ($group as $model) {
                $key = $matchKey((string) $model->name);
                if ($key === null || $key === '') {
                    continue;
                }
                $byKey[$key][] = $model;
            }
            foreach ($byKey as $key => $rows) {
                if (count($rows) < 2) {
                    continue;
                }
                // 在庫の多い順に行を並べる（残す側が上に来やすいように）。
                usort($rows, fn ($a, $b) => (int) $b->active_listings_count <=> (int) $a->active_listings_count);
                $stock = array_sum(array_map(fn ($m) => (int) $m->active_listings_count, $rows));
                $pairs[] = ['key' => $key, 'rows' => $rows, 'stock' => $stock];
            }
        }

        // 在庫合計の多い組から順に。
        usort($pairs, fn ($a, $b) => $b['stock'] <=> $a['stock']);

        // --csv: model:merge-pairs 用の canonical_id,dupe_id だけを標準出力へ出して終了。
        // 人向け一覧（既存の出力形式）は変更せず、--csv 指定時のみ挙動を差し替える。読み取り専用のまま。
        if ($this->option('csv')) {
            $this->emitMergeCsv($pairs, $fitmentCounts);

            return self::SUCCESS;
        }

        // 集計（今回いちばん知りたい3つの数字）は全候補の組を対象にする。
        $totalPairs = count($pairs);
        $totalRecords = array_sum(array_map(fn ($p) => count($p['rows']), $pairs));
        $totalStock = array_sum(array_map(fn ($p) => $p['stock'], $pairs));

        $this->newLine();
        $this->line('==== カタカナ↔ローマ字の表記重複候補（読み取り専用・DB変更なし）====');
        $this->comment('※ 変換は GenerateMissingSlugs の辞書＋Transliterator を再利用。誤検出（別グレード・年式違い等）は混ざる前提です。');
        $this->newLine();

        $zeroStockOmitted = 0;
        foreach ($pairs as $pair) {
            // 在庫が両方（全レコード）0の組は一覧から省く（判断材料にならない）。件数だけ数える。
            if ($pair['stock'] === 0) {
                $zeroStockOmitted++;

                continue;
            }

            $this->line(sprintf('■ key="%s"  在庫合計 %s台  レコード%d件', $pair['key'], number_format($pair['stock']), count($pair['rows'])));
            foreach ($pair['rows'] as $model) {
                $this->line(sprintf(
                    '    id=%-6d 在庫%-6s 適合%-3d slug=%-22s created=%s  name="%s"',
                    $model->id,
                    number_format((int) $model->active_listings_count),
                    (int) ($fitmentCounts[$model->id] ?? 0),
                    $model->slug ?? '(無)',
                    optional($model->created_at)->format('Y-m-d') ?? '-',
                    $model->name,
                ));
            }
            $this->newLine();
        }

        $this->line('==== 集計 ====');
        $this->line(sprintf('  候補の組数            : %d', $totalPairs));
        $this->line(sprintf('  関係するレコード数    : %d', $totalRecords));
        $this->line(sprintf('  巻き込まれる在庫合計  : %s台', number_format($totalStock)));
        $this->line(sprintf('  （うち在庫が全レコード0で一覧から省いた組: %d）', $zeroStockOmitted));

        return self::SUCCESS;
    }

    /**
     * model:merge-pairs が読む canonical_id,dupe_id を標準出力へ出す（1組=1canonical + 残り全dupe）。
     * 在庫合計が --min-listings 未満の組は除外。ヘッダ等は出さず、データ行のみ（merge-pairs へそのまま流せる）。
     *
     * canonical の選定は model:dedup の pickCanonical と同じ考え方の3段:
     *   (1) active 在庫件数が多いほう → (2) 同数なら model_fitments 行数が多いほう → (3) それも同数なら id が小さいほう。
     *   ※pickCanonical の第2段は spec 充実度だが、本コマンドは指示により model_fitments 行数を用いる。
     *
     * @param  array<int, array{key:string, rows:array<int, \App\Models\BikeModel>, stock:int}>  $pairs
     * @param  \Illuminate\Support\Collection<int, int>  $fitmentCounts
     */
    private function emitMergeCsv(array $pairs, $fitmentCounts): void
    {
        $minListings = (int) $this->option('min-listings');

        foreach ($pairs as $pair) {
            if ($pair['stock'] < $minListings) {
                continue; // 組の在庫合計がしきい値未満は出力しない
            }

            $rows = $pair['rows'];
            usort($rows, function ($a, $b) use ($fitmentCounts) {
                // (1) active 在庫件数 desc
                if ((int) $a->active_listings_count !== (int) $b->active_listings_count) {
                    return (int) $b->active_listings_count <=> (int) $a->active_listings_count;
                }
                // (2) model_fitments 行数 desc
                $fa = (int) ($fitmentCounts[$a->id] ?? 0);
                $fb = (int) ($fitmentCounts[$b->id] ?? 0);
                if ($fa !== $fb) {
                    return $fb <=> $fa;
                }
                // (3) id asc
                return (int) $a->id <=> (int) $b->id;
            });

            $canonical = $rows[0];
            foreach (array_slice($rows, 1) as $dupe) {
                // 3件以上が同キーに集まる組でも、選ばれた canonical に対し残り全てを dupe として出す。
                $this->line($canonical->id.','.$dupe->id);
            }
        }
    }
}
