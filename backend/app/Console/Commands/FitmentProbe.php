<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BikeModel;
use App\Models\ModelFitment;
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
        {--sleep=2 : 楽天へのリクエスト1回ごとの待機秒数（--verify の既定は5秒）}
        {--chain-survey : chain タスクで、車種名一致・サイズ・リンク数の内訳を測る（抽出はしない）}
        {--verify : model_fitments の既存データと突き合わせて精度を測る（SELECTのみ・書き込みなし）}
        {--models=10 : --verify で照合する車種数}
        {--frame-widen : 型式パターンを数字3桁まで広げた場合の増分と実例を測る（--verify 併用・診断のみ・採用ロジックは不変）}
        {--dump : 取得した商品名と説明文の先頭を生で出力する}';

    protected $description = '商品説明文から品番・適合型式を抽出できるかの歩留まりを測る（読み取り専用・DB書き込みなし）';

    /** 既定の対象車種。 */
    private const DEFAULT_MODEL = 'Vストローム250';

    /** --dump で表示する説明文の文字数。 */
    private const DUMP_CHARS = 400;

    /** 不一致だった車種名の実例を出す上限。 */
    private const MISMATCH_SAMPLES = 10;

    /**
     * 429（レート制限）を受けたときの再試行の待機秒。指数バックオフで最大3回。
     * 即打ち切りにすると4車種目で測定が終わってしまい（2026-08-12 の実測）、
     * 全体像が分からないため、粘ってから諦める。
     */
    private const RETRY_BACKOFF = [10, 30, 60];

    /** --sleep の既定値（signature と一致させること）。 */
    private const DEFAULT_SLEEP = 2;

    /** --verify のリクエスト間の既定待機秒（通常モードより長め）。車種数×タスク数だけ投げるため。 */
    private const VERIFY_DEFAULT_SLEEP = 5;

    /**
     * --verify の判定。
     *
     * ※ 「不一致」ではなく「食い違い（要確認）」としているのは、既存データを正解と見なせないため。
     *   2026-08-12 の実測で、ジョルノ AF70 は既存 CPR6EA-9 が誤りで抽出 CR7HSA-9 が正しかった。
     *   このモードは精度の測定ではなく、人が確認すべき差分の洗い出しに使う。
     */
    private const VERDICT_MATCH = '一致';

    /**
     * 既存の推奨品番そのものではないが、isSameSeries() で同一系列（他社互換品）と判定できた一致。
     * 実用上は正しい情報を取れているため、食い違いには数えず「一致」の一種として別建てで集計する。
     * 例: 既存 YTX7L-BS に対し抽出 MTX7L-BS/PTX7L-BS（TX7L-BS で一致）。
     */
    private const VERDICT_MATCH_COMPAT = '一致（互換品番）';

    private const VERDICT_CONFLICT = '食い違い（要確認）';

    private const VERDICT_NEW = '新規';

    /**
     * 取りこぼしは原因別に3つへ分ける。各検索の診断値（取得商品数・厳密一致件数）から機械的に決める。
     * 「データが無い」「名前を認識できない」「型式が書かれていない」は打ち手が違うため。
     */
    private const VERDICT_MISSED_NO_ITEMS = '取りこぼし（商品が0件）';

    private const VERDICT_MISSED_NO_NAME = '取りこぼし（車種名を認識できず）';

    private const VERDICT_MISSED_NO_CODE = '取りこぼし（型式が抽出できず）';

    /**
     * 採用の信頼度。除外条件ではなく、後段（書き込み）で優先度を判断するための印。
     *   高(書式A) … 【適合型式】ラベルで明示されている
     *   高(書式B) … タイトルと説明文の両方で同じ型式が取れた
     *   中(書式B) … 説明文だけで取れた
     */
    private const CONFIDENCE_LABEL = '高(書式A:ラベル)';

    private const CONFIDENCE_HIGH = '高(書式B:タイトル+説明文)';

    private const CONFIDENCE_MEDIUM = '中(書式B:説明文のみ)';

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

    /**
     * bike_models.name / display_name を正規化した「名前のリスト（値の配列）」のキャッシュ。
     * null は「未読込」。1回だけ SELECT して使い回す。
     *
     * ★キーではなく値として持つ。連想配列のキーにすると、PHP が「250」のような数字だけの
     *   文字列キーを自動的に int へ変換し、後段で mb_strlen(int) 等が TypeError になるため
     *   （本番で発生）。値の配列なら数字だけの名前でも string のまま保たれる。
     *
     * @var array<int, string>|null
     */
    private ?array $modelNames = null;

    public function handle(ProductSearchService $service): int
    {
        $tasks = $this->resolveTasks();
        if ($tasks === null) {
            return self::FAILURE;
        }

        $limit = max(1, (int) $this->option('limit'));
        $sleep = max(0, (int) $this->option('sleep'));

        if ($this->option('verify')) {
            // --verify は車種数×タスク数だけリクエストが出るため、既定の待機を長くする。
            // --sleep が明示されていればその値を尊重する（既定値と同じ数字を明示した場合も尊重されるよう、
            // 値の比較ではなくコマンドラインに現れたかどうかで判定する）。
            $sleepGiven = $this->input->hasParameterOption('--sleep');

            return $this->verifyMode($service, $tasks, $limit, $sleepGiven ? $sleep : self::VERIFY_DEFAULT_SLEEP);
        }

        $requested = trim((string) ($this->option('model') ?: self::DEFAULT_MODEL));
        [$modelName, $displayName] = $this->resolveModelName($requested);

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
            'fitment_table' => 0, // 適合表の埋め込みと判断して書式Bを見送った
        ];
        /** @var array<string, array<string, array<int, string>>> $byCode タスク → 型式 → 品番群（矛盾検出用） */
        $byCode = [];

        /** @var array<string, int> "型式\t品番" => 何商品が同じことを書いているか */
        $pairs = [];
        /** @var array<string, int> "型式\t純正品番" => 同上 */
        $oemPairs = [];
        /** @var array<int, string> 不一致だった車種名の実例 */
        $mismatchSamples = [];

        foreach ($tasks as $task => $partName) {
            $query = "{$modelName} {$partName}";

            $this->newLine();
            $this->line('────────────────────────────────');
            $this->line("[{$task}]");
            $this->line("  検索クエリ: 「{$query}」");

            // 待機とリトライは searchWithRetry に集約（リクエスト単位で $sleep を挟む）
            $fetched = $this->searchWithRetry($service, $query, $limit, $sleep, false);

            // 「0件」と「取得失敗」を必ず区別する。楽天が429を返した回を「0件」と読むと、
            // 検索語が悪いのだと誤解して無駄な調整をすることになる。
            if ($fetched['error'] !== null) {
                $apiFailures[$task] = $fetched['error'];
                $this->error("  楽天APIの取得に失敗しました（{$fetched['error']}）。");
                $this->error('  これは「該当0件」ではありません。検索語ではなくAPI側（レート制限・障害）を疑ってください。');

                continue;
            }

            $results = $fetched['items'];
            $rakuten = array_values(array_filter($results, fn (array $i): bool => ($i['mall'] ?? '') === 'rakuten'));

            // 取りこぼしの原因を切り分けるための内訳
            $this->printDiagnosis($this->diagnose($rakuten, $modelName, $displayName));

            $spelling = $this->dominantTitleSpelling($rakuten, $modelName);
            if ($spelling !== null) {
                $this->line("    商品側の表記(最多)   : {$spelling}  ／ DB上の名前: {$modelName}"
                    .($displayName !== null && $displayName !== '' ? " / display_name: {$displayName}" : ''));
            }

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
                // 採用条件は「説明文で車種名の直後に型式が現れること」。
                //
                // ※ 第4版で「タイトルでも裏が取れること」を採用条件にしたが、2026-08-12 の実測で
                //   誤りは1件も落とせず（DS12E → MR7E-9 は残存）、正しい採用を4件落とした
                //   （プラグ 8→4）ため撤回した。原因は適合表の埋め込みではなく、単品商品の
                //   タイトル自体が誤っていたこと（「…Vストローム250 DS12E…」と書かれた商品の
                //   実際の品番が別車種用）。出品者は同じフィードから生成するため誤りは相関し、
                //   タイトルと説明文の一致でも一致数の多数決でも検出できない。
                //   よってタイトル一致は「除外条件」ではなく「信頼度の印」として保持する。
                $isFitmentTable = false;
                $rawDescCodes = [];
                $titleCodes = [];
                $formatBCodes = [];
                $titleConfirmed = false;

                if (! $formatAOk) {
                    $isFitmentTable = FitmentTextExtractor::looksLikeFitmentTable($description);
                    $rawDescCodes = FitmentTextExtractor::frameCodesAfterModelName($description, $modelName);
                    $titleCodes = FitmentTextExtractor::frameCodesAfterModelName($name, $modelName);

                    if (! $isFitmentTable) {
                        $formatBCodes = $rawDescCodes;
                        $titleConfirmed = $this->intersectFrameCodes($rawDescCodes, $titleCodes) !== [];
                    }
                }

                if ($formatAOk) {
                    $nameMatched++;
                    $adopted++;
                    $adoptedA++;
                    $taskAdopted++;
                    $verdict = '採用（書式A: 【適合型式】ラベル）';
                    $this->countPairs($pairs, $oemPairs, $frameCodes, $partNumber, $oemNumbers, $name, self::CONFIDENCE_LABEL);
                    $this->recordByCode($byCode, $task, $frameCodes, $partNumber);
                    if ($partNumber === null) {
                        $adoptedWithoutPart++;
                    }
                } elseif ($formatBCodes !== []) {
                    $nameMatched++;
                    $adopted++;
                    $adoptedB++;
                    $taskAdopted++;
                    $confidence = $titleConfirmed ? self::CONFIDENCE_HIGH : self::CONFIDENCE_MEDIUM;
                    $verdict = '採用（書式B / 信頼度 '.$confidence.'）';
                    // 書式Bには純正品番ラベルが無いのが実データの傾向。あれば拾うが、無くても採用する。
                    $this->countPairs($pairs, $oemPairs, $formatBCodes, $partNumber, $oemNumbers, $name, $confidence);
                    $this->recordByCode($byCode, $task, $formatBCodes, $partNumber);
                    if ($partNumber === null) {
                        $adoptedWithoutPart++;
                    }
                } elseif ($isFitmentTable && $rawDescCodes !== []) {
                    // 適合表を貼った商品。型式自体は取れるが、それは商品の適合ではなく表の1行。
                    $reject['fitment_table']++;
                    $verdict = '不採用（適合表の埋め込みと判断: 型式が'
                        .count(FitmentTextExtractor::distinctFrameCodeTokens($description)).'種）';
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
        $this->line("  判定不能                        : {$reject['undecidable']}");

        $this->printPairs('---- 型式 → 品番（メーカー品番）と一致数 ----', $pairs);
        $this->printPairs('---- 型式 → 純正品番 と一致数 ----', $oemPairs);

        $contradictions = $this->detectContradictions($byCode);
        if ($contradictions !== []) {
            $this->newLine();
            $this->line('---- 要確認（型式間で品番が食い違う）----');
            foreach ($contradictions as $c) {
                $this->warn('  '.$c);
            }
            $this->line('  ※ 互換品番（先頭1文字違い等）は同一系列として除外済み。');
            $this->line('  ※ ここに出る組は、どちらかの商品データが誤っている可能性があります。');
        }

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
     * 照合モード（--verify）: model_fitments の既存データを正解として抽出精度を測る。
     *
     * DBは SELECT のみ。書き込みは一切しない。
     *
     * 対象車種の並び順: 車種ごとの MAX(verified_at) の降順 → bike_model_id の昇順。
     * verified_at が入った行は人手で検証済み＝答え合わせの正解として最も信頼できるため先に見る。
     * 日付は同値が多く並びが不定になり得るので、bike_model_id で必ずタイエブレークして
     * 再実行しても同じ順序になるようにする（測定の再現性のため）。
     *
     * 対象タスクは「その車種の model_fitments に実在するタスク」だけに絞る（--task 指定時はさらに絞る）。
     * 存在しないタスクを叩いても照合相手が無く、車種数×タスク数ぶんのリクエストを
     * 無駄に増やしてレート制限に当たりに行くことになるため。
     *
     * @param  array<string, string>  $tasks
     */
    private function verifyMode(ProductSearchService $service, array $tasks, int $limit, int $sleep): int
    {
        $modelLimit = max(1, (int) $this->option('models'));

        $this->newLine();
        $this->line('==== fitment:probe --verify（読み取り専用・DBへは書き込みません）====');
        $this->line("照合車種数の上限: {$modelLimit} / 1タスクあたり {$limit}件 / 待機 {$sleep}秒");
        $this->line('照合方式: model_fitments の既知型式だけを商品テキストから探す（FRAME_CODEパターンでの発見はしない）');
        $this->comment('  ※既知の値しか探さないため誤検出は起きず、その代わり「新規」型式の発見も行いません（発見は通常モードの担当）。');

        // 車種ごとの最新 verified_at を取り、順序を固定して取得（SELECTのみ）
        $targets = ModelFitment::query()
            ->join('bike_models', 'bike_models.id', '=', 'model_fitments.bike_model_id')
            ->groupBy('model_fitments.bike_model_id', 'bike_models.name', 'bike_models.display_name')
            ->orderByRaw('MAX(model_fitments.verified_at) DESC')
            ->orderBy('model_fitments.bike_model_id')
            ->limit($modelLimit)
            ->get([
                'model_fitments.bike_model_id',
                'bike_models.name as model_name',
                'bike_models.display_name',
            ]);

        if ($targets->isEmpty()) {
            $this->warn('model_fitments にデータがありません。');

            return self::SUCCESS;
        }

        $result = [
            self::VERDICT_MATCH => 0,
            self::VERDICT_MATCH_COMPAT => 0,
            self::VERDICT_CONFLICT => 0,
            self::VERDICT_MISSED_NO_ITEMS => 0,
            self::VERDICT_MISSED_NO_NAME => 0,
            self::VERDICT_MISSED_NO_CODE => 0,
            self::VERDICT_NEW => 0,
        ];
        $processedModels = 0;
        $abortReason = null;   // 全体を打ち切った理由（レート制限に限らない）
        $skipped = [];         // 400 等でこの車種だけ飛ばしたもの

        // 照合モード内でのみ「品番でない」と判断して除外した抽出値（品番 => 除外理由）。
        // PartsCodeExtractor 本体は変更せず、ここ（測定側）だけで弾く。run 全体で distinct 集計。
        $excludedNonParts = [];

        // 型式パターン拡張の効果測定（--frame-widen）。トークン => 出現商品数 を run 全体で集計する。
        // ★診断専用。採用判定には一切使わない（下の compareWithExisting は現行パターンのまま）。
        $widen = (bool) $this->option('frame-widen');
        $widenCurrent = [];    // 現行パターン（数字2桁）で拾える distinct トークン
        $widenAll = [];        // 拡張パターン（数字2〜3桁）で拾える distinct トークン

        foreach ($targets as $target) {
            if ($abortReason !== null) {
                break;
            }

            $modelId = (int) $target->bike_model_id;
            $modelName = (string) $target->model_name;
            $displayName = isset($target->display_name) ? (string) $target->display_name : null;

            // この車種の既存行（SELECTのみ）。task → frame_code → 品番集合 に畳む。
            $existingRows = ModelFitment::query()
                ->where('bike_model_id', $modelId)
                ->get(['task', 'frame_code', 'oem_part_no', 'recommended_part_no', 'compatible_part_nos']);

            /** @var array<string, array<string, array<int, string>>> $existing */
            $existing = [];
            foreach ($existingRows as $row) {
                $task = (string) $row->task;
                $code = strtoupper(trim((string) $row->frame_code));
                foreach ($this->existingPartNumbers($row) as $part) {
                    $existing[$task][$code][] = $part;
                }
            }

            // 照合するタスク＝既存データにあるタスク（--task 指定があればその積集合）
            $targetTasks = array_intersect_key($tasks, $existing);
            if ($targetTasks === []) {
                continue;
            }

            $this->newLine();
            $this->line('────────────────────────────────');
            $this->line("[{$modelName}] (bike_model_id={$modelId}) 照合タスク: ".implode(', ', array_keys($targetTasks)));

            foreach ($targetTasks as $task => $partName) {
                $query = "{$modelName} {$partName}";
                // --verify は楽天のみ取得（Yahoo は集計対象外なのでリクエストが無駄になる）
                $fetched = $this->searchWithRetry($service, $query, $limit, $sleep, true);

                if ($fetched['error'] !== null) {
                    // 429 は searchWithRetry が粘ったうえで返してくるので、来たら全体を打ち切る。
                    // それ以外（400 等）は待っても直らないが、その車種だけ飛ばせば測定は続けられる。
                    if (str_contains($fetched['error'], '429')) {
                        $this->error("  楽天APIがレート制限を返し続けています（{$fetched['error']}）。ここで打ち切ります。");
                        $abortReason = $fetched['error'].'（レート制限・再試行しても回復せず）';
                        break;
                    }

                    $this->warn("  楽天APIの取得に失敗（{$fetched['error']}）。この車種をスキップして次へ進みます。");
                    $skipped[] = "{$modelName}（{$fetched['error']}）";

                    continue 2; // 次の車種へ
                }

                $results = $fetched['items'];

                // 型式パターン拡張の効果測定（採用には反映しない・数えるだけ）
                if ($widen) {
                    $this->accumulateFrameCodeWiden($results, $widenCurrent, $widenAll);
                }

                // 取りこぼしの原因を切り分ける（データ無し / 名前を認識できない / 型式が無い）
                $diag = $this->diagnose($results, $modelName, $displayName);
                $this->line("  [{$task}] 検索クエリ: 「{$query}」");
                $this->printDiagnosis($diag);
                $spelling = $this->dominantTitleSpelling($results, $modelName);
                $this->line('    商品側の表記(最多)   : '.($spelling ?? '(該当なし)')."  ／ DB上の名前: {$modelName}"
                    .($displayName !== null && $displayName !== '' ? " / display_name: {$displayName}" : ''));

                // 探すべき型式の一覧＝この車種・このタスクの既存 frame_code を正規化したもの
                // （"2BK-DN11A/8BK-DN12B" → [DN11A, DN12B]）。空文字キー（型式区別なし）は何も生まない。
                $knownCodes = [];
                foreach (array_keys($existing[$task] ?? []) as $rawCode) {
                    foreach (FitmentTextExtractor::normalizeFrameCodesForMatching((string) $rawCode) as $code) {
                        $knownCodes[$code] = true;
                    }
                }
                $knownCodes = array_keys($knownCodes);

                // 既知の型式だけを商品テキストから拾う（パターン発見はしない）。
                // 品番として明らかにおかしい抽出値は $excludedNonParts に退避してから照合する。
                $extracted = $this->extractForVerifyByKnownCodes($results, $knownCodes, $excludedNonParts);

                foreach ($this->compareWithExisting($existing[$task] ?? [], $extracted, $diag) as $line) {
                    $result[$line['verdict']]++;
                    $this->line(sprintf(
                        '    %-10s %-28s 既存: %-22s 抽出: %s',
                        $task,
                        $line['code'] === '' ? '(型式なし)' : $line['code'],
                        $line['existing'] === '' ? '(なし)' : $line['existing'],
                        $line['extracted'] === '' ? '(なし)' : $line['extracted']
                    ));
                    $this->line('      → '.$line['verdict']);
                }
            }

            $processedModels++;
        }

        $this->newLine();
        $this->line('==== 照合結果 ====');
        if ($abortReason !== null) {
            // 中断理由は実際のエラーに合わせる（何でも「レート制限」と書かない）
            $this->warn("{$processedModels}車種まで測定して中断: {$abortReason}");
        } else {
            $this->line("照合した車種数: {$processedModels}");
        }

        if ($skipped !== []) {
            $this->newLine();
            $this->warn('スキップした車種: '.implode(' / ', $skipped));
        }

        foreach ($result as $verdict => $count) {
            $this->line(sprintf('  %-28s : %d', $verdict, $count));
        }
        $this->comment('  ※「新規」は既知型式だけを探すこの方式では構造上 0 のままです（未知型式の発見は通常モードの担当）。');

        // 品番でないと判断して除外した抽出値（distinct）。実例を必ず出し、過剰除外を目視確認できるようにする。
        $this->newLine();
        if ($excludedNonParts === []) {
            $this->line('  品番でないと判断して除外: 0件');
        } else {
            $samples = [];
            foreach ($excludedNonParts as $part => $reason) {
                $samples[] = "{$part}（{$reason}）";
            }
            $this->line('  品番でないと判断して除外: '.count($excludedNonParts).'件');
            $this->line('    実例: '.implode(' / ', array_slice($samples, 0, 20)));
        }

        // 品番の一致率（行単位の集計とは別に、既存・抽出の両方に値がある行だけを分母にする）。
        // 取りこぼし（抽出が無い）と新規（既存が無い）は分母に入れない。精度ではなくデータ有無の問題のため。
        $matched = $result[self::VERDICT_MATCH];
        $matchedCompat = $result[self::VERDICT_MATCH_COMPAT];
        $conflict = $result[self::VERDICT_CONFLICT];
        $comparable = $matched + $matchedCompat + $conflict;

        $this->newLine();
        $this->line('==== 品番の一致率（既存・抽出の両方に値がある行のみ）====');
        $this->line(sprintf('  照合できた行（既存・抽出の両方に値あり）: %d行', $comparable));
        $this->line(sprintf('  うち一致                                  : %d行', $matched));
        $this->line(sprintf('  うち一致（互換品番）                      : %d行', $matchedCompat));
        $this->line(sprintf('  うち食い違い                              : %d行', $conflict));
        $this->line(sprintf('  一致率                                    : %s', $this->percent($matched + $matchedCompat, $comparable)));
        $this->comment('  ※一致率の分子は「一致」＋「一致（互換品番）」。互換品番は実用上正しい情報が取れているため一致に含める。');

        $this->newLine();
        $this->warn('  既存と抽出が食い違った件数: '.$result[self::VERDICT_CONFLICT]);
        $this->newLine();
        $this->comment('※ 食い違いは、既存データが誤っている場合と抽出が誤っている場合の');
        $this->comment('  両方があります。実例: ジョルノ AF70 は既存 CPR6EA-9 が誤りで、');
        $this->comment('  抽出した CR7HSA-9 が正しい値でした。人が確認する対象として扱ってください。');

        if ($widen) {
            $this->printFrameCodeWiden($widenCurrent, $widenAll);
        }

        return self::SUCCESS;
    }

    /**
     * 【診断専用】取得商品の「タイトル＋説明文」を走査し、型式トークンを現行/拡張パターンで数える。
     * $current / $widened は「トークン => 出現商品数」で run 全体にわたって加算する。
     *
     * ★採用判定には一切関与しない。増分と実例を見るために数えるだけ。
     * 走査は楽天商品のみ（--verify は楽天限定・Yahoo には説明文が無い）。
     *
     * @param  array<int, array<string, mixed>>  $results
     * @param  array<string, int>  $current
     * @param  array<string, int>  $widened
     */
    private function accumulateFrameCodeWiden(array $results, array &$current, array &$widened): void
    {
        foreach ($results as $item) {
            if (($item['mall'] ?? '') !== 'rakuten') {
                continue;
            }

            $text = ((string) ($item['name'] ?? '')).' '.((string) ($item['description'] ?? ''));

            foreach (FitmentTextExtractor::distinctFrameCodeTokens($text) as $tok) {
                $current[$tok] = ($current[$tok] ?? 0) + 1;
            }
            foreach (FitmentTextExtractor::distinctFrameCodeTokensWidened($text) as $tok) {
                $widened[$tok] = ($widened[$tok] ?? 0) + 1;
            }
        }
    }

    /**
     * 【診断専用】型式パターンを数字3桁まで広げた場合の増分と実例を出す。
     *
     * 採用判定は現行パターン（数字2桁）のまま。ここは「広げたら何が追加で拾えるか」を
     * 目で確認するための出力で、車種名らしき語（CB250R / GB350S 等）が混ざらないかを見極める。
     *
     * @param  array<string, int>  $current 現行パターンで拾えた型式 => 出現商品数
     * @param  array<string, int>  $widened 拡張パターンで拾えた型式 => 出現商品数
     */
    private function printFrameCodeWiden(array $current, array $widened): void
    {
        $this->newLine();
        $this->line('==== 型式パターン拡張の影響（診断のみ・採用ロジックは未変更）====');
        $this->line('  走査対象           : 取得商品のタイトル＋説明文の全体（採用経路より広い上限値）');
        $this->line('  現行パターン(英字2+数字2+任意英字)で拾えた型式    : '.count($current).'個');

        // 拡張でのみ拾えるトークン（＝広げたときの追加分）を、出現商品数の降順で並べる。
        $extra = [];
        foreach ($widened as $tok => $cnt) {
            if (! array_key_exists($tok, $current)) {
                $extra[] = [$tok, $cnt];
            }
        }
        // 頻度降順 → トークン昇順（再実行しても並びが一定になるよう決定的に）
        usort($extra, fn (array $a, array $b): int => [$b[1], $a[0]] <=> [$a[1], $b[0]]);

        $this->line('  拡張パターン(英字2+数字2〜3+任意英字)で追加される型式: '.count($extra).'個');

        if ($extra === []) {
            $this->line('  追加される型式はありませんでした。');

            return;
        }

        // 追加分のうち、bike_models.name / display_name と（正規化して）一致するもの＝車種名の混入。
        // 接頭辞リストの妥当性を検証するときの対照になる。
        // 型式トークン（英字を含む）で isset 参照するだけなので、数字だけの名前が int キーになっても無害。
        $modelSet = array_fill_keys($this->modelNames(), true);
        $nameMatch = static fn (string $tok): bool => isset($modelSet[$tok]);

        $mixed = 0;
        foreach ($extra as [$tok, $cnt]) {
            if ($nameMatch($tok)) {
                $mixed++;
            }
        }
        $this->line(sprintf(
            '  うち車種名（name/display_name）と一致: %d個（%s）',
            $mixed,
            $this->percent($mixed, count($extra))
        ));

        $samples = array_slice($extra, 0, 20);
        $this->line('  追加される型式の実例（最大20件・出現商品数の降順、★＝車種名と一致）:');
        $this->line('    '.implode('  ', array_map(
            fn (array $r): string => $r[0].'('.$r[1].'商品)'.($nameMatch($r[0]) ? ' ★車種名と一致' : ''),
            $samples
        )));

        $this->newLine();
        $this->comment('  ※★（車種名と一致）が多いほど、単純な数字3桁拡張の誤検出が多いことを意味します。');
        $this->comment('  ※このブロックは測定のみ。採用は現行パターン（数字2桁）のままです。');
    }

    /**
     * bike_models.name と display_name を正規化した「重複なしのリスト（値の配列）」を返す。
     *
     * 用途は2つ:
     *   - 抽出品番が車種名混じりか（nonPartNumberReason の規則a）… 値を回して部分一致を見る
     *   - --frame-widen で追加型式が車種名か … 呼び出し側で array_fill_keys して isset 判定
     * トークン（大文字・空白なしの型式形）と突き合わせるため normalize → 大文字化して持つ。
     * DB は SELECT のみ。1回だけ読み、以後は使い回す。
     *
     * ★戻り値は「値の配列」。重複排除は連想配列のキーで行うが、返す前に array_map('strval', …) で
     *   必ず string の値へ戻す。こうしないと「250」のような数字だけの名前がキーの段階で int 化し、
     *   呼び出し側の mb_strlen()/str_contains() へ int が渡って TypeError になる（本番で発生した罠）。
     *
     * @return array<int, string>
     */
    private function modelNames(): array
    {
        if ($this->modelNames !== null) {
            return $this->modelNames;
        }

        $seen = [];
        foreach (BikeModel::query()->get(['name', 'display_name']) as $model) {
            foreach ([$model->name, $model->display_name] as $value) {
                $key = strtoupper(FitmentTextExtractor::normalize((string) ($value ?? '')));
                if ($key !== '') {
                    $seen[$key] = true; // 重複排除用。数字だけの名前はここで int キーになり得る
                }
            }
        }

        // array_keys だと数字だけの名前が int で返るため、strval で string の値配列に戻す。
        return $this->modelNames = array_map('strval', array_keys($seen));
    }

    /**
     * 既存行が持つ品番（推奨・純正・互換）をまとめて返す。
     * compatible_part_nos は [{"brand":"…","part_no":"…"}] 形式（モデルで array キャスト済み）。
     *
     * @return array<int, string>
     */
    private function existingPartNumbers(ModelFitment $row): array
    {
        $out = [];

        foreach ([$row->recommended_part_no, $row->oem_part_no] as $value) {
            $value = strtoupper(trim((string) ($value ?? '')));
            if ($value !== '') {
                $out[] = $value;
            }
        }

        $compatible = $row->compatible_part_nos;
        if (is_array($compatible)) {
            foreach ($compatible as $entry) {
                $partNo = is_array($entry) ? ($entry['part_no'] ?? null) : $entry;
                $partNo = strtoupper(trim((string) ($partNo ?? '')));
                if ($partNo !== '') {
                    $out[] = $partNo;
                }
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * 【--verify 専用】既知の型式一覧に含まれる型式だけを商品テキストから拾い、品番と紐づける。
     *
     * パターンで「型式らしきもの」を発見する方式ではなく、model_fitments に既に入っている
     * 正解の型式（EX250L / ZR900C など）だけを探す。だから CB250R のような車種名の形を
     * 型式と誤認することが原理的に起きない。また ninja 250 のように商品側の表記（Ninja250）と
     * DB名（ninja 250）の空白が食い違って書式Bが空振りする車種でも、型式そのものは本文にあるので
     * 回収できる（照合の起点を車種名ではなく既知型式にするため）。
     *
     * 走査対象はタイトル＋説明文。語境界は findKnownFrameCodes が distinctFrameCodeTokens と
     * 同じ規則で判定し、車台番号 LC6DS12EZ01100001 の中の DS12E は拾わない。
     *
     * @param  array<int, array<string, mixed>>  $results
     * @param  array<int, string>  $knownCodes 正規化済みの探すべき型式（DN11A 等）
     * @param  array<string, string>  $excluded 品番でないと判断して弾いた値（品番 => 理由）を run 全体で蓄積する
     * @return array<string, array<int, string>> 型式 => 品番群
     */
    private function extractForVerifyByKnownCodes(array $results, array $knownCodes, array &$excluded): array
    {
        $out = [];

        if ($knownCodes === []) {
            return $out;
        }

        foreach ($results as $item) {
            if (($item['mall'] ?? '') !== 'rakuten') {
                continue;
            }

            $name = (string) ($item['name'] ?? '');
            $description = (string) ($item['description'] ?? '');
            $partNumber = PartsCodeExtractor::extract($name, $description)['partNumber'];
            if ($partNumber === null) {
                continue;
            }

            $partNumber = strtoupper($partNumber);

            // 品番として明らかにおかしい抽出値は照合に入れない（照合モード内だけの措置）。
            $reason = $this->nonPartNumberReason($partNumber);
            if ($reason !== null) {
                $excluded[$partNumber] = $reason; // distinct（同じSKUが複数店舗で出ても1件として数える）

                continue;
            }

            foreach (FitmentTextExtractor::findKnownFrameCodes($name.' '.$description, $knownCodes) as $code) {
                $out[$code][] = $partNumber;
            }
        }

        foreach ($out as $code => $parts) {
            $out[$code] = array_values(array_unique($parts));
        }

        return $out;
    }

    /**
     * 【--verify 専用】抽出値が「品番ではない」と判断できる理由を返す（該当しなければ null）。
     *
     * PartsCodeExtractor 本体は変更せず、照合モードの中だけで明らかなノイズを弾くためのもの。
     * 実データ（2026-08-12）で食い違いに紛れ込んでいた次の2種を落とす:
     *   a) 車種名混じり: AF78ZOOMER-X（車種名 ZOOMER-X を含む）
     *   b) 店舗SKU: D2-98308（5桁以上の連番を含む）
     *
     * @return string|null 除外理由。null は「品番として妥当（除外しない）」
     */
    private function nonPartNumberReason(string $part): ?string
    {
        $norm = strtoupper(FitmentTextExtractor::normalize($part));

        // b) 5桁以上の連番を含み、かつ先頭が数字でないもの（D2-98308 のような店舗SKU）。
        //    「先頭が数字でない」を条件に足すのは過剰除外を避けるため。純正(OEM)品番は
        //    数字グループで始まる（スズキ 16510-06B00、ヤマハ 4FM-14613-00、ホンダ 15410-KYJ-901 等）
        //    ため、これらは5桁連番を含んでも先頭が数字なので除外しない。一方、店舗SKUは
        //    D2-/BC- のように英字接頭辞から始まることが多い。桁数だけで切ると OEM 品番まで
        //    落として一致率をかえって下げてしまうため、この2条件のANDにする。
        if (preg_match('/[0-9]{5,}/', $norm) === 1 && preg_match('/^[0-9]/', $norm) !== 1) {
            return '5桁以上の連番＋英字始まり（店舗SKUと判断）';
        }

        // a) 車種名（name/display_name）を部分文字列として含むもの（AF78ZOOMER-X 等）。
        //    ただし短い車種名（GB/Z/CT 等）は正規の品番の一部に偶然含まれて過剰除外を招くため、
        //    正規化後4文字以上の車種名に限る。4文字未満（「250」等）は判定材料にしない。
        //    modelNames() は値の配列だが、念のため明示的に string へキャストしてから使う
        //    （数字だけの名前が int で紛れ込んでも mb_strlen()/str_contains() を壊さないため）。
        foreach ($this->modelNames() as $name) {
            $name = (string) $name;
            if (mb_strlen($name) >= 4 && str_contains($norm, $name)) {
                return "車種名『{$name}』を含む";
            }
        }

        return null;
    }

    /**
     * 既存データと抽出結果を型式ごとに突き合わせる。
     *
     * 既存の frame_code が空文字（型式区別なし）の行は、抽出したどの型式とも照合できるよう
     * 「抽出結果の全品番」を相手にする（スキーマ上 '' は「型式を区別しない」の意味のため）。
     *
     * @param  array<string, array<int, string>>  $existing  型式 → 品番群
     * @param  array<string, array<int, string>>  $extracted 型式 → 品番群
     * @return array<int, array{code: string, existing: string, extracted: string, verdict: string}>
     */
    private function compareWithExisting(array $existing, array $extracted, array $diag): array
    {
        $out = [];
        $allExtracted = array_values(array_unique(array_merge(...array_values($extracted) ?: [[]])));
        $covered = [];

        foreach ($existing as $rawCode => $parts) {
            // 既存の frame_code を照合可能な形へ（"2BK-DN11A/8BK-DN12B" → [DN11A, DN12B]）
            $normalized = FitmentTextExtractor::normalizeFrameCodesForMatching((string) $rawCode);

            if ((string) $rawCode === '') {
                // 型式区別なしの行は、抽出できた全品番を相手にする
                $mine = $allExtracted;
                $covered = array_merge($covered, array_keys($extracted));
            } else {
                $mine = [];
                foreach ($normalized as $code) {
                    if (isset($extracted[$code])) {
                        $mine = array_merge($mine, $extracted[$code]);
                        $covered[] = $code;
                    }
                }
                $mine = array_values(array_unique($mine));
            }

            // 完全一致が最優先。無ければ同一系列（他社互換品）の一致を見る。
            // どちらも無ければ食い違い。互換一致を食い違いに数えると精度が実態より低く見えるため分ける。
            $verdict = match (true) {
                $mine === [] => $this->classifyMissed($diag),
                array_intersect($parts, $mine) !== [] => self::VERDICT_MATCH,
                $this->hasSameSeriesMatch($parts, $mine) => self::VERDICT_MATCH_COMPAT,
                default => self::VERDICT_CONFLICT,
            };

            // 表示は元の値のまま。正規化結果は括弧で併記する（どちらの表記だったか分かるように）
            $codeLabel = (string) $rawCode;
            if ($codeLabel !== '' && $normalized !== [] && $normalized !== [$codeLabel]) {
                $codeLabel .= '（→ '.implode(', ', $normalized).'）';
            }

            $out[] = [
                'code' => $codeLabel,
                'existing' => implode(', ', $parts),
                'extracted' => implode(', ', $mine),
                'verdict' => $verdict,
            ];
        }

        // 既存のどの型式にも紐づかなかった抽出＝新規（誤りとは限らない）
        foreach ($extracted as $code => $parts) {
            if (! in_array($code, $covered, true)) {
                $out[] = [
                    'code' => (string) $code,
                    'existing' => '',
                    'extracted' => implode(', ', $parts),
                    'verdict' => self::VERDICT_NEW,
                ];
            }
        }

        return $out;
    }

    /**
     * 取りこぼしの原因を診断値から機械的に決める。
     *
     * @param  array{total: int, strict: int}  $diag
     */
    private function classifyMissed(array $diag): string
    {
        return match (true) {
            $diag['total'] === 0 => self::VERDICT_MISSED_NO_ITEMS,
            $diag['strict'] === 0 => self::VERDICT_MISSED_NO_NAME,
            default => self::VERDICT_MISSED_NO_CODE,
        };
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
     * 楽天へ1リクエスト投げる。429 のときは指数バックオフで最大3回まで再試行する。
     *
     * 呼び出しのたびに $sleep 秒空ける（タスク間ではなくリクエスト単位）。
     * 429 で即打ち切ると測定が完走しないため粘るが、それでも駄目なら諦めて呼び出し側へ返す。
     *
     * @return array{items: array<int, array<string, mixed>>, error: string|null}
     */
    private function searchWithRetry(ProductSearchService $service, string $query, int $limit, int $sleep, bool $rakutenOnly): array
    {
        $attempt = 0;

        while (true) {
            if ($sleep > 0) {
                sleep($sleep);
            }

            // 第5引数 false ＝ 429後の休止（ブレーカー）を迂回する。
            // ブレーカーはサイト側の render を守るためのもので、明示的な再試行まで止めると
            // 測定が「休止中」だらけで完走しなくなる。間隔制御のほうは迂回しない（叩く量は守る）。
            $results = $service->searchProducts($query, $limit, true, $rakutenOnly, respectBreaker: false);
            $error = $service->lastErrors()['rakuten'] ?? null;

            if ($error === null) {
                return ['items' => $results, 'error' => null];
            }

            // 429 以外（500 等）は待っても直らないことが多いので再試行しない
            if (! str_contains($error, '429') || $attempt >= count(self::RETRY_BACKOFF)) {
                return ['items' => [], 'error' => $error];
            }

            $wait = self::RETRY_BACKOFF[$attempt];
            $attempt++;
            // 沈黙して止まっているように見えないよう、待機のたびに1行出す
            $this->warn("    レート制限のため {$wait}秒待機して再試行します（{$attempt}回目）");
            sleep($wait);
        }
    }

    /**
     * 検索結果の内訳を出して、取りこぼしの原因を「データが無い/名前を認識できない/型式が無い」に分解する。
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array{total: int, loose: int, strict: int}
     */
    private function diagnose(array $items, string $modelName, ?string $displayName = null): array
    {
        $loose = 0;
        $strict = 0;
        $strictNoSpace = 0;
        $strictDisplay = 0;

        // 照合候補を増やしたときの増分を測るための派生表記。
        // ★ 採用判定は今も name のみ。ここは効果測定だけで、採否には一切使わない。
        $noSpace = preg_replace('/[\s\x{3000}]+/u', '', $modelName) ?? $modelName;

        foreach ($items as $item) {
            $haystack = ((string) ($item['name'] ?? '')).' '.((string) ($item['description'] ?? ''));

            if (FitmentTextExtractor::containsModelNameLoose($haystack, $modelName)) {
                $loose++;
            }

            $hitName = FitmentTextExtractor::containsModelName($haystack, $modelName);
            $hitNoSpace = $hitName
                || ($noSpace !== $modelName && FitmentTextExtractor::containsModelName($haystack, $noSpace));
            $hitDisplay = $hitNoSpace
                || ($displayName !== null && $displayName !== ''
                    && FitmentTextExtractor::containsModelName($haystack, $displayName));

            if ($hitName) {
                $strict++;
            }
            if ($hitNoSpace) {
                $strictNoSpace++;
            }
            if ($hitDisplay) {
                $strictDisplay++;
            }
        }

        return [
            'total' => count($items),
            'loose' => $loose,
            'strict' => $strict,
            'strictNoSpace' => $strictNoSpace,
            'strictDisplay' => $strictDisplay,
        ];
    }

    /**
     * @param  array{total: int, loose: int, strict: int, strictNoSpace: int, strictDisplay: int}  $d
     */
    private function printDiagnosis(array $d): void
    {
        $this->line("    取得商品数           : {$d['total']}");
        $this->line("    車種名を含む(緩い)   : {$d['loose']}  ※空白・記号・大小文字・全半角を無視した部分一致（診断専用）");
        $this->line("    車種名が厳密一致     : {$d['strict']}  ※採用判定に使うのはこちら");
        $this->line(sprintf(
            '    照合候補の効果測定   : name のみ: %d件 / +空白除去: %d件 / +display_name: %d件  ※採用には未反映',
            $d['strict'],
            $d['strictNoSpace'],
            $d['strictDisplay']
        ));
    }

    /**
     * 商品タイトル側で最も多く現れた車種名の表記を返す（表記ゆれの診断用）。
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    private function dominantTitleSpelling(array $items, string $modelName): ?string
    {
        $tally = [];

        foreach ($items as $item) {
            $found = FitmentTextExtractor::findLooseOccurrence((string) ($item['name'] ?? ''), $modelName);
            if ($found !== null && trim($found) !== '') {
                $key = trim($found);
                $tally[$key] = ($tally[$key] ?? 0) + 1;
            }
        }

        if ($tally === []) {
            return null;
        }

        arsort($tally);
        $top = array_key_first($tally);

        return $top.'（'.$tally[$top].'件）';
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
    private function countPairs(array &$pairs, array &$oemPairs, array $frameCodes, ?string $partNumber, array $oemNumbers, string $sourceTitle, string $confidence): void
    {
        foreach ($frameCodes as $fc) {
            if ($partNumber !== null) {
                $this->addPair($pairs, $fc['code']."\t".$partNumber, $sourceTitle, $confidence);
            }
            foreach ($oemNumbers as $oem) {
                $this->addPair($oemPairs, $fc['code']."\t".$oem, $sourceTitle, $confidence);
            }
        }
    }

    /**
     * @param  array<string, array{count: int, title: string, confidence: string}>  $table
     */
    private function addPair(array &$table, string $key, string $sourceTitle, string $confidence): void
    {
        if (! isset($table[$key])) {
            // 根拠は先頭の1件だけを残す（一覧が縦に伸びないように）
            $table[$key] = [
                'count' => 0,
                'title' => $this->flatten(mb_substr($sourceTitle, 0, 60)),
                'confidence' => $confidence,
            ];
        }
        $table[$key]['count']++;

        // 同じ組を複数商品が支持する場合は、最も高い信頼度を残す
        if ($this->confidenceRank($confidence) > $this->confidenceRank($table[$key]['confidence'])) {
            $table[$key]['confidence'] = $confidence;
        }
    }

    private function confidenceRank(string $confidence): int
    {
        return match ($confidence) {
            self::CONFIDENCE_LABEL => 3,
            self::CONFIDENCE_HIGH => 2,
            default => 1,
        };
    }

    /**
     * 矛盾検出用に「タスク → 型式 → 品番の集合」を記録する。
     *
     * @param  array<string, array<string, array<int, string>>>  $byCode
     * @param  array<int, array{raw: string, regulation: string|null, code: string}>  $frameCodes
     */
    private function recordByCode(array &$byCode, string $task, array $frameCodes, ?string $partNumber): void
    {
        if ($partNumber === null) {
            return;
        }

        foreach ($frameCodes as $fc) {
            $byCode[$task][$fc['code']][] = $partNumber;
        }
    }

    /**
     * 「型式 → 品番」の組を一致数の多い順に出す。根拠となった商品タイトルと信頼度を併記する。
     *
     * @param  array<string, array{count: int, title: string, confidence: string}>  $pairs
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
            $this->line(sprintf('  %-10s → %-20s  %d商品が一致  信頼度: %s', $code, $part, $row['count'], $row['confidence']));
            $this->line('      根拠: '.$row['title']);
        }
    }

    /**
     * 同一車種・同一タスクの中で、型式ごとに別系列の品番が採用されている箇所を洗い出す。
     *
     * 例（2026-08-12 の実データ）: plug で DS11A → CPR7EA-9 / DS12E → MR7E-9。
     * DS11A と DS12E は同じ248cc並列2気筒（2023年の規制で型式が変わっただけ）なので、
     * プラグが変わるのは不自然＝どちらかの商品データが誤っている疑いがある。
     *
     * ただしバッテリーは正常に複数出る（YTX9-BS に対する ATX9-BS / BTX9-BS / DYTX9-BS 等は
     * 他社の互換品番）。これを矛盾と誤判定しないよう、同一系列は除外する。
     *
     * @param  array<string, array<string, array<int, string>>>  $byCode  タスク → 型式 → 品番群
     * @return array<int, string>
     */
    private function detectContradictions(array $byCode): array
    {
        $out = [];

        foreach ($byCode as $task => $codes) {
            $codeNames = array_keys($codes);
            $count = count($codeNames);

            for ($i = 0; $i < $count; $i++) {
                for ($j = $i + 1; $j < $count; $j++) {
                    $a = $codeNames[$i];
                    $b = $codeNames[$j];
                    $partsA = array_values(array_unique($codes[$a]));
                    $partsB = array_values(array_unique($codes[$b]));

                    // どれか1組でも同一系列なら、型式間で品番が食い違っているとは言えない
                    $related = false;
                    foreach ($partsA as $pa) {
                        foreach ($partsB as $pb) {
                            if ($this->isSameSeries($pa, $pb)) {
                                $related = true;
                                break 2;
                            }
                        }
                    }

                    if (! $related) {
                        $out[] = sprintf(
                            '%s: %s → %s / %s → %s',
                            $task,
                            $a,
                            implode(', ', $partsA),
                            $b,
                            implode(', ', $partsB)
                        );
                    }
                }
            }
        }

        return $out;
    }

    /**
     * 2つの品番が「同一系列（互換品番）」か。
     *
     * 判定規則: それぞれについて「そのもの」と「先頭1文字を落としたもの」を候補に取り、
     * 候補どうしが4文字以上で一致すれば同一系列とみなす。
     *   YTX9-BS  → {YTX9-BS, TX9-BS}
     *   ATX9-BS  → {ATX9-BS, TX9-BS}   … TX9-BS で一致 → 同一系列（先頭1文字違い）
     *   DYTX9-BS → {DYTX9-BS, YTX9-BS} … YTX9-BS で一致 → 同一系列（先頭1文字の付加）
     *   CPR7EA-9 → {CPR7EA-9, PR7EA-9}
     *   MR7E-9   → {MR7E-9, R7E-9}     … 一致なし → 別系列（＝矛盾として報告する）
     * バッテリーの互換品番は「メーカー記号 + 共通型番」という命名なので、この規則で拾える。
     * 4文字の下限は、短い断片の偶然一致を避けるため。
     */
    /**
     * 既存品番群と抽出品番群のあいだに、同一系列（互換品番）の組が1つでもあるか。
     *
     * @param  array<int, string>  $existing
     * @param  array<int, string>  $extracted
     */
    private function hasSameSeriesMatch(array $existing, array $extracted): bool
    {
        foreach ($existing as $a) {
            foreach ($extracted as $b) {
                if ($this->isSameSeries($a, $b)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function isSameSeries(string $a, string $b): bool
    {
        $keys = static function (string $code): array {
            $code = strtoupper($code);
            $out = [$code];
            if (mb_strlen($code) > 1) {
                $out[] = mb_substr($code, 1);
            }

            return array_values(array_filter($out, fn (string $k): bool => mb_strlen($k) >= 4));
        };

        return array_intersect($keys($a), $keys($b)) !== [];
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
    private function resolveModelName(string $requested): array
    {
        $exact = BikeModel::where('name', $requested)->first(['id', 'name', 'display_name']);
        if ($exact !== null) {
            $this->line("車種名の解決: 「{$requested}」= bike_models.name に完全一致（id={$exact->id}）");

            return [(string) $exact->name, $exact->display_name !== null ? (string) $exact->display_name : null];
        }

        $partial = BikeModel::where('name', 'like', "%{$requested}%")
            ->withCount(['listings' => fn ($q) => $q->where('is_sold_out', false)])
            ->orderByDesc('listings_count')
            ->first(['id', 'name', 'display_name']);

        if ($partial !== null) {
            $this->warn("車種名の解決: 「{$requested}」は完全一致せず。部分一致で「{$partial->name}」(id={$partial->id}) を採用します。");

            return [(string) $partial->name, $partial->display_name !== null ? (string) $partial->display_name : null];
        }

        $this->warn("車種名の解決: 「{$requested}」は bike_models に見つかりませんでした。文字列のまま検索します。");

        return [$requested, null];
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
