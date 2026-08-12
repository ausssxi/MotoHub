<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BikeModel;
use App\Services\Parts\FitmentTextExtractor;
use App\Services\Parts\PartsCodeExtractor;
use App\Services\Parts\ProductSearchService;
use Illuminate\Console\Command;

/**
 * 「商品説明文から品番と適合型式を抽出する」方式の歩留まりを測る調査コマンド（読み取り専用）。
 *
 * model_fitments は217行・52車種（全4,954車種の1%）しか埋まっておらず、手作業では
 * 上位200車種を埋めても在庫全体の58%にしか届かない。そこで楽天の商品説明文に
 *   【適合車種】Vストローム250（V-Strom250）【適合型式】2BK-DS11A【適合年式】17年
 * のように品番と型式がセットで書かれている事実を利用できないかを測る。
 *
 * ■ このコマンドは DB に一切書き込まない。標準出力に出すだけ。
 *   DB を読むのは車種名の解決（bike_models の SELECT）のみ。
 *
 * ■ 採用条件（誤ったデータを作らないことを最優先にする）
 *   1. 【適合型式】ラベルがあること。年式や車台番号から型式を推測しない。
 *      実測でラベルを持つ商品は正しい適合を書いており、持たない商品は汎用品だった。
 *      ラベルの要求が、そのまま汎用品の除外フィルタになる。
 *   2. 【適合車種】が対象車種名と完全一致すること。前方一致では通さない
 *      （「Vストローム250SX」が「Vストローム250」として混入するため）。
 *   3. 上を満たさないものは採用せず、理由別に計上する。
 *
 * 抽出パターンは App\Services\Parts\FitmentTextExtractor に置いた（後の取込コマンドと共用するため）。
 * 品番の抽出は既存の PartsCodeExtractor をそのまま使う（新しい実装は書かない）。
 *
 * ■ 測定対象は楽天のみ。
 *   説明文（itemCaption）が返ることが実証済みで、genreId=200305（バイク用品）の
 *   ジャンル絞りも効いているため。Yahoo は説明文を返す証跡が無く、ジャンル絞りも無いので除外する。
 *
 * 使い方:
 *   php artisan fitment:probe                             … 集計（採用率と 型式→品番 の一致数）
 *   php artisan fitment:probe --dump                      … 生の説明文も出す
 *   php artisan fitment:probe --model=レブル250 --task=battery
 */
final class FitmentProbe extends Command
{
    protected $signature = 'fitment:probe
        {--model= : 対象車種名（既定: Vストローム250）}
        {--task= : oil-filter|battery|plug|chain（未指定は全部）}
        {--limit=20 : 1タスクあたりの取得商品数}
        {--sleep=2 : タスク間の待機秒数（楽天APIのレート制限を避けるため）}
        {--chain-survey : chain タスクで、車種名一致・サイズ・リンク数の内訳を測る（抽出はしない）}
        {--dump : 取得した商品名と説明文の先頭を生で出力する}';

    protected $description = '商品説明文から品番・適合型式を抽出できるかの歩留まりを測る（読み取り専用・DB書き込みなし）';

    /** 既定の対象車種。 */
    private const DEFAULT_MODEL = 'Vストローム250';

    /** --dump で表示する説明文の文字数。 */
    private const DUMP_CHARS = 400;

    /** 不一致だった車種名の実例を出す上限。 */
    private const MISMATCH_SAMPLES = 10;

