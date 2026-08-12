<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BikeModel;
use App\Services\Parts\PartsCodeExtractor;
use App\Services\Parts\ProductSearchService;
use Illuminate\Console\Command;

/**
 * 「商品説明文から品番と適合型式を抽出する」方式の歩留まりを測る調査コマンド（読み取り専用）。
 *
 * model_fitments は217行・52車種（全4,954車種の1%）しか埋まっておらず、手作業では
 * 上位200車種を埋めても在庫全体の58%にしか届かない。そこで楽天の商品説明文に
 *   ■メーカー品番 15412-MGS-D21
 *   ■適合車種 スーパーカブ50（AA07/AA08/AA09） …
 * のように品番と型式がセットで書かれている事実を利用できないかを測る。
 *
 * ■ このコマンドは DB に一切書き込まない。標準出力に出すだけ。
 *   DB を読むのは車種名の解決（bike_models の SELECT）のみ。
 *
 * ■ 第1版の主目的は --dump（生の説明文を目で見ること）。
 *   型式コードの抽出は「実データを見てからパターンを決める」ため、ここでは実装しない。
 *   品番の抽出は既存の PartsCodeExtractor をそのまま使う（新しい実装は書かない）。
 *
 * ■ 測定対象は楽天のみ。
 *   説明文（itemCaption）が返ることが実証済みで、genreId=200305（バイク用品）の
 *   ジャンル絞りも効いているため。Yahoo は説明文を返す証跡が無く、ジャンル絞りも無いので除外する。
 *   （ProductSearchService は楽天+Yahooをまとめて返すので、mall で絞ってから数える）
 *
 * 使い方:
 *   php artisan fitment:probe --dump                      … 既定車種の全タスクの説明文を見る
 *   php artisan fitment:probe --model=レブル250 --task=oil-filter --dump
 *   php artisan fitment:probe --limit=30                  … 集計だけ（品番の取得率）
 */
final class FitmentProbe extends Command
{
    protected $signature = 'fitment:probe
        {--model= : 対象車種名（既定: Vストローム250）}
        {--task= : oil-filter|battery|plug|chain（未指定は全部）}
        {--limit=20 : 1タスクあたりの取得商品数}
        {--dump : 取得した商品名と説明文の先頭を生で出力する（第1版の主目的）}';

    protected $description = '商品説明文から品番・適合型式を抽出できるかの歩留まりを測る（読み取り専用・DB書き込みなし）';

    /** 既定の対象車種。 */
    private const DEFAULT_MODEL = 'Vストローム250';

    /** --dump で表示する説明文の文字数。 */
    private const DUMP_CHARS = 400;

    /**
     * タスク → 検索語に足す部品名。
     * 検索クエリは「車種名 + 部品名」で組み立てる。
     *
     * @var array<string, string>
     */
    private const TASKS = [
        'oil-filter' => 'オイルフィルター',
        'battery' => 'バッテリー',
        'plug' => 'スパークプラグ',
        'chain' => 'チェーン',
    ];

