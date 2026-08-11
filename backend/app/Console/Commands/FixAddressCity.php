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
 * 一切触れない。対象行は address から AddressParser（廃止・改称の補正表つき）で市区町村を
 * 取り直し、それがマスタに実在するときだけ更新する。prefecture は変更しない
 * （誤りが疑われる行は報告のみ）。
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

    protected $description = 'municipalities に存在しない (prefecture, city) の行を、address から再導出して是正します（既定は dry-run）';

    /** 表示サンプルの上限（更新内容・解決できなかった行のそれぞれ） */
    private const SAMPLE_LIMIT = 20;

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
        $this->line('自治体マスタ: '.array_sum(array_map('count', $master)).'件');

        $names = $table === 'all' ? array_keys(self::TABLES) : [$table];

        $totalTarget = 0;
        $totalUpdated = 0;
        $totalUnresolved = 0;

        foreach ($names as $name) {
            $result = $this->processTable($name, $parser, $master, $execute, $chunk);
            $totalTarget += $result['target'];
            $totalUpdated += $result['updated'];
            $totalUnresolved += $result['unresolved'];
        }

        if (count($names) > 1) {
            $this->newLine();
            $this->info('=== 合計 ===');
            $this->line("対象: {$totalTarget} / 更新".($execute ? '' : '予定').": {$totalUpdated} / 解決できず: {$totalUnresolved}");
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, array<string, string>>  $master
     * @return array{target: int, updated: int, unresolved: int}
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

        /** @var list<string> $updateSamples */
        $updateSamples = [];
        /** @var list<string> $unresolvedSamples */
        $unresolvedSamples = [];
        /** @var list<string> $prefectureSuspects */
        $prefectureSuspects = [];

        $query->chunkById($chunk, function ($rows) use (
            $parser, $master, $execute,
            &$target, &$updated, &$unresolved, &$prefectureSuspectCount,
            &$updateSamples, &$unresolvedSamples, &$prefectureSuspects
        ) {
            foreach ($rows as $row) {
                $prefecture = trim((string) $row->prefecture);
                $city = trim((string) $row->city);

                // 表記ゆれ（龍ヶ崎市/龍ケ崎市 等）だけの差はマスタに実在するとみなし、触れない。
                if ($prefecture !== '' && $city !== ''
                    && isset($master[$prefecture][self::normalizeForMatch($city)])
                ) {
                    continue;
                }

                $target++;

                $current = ($prefecture === '' ? '(県なし)' : $prefecture).' / '.($city === '' ? '(city空)' : $city);
                $address = trim((string) $row->address);

                if ($address === '') {
                    $unresolved++;
                    $this->pushSample($unresolvedSamples, "#{$row->id} {$current} / address なし");

                    continue;
                }

                $parsed = $parser->parseWithCorrections($address);

                // prefecture は変更しない。食い違いは報告だけする。
                if ($prefecture !== '' && $parsed['prefecture'] !== '' && $parsed['prefecture'] !== $prefecture) {
                    $prefectureSuspectCount++;
                    $this->pushSample(
                        $prefectureSuspects,
                        "#{$row->id} 現在={$prefecture} / address由来={$parsed['prefecture']} / address={$address}"
                    );
                }

                // 行の prefecture は動かさないので、その都道府県の中でだけマスタを引く。
                $canonical = $parsed['city'] === ''
                    ? null
                    : ($master[$prefecture][self::normalizeForMatch($parsed['city'])] ?? null);

                if ($canonical === null || $canonical === $city) {
                    $unresolved++;
                    $this->pushSample(
                        $unresolvedSamples,
                        "#{$row->id} {$current} / address={$address} / 再導出=".($parsed['city'] === '' ? '(取得できず)' : $parsed['city'])
                    );

                    continue;
                }

                $updated++;
                $this->pushSample($updateSamples, "#{$row->id} {$prefecture} 「{$city}」 → 「{$canonical}」");

                if ($execute) {
                    $row->updateQuietly(['city' => $canonical]);
                }
            }
        });

        $this->newLine();
        $this->info("=== {$name} ===");
        $this->line("全体: {$total}");
        $this->line("対象（マスタに無い (prefecture, city)）: {$target}");
        $this->line('更新'.($execute ? '' : '予定').": {$updated}");
        $this->line("解決できず: {$unresolved}");

        $this->printSamples('更新内容サンプル', $updateSamples, $updated);
        $this->printSamples('解決できなかった行', $unresolvedSamples, $unresolved);
        $this->printSamples('prefecture が address と食い違う行（変更しません・報告のみ）', $prefectureSuspects, $prefectureSuspectCount);

        return ['target' => $target, 'updated' => $updated, 'unresolved' => $unresolved];
    }

    /**
     * @param  list<string>  $samples
     */
    private function pushSample(array &$samples, string $line): void
    {
        if (count($samples) < self::SAMPLE_LIMIT) {
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
     * municipalities を prefecture ごとの「正規化済み full_name => 正式表記」へ読み込む。
     * 更新時はマスタ側の正式表記を書き込む（住所の表記ゆれをマスタに寄せる）。
     *
     * @return array<string, array<string, string>>
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
            $master[$prefecture][$key] = $fullName;
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
}
