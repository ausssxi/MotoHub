<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\DrivingSchool;
use Illuminate\Console\Command;

/**
 * 指定自動車教習所（二輪校）の CSV 取り込み。
 *
 * - キーは (prefecture_slug, name)。既存は更新・無ければ新規。全削除→再投入は絶対にしない。
 * - verified_at が空の行は null（未確認＝非公開）として保存する。
 * - --dry-run では DB を一切変更せず、差分だけを出力する。
 */
class ImportDrivingSchools extends Command
{
    protected $signature = 'schools:import {path : CSVファイルのパス} {--dry-run : 書き込まずに差分だけ表示}';

    protected $description = '二輪教習に対応した指定自動車教習所の CSV を取り込む';

    /** CSV ヘッダ（この順で固定）。 */
    private const COLUMNS = [
        'prefecture', 'prefecture_slug', 'city', 'name',
        'official_url', 'futsuu_nirin', 'oogata_nirin', 'source_url', 'verified_at', 'status',
    ];

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

        $created = 0;
        $updated = 0;
        $unchanged = 0;
        $skipped = 0;
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

            $error = $this->validateRow($data);
            if ($error !== null) {
                $skipped++;
                $this->warn("行{$lineNo}: スキップ（{$error}）");

                continue;
            }

            $attrs = [
                'prefecture' => $data['prefecture'],
                'city' => $data['city'],
                'official_url' => $data['official_url'] !== '' ? $data['official_url'] : null,
                'futsuu_nirin' => $data['futsuu_nirin'] === '1',
                'oogata_nirin' => $data['oogata_nirin'] === '1',
                'source_url' => $data['source_url'],
                'verified_at' => $data['verified_at'] !== '' ? $data['verified_at'] : null,
                'status' => $data['status'] !== '' ? $data['status'] : DrivingSchool::STATUS_OPEN,
            ];

            $existing = DrivingSchool::query()
                ->where('prefecture_slug', $data['prefecture_slug'])
                ->where('name', $data['name'])
                ->first();

            if ($existing === null) {
                $created++;
                $this->line("新規: {$data['prefecture_slug']} / {$data['name']}");

                if (! $dryRun) {
                    DrivingSchool::create(array_merge($attrs, [
                        'prefecture_slug' => $data['prefecture_slug'],
                        'name' => $data['name'],
                    ]));
                }

                continue;
            }

            $diff = $this->diff($existing, $attrs);
            if ($diff === []) {
                $unchanged++;

                continue;
            }

            $updated++;
            $this->line("更新: {$data['prefecture_slug']} / {$data['name']}");
            foreach ($diff as $col => [$from, $to]) {
                $this->line("      - {$col}: {$from} → {$to}");
            }

            if (! $dryRun) {
                $existing->fill($attrs)->save();
            }
        }

        fclose($handle);

        $this->newLine();
        $this->info(sprintf(
            '%s新規: %d件 / 更新: %d件 / 変更なし: %d件 / スキップ: %d件',
            $dryRun ? '[dry-run] ' : '',
            $created,
            $updated,
            $unchanged,
            $skipped,
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

    /** 1行のバリデーション。問題があれば理由文字列、無ければ null を返す。 */
    private function validateRow(array $d): ?string
    {
        foreach (['prefecture', 'prefecture_slug', 'city', 'name', 'source_url'] as $req) {
            if ($d[$req] === '') {
                return "{$req} が空です";
            }
        }

        if (! preg_match('/^[a-z]+$/', $d['prefecture_slug'])) {
            return "prefecture_slug が小文字英字のみではありません: {$d['prefecture_slug']}";
        }

        foreach (['futsuu_nirin', 'oogata_nirin'] as $flag) {
            if ($d[$flag] !== '0' && $d[$flag] !== '1') {
                return "{$flag} は 0 または 1 のみ: {$d[$flag]}";
            }
        }

        if ($d['futsuu_nirin'] === '0' && $d['oogata_nirin'] === '0') {
            return '普通二輪・大型二輪ともに0（二輪非対応校）です';
        }

        if ($d['verified_at'] !== '' && ! $this->isValidDate($d['verified_at'])) {
            return "verified_at が Y-m-d 形式ではありません: {$d['verified_at']}";
        }

        // status は空なら open 扱い。非空なら許可値のみ。
        if ($d['status'] !== '' && ! in_array($d['status'], DrivingSchool::STATUSES, true)) {
            return 'status は '.implode(' / ', DrivingSchool::STATUSES)." のみ: {$d['status']}";
        }

        return null;
    }

    /** Y-m-d 形式かどうか（実在する日付か含めて判定）。 */
    private function isValidDate(string $value): bool
    {
        $dt = \DateTime::createFromFormat('Y-m-d', $value);

        return $dt !== false && $dt->format('Y-m-d') === $value;
    }

    /**
     * 既存レコードと新しい値の差分。
     *
     * @return array<string, array{0: string, 1: string}> col => [from, to]
     */
    private function diff(DrivingSchool $existing, array $attrs): array
    {
        $diff = [];
        foreach ($attrs as $col => $new) {
            $old = $existing->getAttribute($col);

            // boolean / date / null を人が読める文字列に正規化して比較。
            $oldStr = $this->normalize($col, $old);
            $newStr = $this->normalize($col, $new);

            if ($oldStr !== $newStr) {
                $diff[$col] = [$oldStr, $newStr];
            }
        }

        return $diff;
    }

    /** 比較・表示用に値を文字列化する。 */
    private function normalize(string $col, mixed $value): string
    {
        if ($col === 'verified_at') {
            if ($value === null || $value === '') {
                return '(null)';
            }

            return $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : (string) $value;
        }

        if ($col === 'futsuu_nirin' || $col === 'oogata_nirin') {
            return $value ? '1' : '0';
        }

        if ($value === null || $value === '') {
            return '(null)';
        }

        return (string) $value;
    }
}