    /**
     * タスク → 検索語に足す部品名。検索クエリは「車種名 + 部品名」で組み立てる。
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
        $sleep = max(0, (int) $this->option('sleep'));
        $requested = trim((string) ($this->option('model') ?: self::DEFAULT_MODEL));
        $modelName = $this->resolveModelName($requested);

        $this->newLine();
        $this->line('==== fitment:probe（読み取り専用・DBへは書き込みません）====');
        $this->line("対象車種: {$modelName}");
        $this->line('対象タスク: '.implode(', ', array_keys($tasks)));
        $this->line("1タスクあたりの取得数: {$limit}（楽天のみを集計） / タスク間の待機: {$sleep}秒");

        // 全タスク通算の集計
        $items = 0;
        $withLabel = 0;      // 【適合型式】ラベルがあった（書式A）
        $nameMatched = 0;    // 車種名が厳密一致した
        $adopted = 0;        // 最終的に採用した
        $adoptedA = 0;       // 書式A（【適合型式】ラベル）で採用
        $adoptedB = 0;       // 書式B（NGK形式・車種名の直後が型式）で採用
        // 採用できたのに品番が取れなかった数。下の「型式→品番」表に出てこない採用がこれ。
        // 例: NGKのプラグ品番 CR8E は4文字で、PartsCodeExtractor の最小6文字に満たず落ちる。
        $adoptedWithoutPart = 0;
        $apiFailures = [];   // タスク名 => エラー内容（0件と取得失敗を区別するため）
        $reject = [
            'no_label' => 0,
            'name_mismatch' => 0,
            'undecidable' => 0,
            'fitment_table' => 0,     // 適合表の埋め込みと判断して書式Bを見送った
            'title_unconfirmed' => 0, // 説明文では取れたがタイトルで裏が取れなかった
        ];

        /** @var array<string, int> "型式\t品番" => 何商品が同じことを書いているか */
        $pairs = [];
        /** @var array<string, int> "型式\t純正品番" => 同上 */
        $oemPairs = [];
        /** @var array<int, string> 不一致だった車種名の実例 */
        $mismatchSamples = [];

        $firstTask = true;

