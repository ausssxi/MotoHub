<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BikeParking;
use App\Models\RentalGarage;
use App\Models\RoadsideStation;
use App\Models\Shop;
use App\Services\Parking\AddressParser;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * 住所テーブルの city カラムを自治体マスタ（municipalities）に実在する表記へ是正する。
 *
 * 対象は「現在の (prefecture, city) が municipalities に無い行」だけ。マスタに実在する行には
 * 一切触れない。prefecture は変更しない（誤りが疑われる行は報告のみ）。
 *
 * 解決は次の順に試し、最初に決まった段を採用する。どの段も、最終的な値が municipalities に
 * 実在することを確認してからでないと書き込まない。
 *   1. 住所から再導出 : address を AddressParser（廃止・改称の補正表つき）で解析
 *   2. 県名除去       : city が行の prefecture で始まる場合に剥がして再照合
 *                       （例「熊本県熊本市南区」→「熊本市南区」）
 *   3. 異体字         : 旧字体・異体字を候補とマスタの双方に適用して照合
 *                       （例「糟屋郡須惠町」→「糟屋郡須恵町」）
 *   4. 県内末尾一致   : 同一 prefecture のマスタで full_name が候補で終わるものを探し、
 *                       ちょうど1件のときだけ採用（例 岩手県「矢巾町」→「紫波郡矢巾町」）。
 *                       0件・2件以上は解決できずとし、候補から選ぶことは絶対にしない。
 *
 * 是正措置なのでスケジュールには載せない。既定は dry-run で、--execute のときだけ書き込む。
 * bike_parkings / roadside_stations には専用の backfill コマンドが無いが、このコマンドで賄う。
 */
final class FixAddressCity extends Command
{
    protected $signature = 'addresses:fix-city
        {--table=all : all|shops|bike_parkings|rental_garages|roadside_stations}
        {--execute : 実際に更新する（未指定は dry-run＝既定）}
        {--chunk=500 : 1チャンクあたりの取得件数}';

    protected $description = 'municipalities に存在しない (prefecture, city) の行を、address 再導出・県名除去・異体字・県内末尾一致で是正します（既定は dry-run）';

    /** 更新内容サンプルの表示上限 */
    private const SAMPLE_LIMIT = 20;

    /** 解決できなかった行の表示上限（元データの破損リストとして使うため多め） */
    private const UNRESOLVED_SAMPLE_LIMIT = 30;

    private const STAGE_ADDRESS = 'address';

    private const STAGE_STRIP_PREFECTURE = 'strip_prefecture';

    private const STAGE_VARIANT = 'variant';

    private const STAGE_SUFFIX = 'suffix';

    /** @var array<string, string> */
    private const STAGE_LABELS = [
        self::STAGE_ADDRESS => '住所から再導出',
        self::STAGE_STRIP_PREFECTURE => '県名除去',
        self::STAGE_VARIANT => '異体字',
        self::STAGE_SUFFIX => '県内末尾一致',
    ];

    /**
     * 照合時にのみ同一視する異体字（旧字体 => 新字体）。候補・マスタの双方に適用する。
     * 書き込む値は常にマスタ側の正式表記なので、この表で DB の文字が置き換わることはない。
     *
     * 出典・確認:
     *   惠→恵 / 曾→曽 / 龍→竜 / 濱→浜 / 澤→沢 / 齋→斎
     *     常用漢字表（平成22年内閣告示第2号）で「いわゆる康熙字典体」として併記されている
     *     旧字体と新字体の対応。自治体名でも 須惠町/須恵町・曾於市/曽於市・龍ケ崎市/竜ケ崎市 の
     *     ように両表記が流通する。
     *   檮→梼
     *     高知県高岡郡檮原町。町の告示名は「檮原町」だが、JIS外字を避けた「梼原町」表記が
     *     住所データに混在する（本番データで両方を実測）。
     *
     * 同一視してよいと確認できたものだけを入れること（安易に増やさない）。
     *
     * @var array<string, string>
     */
    private const VARIANT_CHARS = [
        '惠' => '恵',
        '檮' => '梼',
        '曾' => '曽',
        '龍' => '竜',
        '濱' => '浜',
        '澤' => '沢',
        '齋' => '斎',
    ];

    /** @var array<string, class-string<Model>> */
    private const TABLES = [
        'shops' => Shop::class,
        'bike_parkings' => BikeParking::class,
        'rental_garages' => RentalGarage::class,
        'roadside_stations' => RoadsideStation::class,
    ];

