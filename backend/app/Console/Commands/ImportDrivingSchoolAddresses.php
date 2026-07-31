<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\DrivingSchool;
use Illuminate\Console\Command;

/**
 * 既存の指定自動車教習所（driving_schools）に住所（address）だけを流し込む。
 *
 * - 照合キーは (prefecture_slug, name) の完全一致のみ。あいまい一致はしない。
 * - 該当行が無ければ「skipped(not found)」として一覧出力。新規は絶対に作らない。
 * - address が同じなら unchanged、異なれば update（旧値→新値を出力）。
 * - address が空の CSV 行は skipped(empty)。削除は一切しない。
 * - --dry-run では DB を一切変更せず、差分だけを出力する。
 *
 * ImportDrivingSchools（schools:import）と同じ作法（BOM除去・ヘッダ行スキップ・
 * 位置ベース・dry-run ガード・件数集計の出力）に揃えてある。
 */
class ImportDrivingSchoolAddresses extends Command
{
    protected $signature = 'schools:import-address {path : CSVファイルのパス} {--dry-run : 書き込まずに差分だけ表示}';

    protected $description = '既存の教習所レコードに住所（address）を取り込む（照合キー = prefecture_slug + name）';

    /** CSV ヘッダ（この順で固定・3列）。 */
    private const COLUMNS = ['prefecture_slug', 'name', 'address'];

    public function handle(): int
    {
        $path = $this->argument('path');
        $dryRun = (bool) $this->option('dry-run');

        if (! is_file($path) || ! is_readable($path)) {
            $this->error("CSV が見つかりません: {$path}");

            return self::FAILURE;
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            $this->error("CSV を開けません: {$path}");

            return self::FAILURE;
        }

        $updated = 0;
        $unchanged = 0;
        $skippedEmpty = 0;
        $skippedNotFound = 0;
        $lineNo = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $lineNo++;

            // ヘッダ行（1行目）。BOM を除去して照合。
            if ($lineNo === 1) {
                if (isset($row[0])) {
                    $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', $row[0]);
                }

                continue;
            }

            // 空行はスキップ（件数に数えない）。
            if ($row === [null] || (count($row) === 1 && trim((string) $row[0]) === '')) {
                continue;
            }

            $data = $this->mapRow($row);

            // 照合キーが欠けている行は not found 扱い（新規は作らない）。
            if ($data['prefecture_slug'] === '' || $data['name'] === '') {
                $skippedNotFound++;
                $this->warn("skipped(not found): {$data['prefecture_slug']} / {$data['name']}");

                continue;
            }

            if ($data['address'] === '') {
                $skippedEmpty++;
                $this->line("skipped(empty): {$data['prefecture_slug']} / {$data['name']}");

                continue;
            }

            $existing = DrivingSchool::query()
                ->where('prefecture_slug', $data['prefecture_slug'])
                ->where('name', $data['name'])
                ->first();

            if ($existing === null) {
                $skippedNotFound++;
                $this->warn("skipped(not found): {$data['prefecture_slug']} / {$data['name']}");

                continue;
            }

            $old = (string) ($existing->address ?? '');
            if ($old === $data['address']) {
                $unchanged++;

                continue;
            }

            $updated++;
            $this->line("更新: {$data['prefecture_slug']} / {$data['name']}");
            $this->line('      - address: '.($old === '' ? '(null)' : $old)." → {$data['address']}");

            if (! $dryRun) {
                $existing->address = $data['address'];
                $existing->save();
            }
        }

        fclose($handle);

        $this->newLine();
        $this->info(sprintf(
            '%s新規: 0件 / 更新: %d件 / 変更なし: %d件 / スキップ(空): %d件 / スキップ(該当なし): %d件',
            $dryRun ? '[dry-run] ' : '',
            $updated,
            $unchanged,
            $skippedEmpty,
            $skippedNotFound,
        ));

        return self::SUCCESS;
    }

    /** CSV の1行を列名付き連想配列にする（欠損列は空文字）。 */
    private function mapRow(array $row): array
    {
        $data = [];
        foreach (self::COLUMNS as $i => $col) {
            $data[$col] = isset($row[$i]) ? trim((string) $row[$i]) : '';
        }

        return $data;
    }
}
