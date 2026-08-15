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
    protected $signature = 'models:detect-name-dupes';

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
}