    public function handle(ProductSearchService $service): int
    {
        $tasks = $this->resolveTasks();
        if ($tasks === null) {
            return self::FAILURE;
        }

        $limit = max(1, (int) $this->option('limit'));
        $requested = trim((string) ($this->option('model') ?: self::DEFAULT_MODEL));

        // 車種名を bike_models 上の実際の表記に寄せる（SELECT のみ）。
        // 表記揺れがあっても検索語が的外れにならないようにするのが目的。
        $modelName = $this->resolveModelName($requested);

        $this->newLine();
        $this->line('==== fitment:probe（読み取り専用・DBへは書き込みません）====');
        $this->line("対象車種: {$modelName}");
        $this->line('対象タスク: '.implode(', ', array_keys($tasks)));
        $this->line("1タスクあたりの取得数: {$limit}（楽天のみを集計）");
        $this->newLine();

        $totalItems = 0;
        $totalWithDescription = 0;
        $totalWithPartNumber = 0;
        $totalWithBoth = 0;

        foreach ($tasks as $task => $partName) {
            $query = "{$modelName} {$partName}";

            $this->line('────────────────────────────────');
            $this->line("[{$task}]");
            // 実際に投げた検索クエリは必ず出す（歩留まりが低いとき検索語の妥当性を疑えるように）
            $this->line("  検索クエリ: 「{$query}」");

            // $withDescription=true。楽天の itemCaption が 'description' で返る。
            $results = $service->searchProducts($query, $limit, true);

            // 測定対象は楽天のみ（Yahoo は説明文を返す証跡が無いため除外）
            $items = array_values(array_filter($results, fn (array $i): bool => ($i['mall'] ?? '') === 'rakuten'));
            $yahooCount = count($results) - count($items);

            $this->line('  取得商品数（楽天）: '.count($items)
                .($yahooCount > 0 ? "（Yahoo {$yahooCount}件は集計対象外）" : ''));

            if ($items === []) {
                $this->warn('  0件でした。検索語かAPIの状態を確認してください。');
                $this->newLine();

                continue;
            }

            $withDescription = 0;
            $withPartNumber = 0;
            $withBoth = 0;

            foreach ($items as $i => $item) {
                $name = (string) ($item['name'] ?? '');
                $description = (string) ($item['description'] ?? '');
                $hasDescription = trim($description) !== '';

                // 品番は既存の PartsCodeExtractor を再利用（itemName + itemCaption から抽出）
                $codes = PartsCodeExtractor::extract($name, $description);
                $partNumber = $codes['partNumber'];

                if ($hasDescription) {
                    $withDescription++;
                }
                if ($partNumber !== null) {
                    $withPartNumber++;
                }
                if ($hasDescription && $partNumber !== null) {
                    $withBoth++;
                }

                if ($this->option('dump')) {
                    $this->newLine();
                    $this->line(sprintf('  #%02d %s', $i + 1, $this->flatten($name)));
                    $this->line('      品番: '.($partNumber ?? '(取れず)')
                        .($codes['jan'] !== null ? " / JAN: {$codes['jan']}" : ''));
                    $this->line('      説明文: '.($hasDescription
                        ? $this->flatten(mb_substr($description, 0, self::DUMP_CHARS))
                        : '(説明文なし)'));
                }
            }

            $this->newLine();
            $this->line(sprintf(
                '  → 説明文あり %d/%d 件 / 品番が取れた %d/%d 件 / 両方そろった %d/%d 件（%s）',
                $withDescription,
                count($items),
                $withPartNumber,
                count($items),
                $withBoth,
                count($items),
                $this->percent($withBoth, count($items))
            ));
            $this->newLine();

            $totalItems += count($items);
            $totalWithDescription += $withDescription;
            $totalWithPartNumber += $withPartNumber;
            $totalWithBoth += $withBoth;
        }

        $this->line('==== 合計 ====');
        if ($totalItems === 0) {
            $this->warn('1件も取得できませんでした。');

            return self::SUCCESS;
        }

        $this->line("評価商品数        : {$totalItems}");
        $this->line("説明文あり        : {$totalWithDescription}（".$this->percent($totalWithDescription, $totalItems).'）');
        $this->line("品番が取れた      : {$totalWithPartNumber}（".$this->percent($totalWithPartNumber, $totalItems).'）');
        $this->line("説明文＋品番の両方: {$totalWithBoth}（".$this->percent($totalWithBoth, $totalItems).'）');
        $this->newLine();
        $this->comment('型式コードの抽出は第1版では未実装です。--dump の出力から「適合車種」の');
        $this->comment('書式（カッコの種類・区切り文字・ラベルの有無）を確認してからパターンを決めます。');

        return self::SUCCESS;
    }

    /**
     * --task を解決する。不正値は有効なタスク名を提示して null を返す。
     *
     * @return array<string, string>|null
     */
    private function resolveTasks(): ?array
    {
        $task = trim((string) ($this->option('task') ?? ''));

        if ($task === '') {
            return self::TASKS;
        }

        if (! isset(self::TASKS[$task])) {
            $this->error("--task の値が不正です: {$task}");
            $this->line('有効な値: '.implode(' | ', array_keys(self::TASKS)));

            return null;
        }

        return [$task => self::TASKS[$task]];
    }

    /**
     * 与えられた車種名を bike_models 上の実際の表記へ寄せる（SELECT のみ・書き込みなし）。
     *
     * 完全一致 → 部分一致（在庫の多い順）の順で探す。SeoCompareSeeder::findModel と同じ考え方。
     * 見つからなければ引数をそのまま使い、候補を提示して警告する（検索自体は続行できる）。
     */
    private function resolveModelName(string $requested): string
    {
        $exact = BikeModel::where('name', $requested)->first(['id', 'name']);
        if ($exact !== null) {
            $this->line("車種名の解決: 「{$requested}」= bike_models.name に完全一致（id={$exact->id}）");

            return (string) $exact->name;
        }

        $partial = BikeModel::where('name', 'like', "%{$requested}%")
            ->withCount(['listings' => fn ($q) => $q->where('is_sold_out', false)])
            ->orderByDesc('listings_count')
            ->first(['id', 'name']);

        if ($partial !== null) {
            $this->warn("車種名の解決: 「{$requested}」は完全一致せず。部分一致で「{$partial->name}」(id={$partial->id}) を採用します。");

            return (string) $partial->name;
        }

        $this->warn("車種名の解決: 「{$requested}」は bike_models に見つかりませんでした。文字列のまま検索します。");

        return $requested;
    }

    /**
     * 目視用に1行へ潰す（改行・連続空白・HTMLエンティティを整える）。
     */
    private function flatten(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    private function percent(int $part, int $whole): string
    {
        if ($whole === 0) {
            return '0.0%';
        }

        return sprintf('%.1f%%', $part / $whole * 100);
    }
}