        foreach ($tasks as $task => $partName) {
            // 自分からレート制限に当たりに行かない。タスクごとに楽天+Yahooへ1回ずつ投げるため、
            // その間隔を空ける（2026-08-12 の実測でバッテリーが429を受けて0件になった）。
            if (! $firstTask && $sleep > 0) {
                sleep($sleep);
            }
            $firstTask = false;

            $query = "{$modelName} {$partName}";

            $this->newLine();
            $this->line('────────────────────────────────');
            $this->line("[{$task}]");
            $this->line("  検索クエリ: 「{$query}」");

            $results = $service->searchProducts($query, $limit, true);
            $errors = $service->lastErrors();
            $rakuten = array_values(array_filter($results, fn (array $i): bool => ($i['mall'] ?? '') === 'rakuten'));
            $yahooCount = count($results) - count($rakuten);

            // 「0件」と「取得失敗」を必ず区別する。楽天が429を返した回を「0件」と読むと、
            // 検索語が悪いのだと誤解して無駄な調整をすることになる。
            if (isset($errors['rakuten'])) {
                $apiFailures[$task] = $errors['rakuten'];
                $this->error("  楽天APIの取得に失敗しました（{$errors['rakuten']}）。");
                $this->error('  これは「該当0件」ではありません。検索語ではなくAPI側（レート制限・障害）を疑ってください。');
                $this->line('  --sleep を増やして時間をおいて再実行してください。');

                continue;
            }

            $this->line('  取得商品数（楽天）: '.count($rakuten)
                .($yahooCount > 0 ? "（Yahoo {$yahooCount}件は集計対象外）" : '')
                .(isset($errors['yahoo']) ? "（Yahooは取得失敗: {$errors['yahoo']}）" : ''));

            if ($rakuten === []) {
                $this->warn('  該当0件でした（APIは正常応答）。検索語を見直す余地があります。');

                continue;
            }

            // チェーンの実態測定モード。抽出（採用）は行わず、内訳を数えて表示するだけ。
            if ($task === 'chain' && $this->option('chain-survey')) {
                $this->chainSurvey($rakuten, $modelName);

                continue;
            }

            $taskAdopted = 0;
            $taskWithLabel = 0;

            foreach ($rakuten as $i => $item) {
                $name = (string) ($item['name'] ?? '');
                $description = (string) ($item['description'] ?? '');

                $items++;

                // 品番は既存の PartsCodeExtractor を再利用（itemName + itemCaption から抽出）
                $codes = PartsCodeExtractor::extract($name, $description);
                $partNumber = $codes['partNumber'];

                $hasLabel = FitmentTextExtractor::hasFrameCodeLabel($description);
                $fitNames = FitmentTextExtractor::fitmentModelNames($description);
                $frameCodes = FitmentTextExtractor::frameCodes($description);
                $oemNumbers = FitmentTextExtractor::oemPartNumbers($description);

                // ── 採否の判定 ──
                // 書式A（【適合型式】ラベル）を先に見て、満たさなければ書式B（NGK形式）を試す。
                // 書式Bは「対象車種名の直後の語が型式の形か」しか見ないため、
                // 一致した時点で車種名の完全一致も成立している（SXは語境界で弾かれる）。
                $verdict = null;
                $formatAOk = $hasLabel
                    && $fitNames !== null && $fitNames !== []
                    && FitmentTextExtractor::matchesModel($fitNames, $modelName)
                    && $frameCodes !== [];

                if ($hasLabel) {
                    $taskWithLabel++;
                    $withLabel++;
                }

                // ── 書式B の判定 ──
                // 書式Bの根拠は「NGKの商品はタイトルにも説明文にも同じ形で車種名＋型式が出る」
                // という観測だった。それを条件として明示する。
                //   (a) 説明文が適合表の埋め込みでないこと（無関係な車種の行を拾わないため）
                //   (b) 説明文とタイトルの両方から同じ型式が取れること
                // 片方だけで採ると、別車種向け商品に貼られた適合表の1行を拾って
                // 「DS12E → MR7E-9」のような誤った組を作る（2026-08-12 に実際に発生）。
                $isFitmentTable = false;
                $rawDescCodes = [];
                $titleCodes = [];
                $formatBCodes = [];

                if (! $formatAOk) {
                    $isFitmentTable = FitmentTextExtractor::looksLikeFitmentTable($description);
                    $rawDescCodes = FitmentTextExtractor::frameCodesAfterModelName($description, $modelName);
                    $titleCodes = FitmentTextExtractor::frameCodesAfterModelName($name, $modelName);

                    if (! $isFitmentTable) {
                        // タイトルと説明文の両方に現れた型式だけを採る
                        $formatBCodes = $this->intersectFrameCodes($rawDescCodes, $titleCodes);
                    }
                }

                if ($formatAOk) {
                    $nameMatched++;
                    $adopted++;
                    $adoptedA++;
                    $taskAdopted++;
                    $verdict = '採用（書式A: 【適合型式】ラベル）';
                    $this->countPairs($pairs, $oemPairs, $frameCodes, $partNumber, $oemNumbers, $name);
                    if ($partNumber === null) {
                        $adoptedWithoutPart++;
                    }
                } elseif ($formatBCodes !== []) {
                    $nameMatched++;
                    $adopted++;
                    $adoptedB++;
                    $taskAdopted++;
                    $verdict = '採用（書式B: タイトルと説明文の両方で一致）';
                    // 書式Bには純正品番ラベルが無いのが実データの傾向。あれば拾うが、無くても採用する。
                    $this->countPairs($pairs, $oemPairs, $formatBCodes, $partNumber, $oemNumbers, $name);
                    if ($partNumber === null) {
                        $adoptedWithoutPart++;
                    }
                } elseif ($isFitmentTable && $rawDescCodes !== []) {
                    // 適合表を貼った商品。型式自体は取れるが、それは商品の適合ではなく表の1行。
                    $reject['fitment_table']++;
                    $verdict = '不採用（適合表の埋め込みと判断: 型式が'
                        .count(FitmentTextExtractor::distinctFrameCodeTokens($description)).'種）';
                } elseif ($rawDescCodes !== [] && $titleCodes === []) {
                    $reject['title_unconfirmed']++;
                    $verdict = '不採用（タイトルで裏が取れず）';
                } elseif (! $hasLabel) {
                    // 型式ラベルも無く、車種名の直後も型式ではない＝汎用品の可能性が高い
                    $reject['no_label']++;
                    $verdict = '不採用（型式ラベルなし・書式Bにも該当せず）';
                } elseif ($fitNames === null || $fitNames === []) {
                    $reject['undecidable']++;
                    $verdict = '不採用（適合車種ラベルなし＝判定不能）';
                } elseif (! FitmentTextExtractor::matchesModel($fitNames, $modelName)) {
                    $reject['name_mismatch']++;
                    $verdict = '不採用（車種名が不一致）';
                    if (count($mismatchSamples) < self::MISMATCH_SAMPLES) {
                        $mismatchSamples[] = '「'.implode(' / ', $fitNames).'」 ← '.$this->flatten(mb_substr($name, 0, 60));
                    }
                } else {
                    $reject['undecidable']++;
                    $verdict = '不採用（型式の書式が解釈できない＝判定不能）';
                }

                if ($this->option('dump')) {
                    $this->newLine();
                    $this->line(sprintf('  #%02d %s', $i + 1, $this->flatten(mb_substr($name, 0, 120))));
                    $this->line('      判定: '.$verdict);
                    $this->line('      品番: '.($partNumber ?? '(取れず)')
                        .($codes['jan'] !== null ? " / JAN: {$codes['jan']}" : ''));
                    $this->line('      適合車種: '.($fitNames === null ? '(ラベルなし)' : implode(' / ', $fitNames)));
                    $this->line('      適合型式(書式A): '.($frameCodes === []
                        ? '(なし)'
                        : implode(' / ', array_map(
                            fn (array $c): string => $c['raw'].'[規制'.($c['regulation'] ?? '-').'/型式'.$c['code'].']',
                            $frameCodes
                        ))));
                    // 書式Bは「説明文側 / タイトル側 / 両方一致」を分けて出す（裏取りの成否が見えるように）
                    if (! $formatAOk) {
                        $this->line('      適合型式(書式B): 説明文='.$this->codeList($rawDescCodes)
                            .' / タイトル='.$this->codeList($titleCodes)
                            .' / 両方一致='.$this->codeList($formatBCodes)
                            .($isFitmentTable ? '  ※適合表と判断（型式'
                                .count(FitmentTextExtractor::distinctFrameCodeTokens($description)).'種）' : ''));
                    }
                    $this->line('      純正品番: '.($oemNumbers === [] ? '(なし)' : implode(', ', $oemNumbers)));
                    $this->line('      説明文: '.(trim($description) !== ''
                        ? $this->flatten(mb_substr($description, 0, self::DUMP_CHARS))
                        : '(説明文なし)'));
                }
            }

            $this->newLine();
            $this->line(sprintf(
                '  → 型式ラベルあり %d/%d 件 / 採用 %d/%d 件（%s）',
                $taskWithLabel,
                count($rakuten),
                $taskAdopted,
                count($rakuten),
                $this->percent($taskAdopted, count($rakuten))
            ));
        }