    public function handle(AddressParser $parser): int
    {
        $table = (string) $this->option('table');
        if ($table !== 'all' && ! isset(self::TABLES[$table])) {
            $this->error("--table の値が不正です: {$table}（all|".implode('|', array_keys(self::TABLES)).'）');

            return self::FAILURE;
        }

        $chunk = (int) $this->option('chunk');
        if ($chunk < 1) {
            $this->error('--chunk は1以上を指定してください。');

            return self::FAILURE;
        }

        $execute = (bool) $this->option('execute');

        $master = $this->loadMunicipalities();
        if ($master === []) {
            $this->error('municipalities が空です。先に municipalities:import-n03 を実行してください。');

            return self::FAILURE;
        }

        $this->info($execute ? '[EXECUTE] 実際に更新します。' : '[DRY RUN] 更新は行いません（--execute で実行）。');
        $this->line('自治体マスタ: '.array_sum(array_map(static fn (array $i): int => count($i['exact']), $master)).'件');

        $names = $table === 'all' ? array_keys(self::TABLES) : [$table];

        $totalTarget = 0;
        $totalUpdated = 0;
        $totalUnresolved = 0;
        $totalStages = array_fill_keys(array_keys(self::STAGE_LABELS), 0);

        foreach ($names as $name) {
            $result = $this->processTable($name, $parser, $master, $execute, $chunk);
            $totalTarget += $result['target'];
            $totalUpdated += $result['updated'];
            $totalUnresolved += $result['unresolved'];
            foreach ($result['stages'] as $stage => $count) {
                $totalStages[$stage] += $count;
            }
        }

        if (count($names) > 1) {
            $this->newLine();
            $this->info('=== 合計 ===');
            $this->line("対象: {$totalTarget} / 更新".($execute ? '' : '予定').": {$totalUpdated} / 解決できず: {$totalUnresolved}");
            $this->printStages($totalStages);
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, array{exact: array<string, string>, variant: array<string, list<string>>, names: list<array{full: string, vkey: string}>}>  $master
     * @return array{target: int, updated: int, unresolved: int, stages: array<string, int>}
     */
    private function processTable(string $name, AddressParser $parser, array $master, bool $execute, int $chunk): array
    {
        /** @var class-string<Model> $modelClass */
        $modelClass = self::TABLES[$name];
        $model = new $modelClass;
        $tableName = $model->getTable();

        $total = $modelClass::query()->count();

        // マスタに (prefecture, city) がそのままの表記で存在しない行だけを SQL で先に絞る。
        // 「ヶ／ケ」等の表記ゆれはここでは拾われてしまうが、下の in-memory 判定で除外する
        // （厳密一致で不一致な行の集合は、正規化して不一致な行の集合を必ず含む）。
        $query = $modelClass::query()->whereNotExists(function ($sub) use ($tableName) {
            $sub->select(DB::raw(1))
                ->from('municipalities')
                ->whereColumn('municipalities.prefecture', $tableName.'.prefecture')
                ->whereColumn('municipalities.full_name', $tableName.'.city');
        });

        $target = 0;
        $updated = 0;
        $unresolved = 0;
        $prefectureSuspectCount = 0;
        $stages = array_fill_keys(array_keys(self::STAGE_LABELS), 0);

        /** @var list<string> $updateSamples */
        $updateSamples = [];
        /** @var list<string> $unresolvedSamples */
        $unresolvedSamples = [];
        /** @var list<string> $prefectureSuspects */
        $prefectureSuspects = [];

        $query->chunkById($chunk, function ($rows) use (
            $parser, $master, $execute,
            &$target, &$updated, &$unresolved, &$prefectureSuspectCount, &$stages,
            &$updateSamples, &$unresolvedSamples, &$prefectureSuspects
        ) {
            foreach ($rows as $row) {
                $prefecture = trim((string) $row->prefecture);
                $city = trim((string) $row->city);

                // 表記ゆれ（龍ヶ崎市/龍ケ崎市 等）だけの差はマスタに実在するとみなし、触れない。
                if ($prefecture !== '' && $city !== ''
                    && isset($master[$prefecture]['exact'][self::normalizeForMatch($city)])
                ) {
                    continue;
                }

                $target++;

                $address = trim((string) $row->address);
                $index = $master[$prefecture] ?? null;

                // 行の prefecture は動かさないので、その都道府県の中でだけマスタを引く。
                // prefecture 自体がマスタに無い（空・表記不正）行はここで打ち切る。
                if ($index === null) {
                    $unresolved++;
                    $this->pushSample(
                        $unresolvedSamples,
                        $this->unresolvedLine($row->id, $prefecture, $city, $address, '(prefecture がマスタに無い)'),
                        self::UNRESOLVED_SAMPLE_LIMIT
                    );

                    continue;
                }

                $fromAddress = '';
                if ($address !== '') {
                    $parsed = $parser->parseWithCorrections($address);
                    $fromAddress = $parsed['city'];

                    // prefecture は変更しない。食い違いは報告だけする。
                    if ($parsed['prefecture'] !== '' && $parsed['prefecture'] !== $prefecture) {
                        $prefectureSuspectCount++;
                        $this->pushSample(
                            $prefectureSuspects,
                            "#{$row->id} 現在={$prefecture} / address由来={$parsed['prefecture']} / address={$address}",
                            self::SAMPLE_LIMIT
                        );
                    }
                }

                $resolved = $this->resolve($index, $prefecture, $city, $fromAddress);

                if ($resolved === null || $resolved['city'] === $city) {
                    $unresolved++;
                    $this->pushSample(
                        $unresolvedSamples,
                        $this->unresolvedLine(
                            $row->id,
                            $prefecture,
                            $city,
                            $address,
                            '再導出='.($fromAddress === '' ? '(取得できず)' : $fromAddress)
                        ),
                        self::UNRESOLVED_SAMPLE_LIMIT
                    );

                    continue;
                }

                $updated++;
                $stages[$resolved['stage']]++;
                $this->pushSample(
                    $updateSamples,
                    "#{$row->id} {$prefecture} 「{$city}」 → 「{$resolved['city']}」 [".self::STAGE_LABELS[$resolved['stage']].']',
                    self::SAMPLE_LIMIT
                );

                if ($execute) {
                    $row->updateQuietly(['city' => $resolved['city']]);
                }
            }
        });

        $this->newLine();
        $this->info("=== {$name} ===");
        $this->line("全体: {$total}");
        $this->line("対象（マスタに無い (prefecture, city)）: {$target}");
        $this->line('更新'.($execute ? '' : '予定').": {$updated}");
        $this->line("解決できず: {$unresolved}");
        $this->printStages($stages);

        $this->printSamples('更新内容サンプル', $updateSamples, $updated);
        $this->printSamples('解決できなかった行', $unresolvedSamples, $unresolved);
        $this->printSamples('prefecture が address と食い違う行（変更しません・報告のみ）', $prefectureSuspects, $prefectureSuspectCount);

        return ['target' => $target, 'updated' => $updated, 'unresolved' => $unresolved, 'stages' => $stages];
    }

    /**
     * 4段のフォールバックで現行の市区町村名を決める。決まらなければ null。
     * 返す値は必ずマスタ（municipalities.full_name）の正式表記。
     *
     * @param  array{exact: array<string, string>, variant: array<string, list<string>>, names: list<array{full: string, vkey: string}>}  $index
     * @return array{city: string, stage: string}|null
     */
    private function resolve(array $index, string $prefecture, string $city, string $fromAddress): ?array
    {
        // 1. 住所から再導出した市区町村
        if ($fromAddress !== '') {
            $hit = $index['exact'][self::normalizeForMatch($fromAddress)] ?? null;
            if ($hit !== null) {
                return ['city' => $hit, 'stage' => self::STAGE_ADDRESS];
            }
        }

        // 2. city の先頭に混入した都道府県名を剥がす（「熊本県熊本市南区」→「熊本市南区」）
        $stripped = '';
        if ($prefecture !== '' && $city !== '' && str_starts_with($city, $prefecture)) {
            $stripped = trim(mb_substr($city, mb_strlen($prefecture)));

            if ($stripped !== '') {
                $hit = $index['exact'][self::normalizeForMatch($stripped)] ?? null;
                if ($hit !== null) {
                    return ['city' => $hit, 'stage' => self::STAGE_STRIP_PREFECTURE];
                }
            }
        }

        /** @var list<string> $candidates 以降の段で使う候補（優先順） */
        $candidates = [];
        foreach ([$fromAddress, $stripped, $city] as $candidate) {
            if ($candidate !== '' && ! in_array($candidate, $candidates, true)) {
                $candidates[] = $candidate;
            }
        }

        // 3. 異体字を候補・マスタの双方に適用して照合（曖昧なら採用しない）
        foreach ($candidates as $candidate) {
            $hits = $index['variant'][self::variantKey($candidate)] ?? [];
            if (count($hits) === 1) {
                return ['city' => $hits[0], 'stage' => self::STAGE_VARIANT];
            }
        }

        // 4. 同一都道府県内で full_name が候補で終わるものを探す。
        //    ちょうど1件のときだけ採用する（2件以上から選ぶ処理は入れない）。
        foreach ($candidates as $candidate) {
            $needle = self::variantKey($candidate);
            if ($needle === '') {
                continue;
            }

            $hits = [];
            foreach ($index['names'] as $entry) {
                if (str_ends_with($entry['vkey'], $needle)) {
                    $hits[$entry['full']] = true;
                }
            }

            if (count($hits) === 1) {
                return ['city' => (string) array_key_first($hits), 'stage' => self::STAGE_SUFFIX];
            }
        }

        return null;
    }

    private function unresolvedLine(mixed $id, string $prefecture, string $city, string $address, string $note): string
    {
        return sprintf(
            '#%s pref=%s / city=%s / address=%s / %s',
            (string) $id,
            $prefecture === '' ? '(なし)' : $prefecture,
            $city === '' ? '(空)' : $city,
            $address === '' ? '(なし)' : $address,
            $note
        );
    }

    /**
     * @param  array<string, int>  $stages
     */
    private function printStages(array $stages): void
    {
        if (array_sum($stages) === 0) {
            return;
        }

        $parts = [];
        foreach (self::STAGE_LABELS as $stage => $label) {
            $parts[] = "{$label}: {$stages[$stage]}";
        }

        $this->line('解決の内訳 — '.implode(' / ', $parts));
    }

    /**
     * @param  list<string>  $samples
     */
    private function pushSample(array &$samples, string $line, int $limit): void
    {
        if (count($samples) < $limit) {
            $samples[] = $line;
        }
    }

    /**
     * @param  list<string>  $samples
     */
    private function printSamples(string $title, array $samples, int $count): void
    {
        if ($samples === []) {
            return;
        }

        $this->newLine();
        $this->line("[{$title}] ".count($samples).' / '.$count.'件を表示');
        foreach ($samples as $line) {
            $this->line('  '.$line);
        }
    }

    /**
     * municipalities を prefecture ごとの索引に読み込む。
     *   exact   : 正規化済み full_name => 正式表記
     *   variant : 異体字も畳んだキー => 正式表記の一覧（1件のときだけ採用する）
     *   names   : 末尾一致の走査用（正式表記と異体字畳み込み済みキー）
     * 更新時は必ずマスタ側の正式表記を書き込む。
     *
     * @return array<string, array{exact: array<string, string>, variant: array<string, list<string>>, names: list<array{full: string, vkey: string}>}>
     */
    private function loadMunicipalities(): array
    {
        $master = [];

        foreach (DB::table('municipalities')->select('prefecture', 'full_name')->cursor() as $row) {
            $prefecture = (string) $row->prefecture;
            $fullName = (string) $row->full_name;
            $key = self::normalizeForMatch($fullName);
            if ($prefecture === '' || $key === '') {
                continue;
            }

            $vkey = self::variantKey($fullName);

            $master[$prefecture]['exact'][$key] = $fullName;
            if (! in_array($fullName, $master[$prefecture]['variant'][$vkey] ?? [], true)) {
                $master[$prefecture]['variant'][$vkey][] = $fullName;
            }
            $master[$prefecture]['names'][] = ['full' => $fullName, 'vkey' => $vkey];
        }

        return $master;
    }

    /**
     * 照合用の正規化: 「ヶ」→「ケ」の表記ゆれ吸収 + 空白除去。
     * AddressParser の municipalities 照合と同じ規則にそろえる。
     */
    private static function normalizeForMatch(string $s): string
    {
        $s = str_replace('ヶ', 'ケ', $s);

        return preg_replace('/[\s　]+/u', '', $s) ?? $s;
    }

    /**
     * 異体字まで畳んだ照合キー。候補側・マスタ側の双方に同じものを適用する。
     */
    private static function variantKey(string $s): string
    {
        return strtr(self::normalizeForMatch($s), self::VARIANT_CHARS);
    }
}