        $this->newLine();
        $this->line('==== 合計 ====');

        if ($apiFailures !== []) {
            $this->error('APIの取得に失敗したタスクがあります（下の集計にはこれらは含まれていません）:');
            foreach ($apiFailures as $task => $reason) {
                $this->error("  {$task}: {$reason}");
            }
            $this->line('  → 該当0件ではありません。時間をおくか --sleep を増やして再実行してください。');
            $this->newLine();
        }

        if ($items === 0) {
            $this->warn('1件も評価できませんでした。');

            return self::SUCCESS;
        }

        $this->line("評価商品数              : {$items}");
        $this->line("型式ラベルがあった(書式A): {$withLabel}（".$this->percent($withLabel, $items).'）');
        $this->line("車種名が厳密一致した    : {$nameMatched}（".$this->percent($nameMatched, $items).'）');
        $this->line("最終的に採用した        : {$adopted}（".$this->percent($adopted, $items).'）');
        $this->line("  うち書式A（ラベル）   : {$adoptedA}（".$this->percent($adoptedA, $items).'）');
        $this->line("  うち書式B（NGK形式）  : {$adoptedB}（".$this->percent($adoptedB, $items).'）');
        if ($adoptedWithoutPart > 0) {
            $this->line("  うち品番が取れず      : {$adoptedWithoutPart}（型式は取れたが下の「型式→品番」表には出ない）");
        }

        $this->newLine();
        $this->line('---- 不採用の理由 ----');
        $this->line("  型式ラベルなし（汎用品の可能性）: {$reject['no_label']}");
        $this->line("  車種名が不一致                  : {$reject['name_mismatch']}");
        $this->line("  適合表の埋め込みと判断          : {$reject['fitment_table']}");
        $this->line("  タイトルで裏が取れず            : {$reject['title_unconfirmed']}");
        $this->line("  判定不能                        : {$reject['undecidable']}");

        $this->printPairs('---- 型式 → 品番（メーカー品番）と一致数 ----', $pairs);
        $this->printPairs('---- 型式 → 純正品番 と一致数 ----', $oemPairs);

        if ($mismatchSamples !== []) {
            $this->newLine();
            $this->line('---- 車種名が不一致だった実例（最大'.self::MISMATCH_SAMPLES.'件）----');
            foreach ($mismatchSamples as $s) {
                $this->line('  '.$s);
            }
        }

        $this->newLine();
        $this->comment('※ 書式C（該当純正品番＋参考適合車種が年式のみ）は未対応です。型式が書かれておらず推測になるため。');
        $this->comment('※ DBには一切書き込んでいません。採用した組をどう保存するかは次段の判断です。');

        return self::SUCCESS;
    }

    /**
     * チェーンの実態測定（--chain-survey）。
     *
     * チェーンは商品データに型式が一切なく採用0件だった。一方でタイトルには
     * 「520 116L」のようなサイズとリンク数が出る。スペック型として保存する価値があるかを
     * 判断するため、まず「車種名の厳密一致を通る商品がそもそも何件あるか」を測る。
     *
     * ここでは抽出（採用）は一切行わない。数えて表示するだけ。
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    private function chainSurvey(array $items, string $modelName): void
    {
        $this->newLine();
        $this->line('  ---- チェーン実態測定（--chain-survey・採用はしません）----');

        $nameHit = 0;
        $sizeHit = 0;
        $linkHit = 0;
        $allHit = 0;

        /** @var array<string, array{count: int, title: string}> $combos "サイズ\tリンク数" => 件数 */
        $combos = [];

        foreach ($items as $item) {
            $name = (string) ($item['name'] ?? '');
            $description = (string) ($item['description'] ?? '');
            $haystack = $name.' '.$description;

            // 車種名は書式Bと同じ「語として含まれるか」で判定する（250SX を250として数えない）
            $hasName = FitmentTextExtractor::containsModelName($haystack, $modelName);
            $size = $this->chainSize($haystack);
            $links = $this->chainLinks($haystack);

            if ($hasName) {
                $nameHit++;
            }
            if ($size !== null) {
                $sizeHit++;
            }
            if ($links !== null) {
                $linkHit++;
            }

            if ($hasName && $size !== null && $links !== null) {
                $allHit++;
                $key = $size."\t".$links;
                if (! isset($combos[$key])) {
                    $combos[$key] = ['count' => 0, 'title' => $this->flatten(mb_substr($name, 0, 60))];
                }
                $combos[$key]['count']++;
            }
        }

        $total = count($items);
        $this->line("    車種名が厳密一致    : {$nameHit}/{$total}（".$this->percent($nameHit, $total).'）');
        $this->line("    サイズが取れた      : {$sizeHit}/{$total}（".$this->percent($sizeHit, $total).'）');
        $this->line("    リンク数が取れた    : {$linkHit}/{$total}（".$this->percent($linkHit, $total).'）');
        $this->line("    3つすべて揃った     : {$allHit}/{$total}（".$this->percent($allHit, $total).'）');

        if ($combos === []) {
            $this->line('    → 3つ揃った商品はありませんでした。');

            return;
        }

        $this->newLine();
        $this->line('    ---- サイズ・リンク数・一致数 ----');
        uasort($combos, fn (array $x, array $y): int => $y['count'] <=> $x['count']);
        foreach ($combos as $key => $row) {
            [$size, $links] = explode("\t", $key, 2);
            $this->line(sprintf('    サイズ %-5s / %-6s  %d商品が一致', $size, $links.'L', $row['count']));
            $this->line('        根拠: '.$row['title']);
        }
    }

    /**
     * チェーンサイズらしき3桁（測定用。採用ロジックには使わない）。
     * 任意の3桁ではなく実在する規格だけを見る（年式・価格を拾わないため）。
     */
    private function chainSize(string $text): ?string
    {
        $sizes = ['415', '420', '425', '428', '520', '525', '530', '532', '630'];
        $normalized = FitmentTextExtractor::normalize($text);

        foreach ($sizes as $size) {
            if (preg_match('/(?<!\d)'.$size.'(?!\d)/u', $normalized) === 1) {
                return $size;
            }
        }

        return null;
    }

    /**
     * リンク数らしき値（測定用）。「116L」「116リンク」の形。
     */
    private function chainLinks(string $text): ?string
    {
        $normalized = FitmentTextExtractor::normalize($text);

        if (preg_match('/(?<!\d)(\d{2,3})(?:L|リンク)(?![A-Z0-9])/u', $normalized, $m) === 1) {
            return $m[1];
        }

        return null;
    }

    /**
     * 型式リストを表示用の短い文字列にする。
     *
     * @param  array<int, array{raw: string, regulation: string|null, code: string}>  $codes
     */
    private function codeList(array $codes): string
    {
        return $codes === [] ? '(なし)' : implode(',', array_column($codes, 'code'));
    }

    /**
     * タイトル側と説明文側の型式のうち、両方に現れたものだけを返す。
     *
     * @param  array<int, array{raw: string, regulation: string|null, code: string}>  $a
     * @param  array<int, array{raw: string, regulation: string|null, code: string}>  $b
     * @return array<int, array{raw: string, regulation: string|null, code: string}>
     */
    private function intersectFrameCodes(array $a, array $b): array
    {
        $bCodes = array_column($b, 'code');

        return array_values(array_filter($a, fn (array $c): bool => in_array($c['code'], $bCodes, true)));
    }

    /**
     * 「型式 → 品番」「型式 → 純正品番」の組を数える（同じことを何商品が書いているか）。
     *
     * 併せて、最初に根拠となった商品タイトルを1つだけ保持する（--dump 無しでも目視検証できるように）。
     *
     * @param  array<string, array{count: int, title: string}>  $pairs
     * @param  array<string, array{count: int, title: string}>  $oemPairs
     * @param  array<int, array{raw: string, regulation: string|null, code: string}>  $frameCodes
     * @param  array<int, string>  $oemNumbers
     */
    private function countPairs(array &$pairs, array &$oemPairs, array $frameCodes, ?string $partNumber, array $oemNumbers, string $sourceTitle): void
    {
        foreach ($frameCodes as $fc) {
            if ($partNumber !== null) {
                $this->addPair($pairs, $fc['code']."\t".$partNumber, $sourceTitle);
            }
            foreach ($oemNumbers as $oem) {
                $this->addPair($oemPairs, $fc['code']."\t".$oem, $sourceTitle);
            }
        }
    }

    /**
     * @param  array<string, array{count: int, title: string}>  $table
     */
    private function addPair(array &$table, string $key, string $sourceTitle): void
    {
        if (! isset($table[$key])) {
            // 根拠は先頭の1件だけを残す（一覧が縦に伸びないように）
            $table[$key] = ['count' => 0, 'title' => $this->flatten(mb_substr($sourceTitle, 0, 60))];
        }
        $table[$key]['count']++;
    }

    /**
     * 「型式 → 品番」の組を一致数の多い順に出す。根拠となった商品タイトルを1件併記する。
     *
     * @param  array<string, array{count: int, title: string}>  $pairs
     */
    private function printPairs(string $title, array $pairs): void
    {
        $this->newLine();
        $this->line($title);

        if ($pairs === []) {
            $this->line('  (なし)');

            return;
        }

        uasort($pairs, fn (array $x, array $y): int => $y['count'] <=> $x['count']);
        foreach ($pairs as $key => $row) {
            [$code, $part] = explode("\t", $key, 2);
            $this->line(sprintf('  %-10s → %-20s  %d商品が一致', $code, $part, $row['count']));
            $this->line('      根拠: '.$row['title']);
        }
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
     * 見つからなければ引数をそのまま使い、警告する（検索自体は続行できる）。
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
