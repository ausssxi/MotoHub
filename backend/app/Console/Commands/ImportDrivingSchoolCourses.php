<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\DrivingSchool;
use App\Models\DrivingSchoolCourse;
use Illuminate\Console\Command;

/**
 * 教習所の二輪コース料金 CSV の取り込み。
 *
 * - (prefecture_slug, school_name) で driving_schools を引いて driving_school_id に解決する。
 *   該当校が無い行はエラーに計上してスキップ（勝手に学校を作らない）。
 * - upsert キーはユニーク制約と同じ5列。全削除→再投入はしない。
 * - --dry-run では DB を一切変更せず、新規/更新/変更なし/エラー の件数を出す。
 */
class ImportDrivingSchoolCourses extends Command
{
    protected $signature = 'schools:import-courses {path : CSVファイルのパス} {--dry-run : 書き込まずに差分だけ表示}';

    protected $description = '教習所の二輪コース料金 CSV を取り込む';

    /** CSV ヘッダ（この順で固定）。 */
    private const COLUMNS = [
        'prefecture_slug', 'school_name', 'vehicle_class', 'transmission', 'prerequisite',
        'enrollment_type', 'price_yen', 'price_note', 'source_url', 'verified_at', 'verify_method',
    ];

    /** upsert の一致キー（ユニーク制約と同じ5列）。 */
    private const KEY_COLUMNS = [
        'driving_school_id', 'vehicle_class', 'transmission', 'prerequisite', 'enrollment_type',
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
        $errors = 0;
        $lineNo = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $lineNo++;

            if ($lineNo === 1) {
                if (isset($row[0])) {
                    $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', $row[0]);
                }

                continue;
            }

            if ($row === [null] || (count($row) === 1 && trim((string) $row[0]) === '')) {
                continue;
            }

            $data = $this->mapRow($row);

            $error = $this->validateRow($data);
            if ($error !== null) {
                $errors++;
                $this->warn("行{$lineNo}: エラー（{$error}）");

                continue;
            }

            $school = DrivingSchool::query()
                ->where('prefecture_slug', $data['prefecture_slug'])
                ->where('name', $data['school_name'])
                ->first();

            if ($school === null) {
                $errors++;
                $this->warn("行{$lineNo}: エラー（教習所が見つかりません: {$data['prefecture_slug']} / {$data['school_name']}）");

                continue;
            }

            $enrollment = $data['enrollment_type'] !== '' ? $data['enrollment_type'] : DrivingSchoolCourse::ENROLLMENT_COMMUTE;

            $keys = [
                'driving_school_id' => $school->id,
                'vehicle_class' => $data['vehicle_class'],
                'transmission' => $data['transmission'],
                'prerequisite' => $data['prerequisite'],
                'enrollment_type' => $enrollment,
            ];

            $attrs = [
                'price_yen' => $this->parsePrice($data['price_yen']),
                'price_note' => $data['price_note'] !== '' ? $data['price_note'] : null,
                'source_url' => $data['source_url'],
                'verified_at' => $data['verified_at'] !== '' ? $data['verified_at'] : null,
                'verify_method' => $data['verify_method'] !== '' ? $data['verify_method'] : DrivingSchoolCourse::VERIFY_HUMAN,
            ];

            $existing = DrivingSchoolCourse::query()->where($keys)->first();

            if ($existing === null) {
                $created++;
                $this->line("新規: {$data['prefecture_slug']} / {$data['school_name']} / ".$this->keyLabel($keys));

                if (! $dryRun) {
                    DrivingSchoolCourse::create(array_merge($keys, $attrs));
                }

                continue;
            }

            $diff = $this->diff($existing, $attrs);
            if ($diff === []) {
                $unchanged++;

                continue;
            }

            $updated++;
            $this->line("更新: {$data['prefecture_slug']} / {$data['school_name']} / ".$this->keyLabel($keys));
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
            '%s新規: %d件 / 更新: %d件 / 変更なし: %d件 / エラー: %d件',
            $dryRun ? '[dry-run] ' : '',
            $created,
            $updated,
            $unchanged,
            $errors,
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
        foreach (['prefecture_slug', 'school_name', 'vehicle_class', 'transmission', 'prerequisite', 'source_url'] as $req) {
            if ($d[$req] === '') {
                return "{$req} が空です";
            }
        }

        if (! in_array($d['vehicle_class'], DrivingSchoolCourse::VEHICLE_CLASSES, true)) {
            return 'vehicle_class は '.implode(' / ', DrivingSchoolCourse::VEHICLE_CLASSES)." のみ: {$d['vehicle_class']}";
        }

        if (! in_array($d['transmission'], DrivingSchoolCourse::TRANSMISSIONS, true)) {
            return 'transmission は '.implode(' / ', DrivingSchoolCourse::TRANSMISSIONS)." のみ: {$d['transmission']}";
        }

        if (! in_array($d['prerequisite'], DrivingSchoolCourse::PREREQUISITES, true)) {
            return 'prerequisite は '.implode(' / ', DrivingSchoolCourse::PREREQUISITES)." のみ: {$d['prerequisite']}";
        }

        if ($d['enrollment_type'] !== '' && ! in_array($d['enrollment_type'], DrivingSchoolCourse::ENROLLMENT_TYPES, true)) {
            return 'enrollment_type は '.implode(' / ', DrivingSchoolCourse::ENROLLMENT_TYPES)." のみ: {$d['enrollment_type']}";
        }

        if ($d['price_yen'] !== '' && $this->parsePrice($d['price_yen']) === false) {
            return "price_yen を整数化できません: {$d['price_yen']}";
        }

        if ($d['verified_at'] !== '' && ! $this->isValidDate($d['verified_at'])) {
            return "verified_at が Y-m-d 形式ではありません: {$d['verified_at']}";
        }

        // verify_method は空なら human 扱い。非空なら許可値のみ。
        if ($d['verify_method'] !== '' && ! in_array($d['verify_method'], DrivingSchoolCourse::VERIFY_METHODS, true)) {
            return 'verify_method は '.implode(' / ', DrivingSchoolCourse::VERIFY_METHODS)." のみ: {$d['verify_method']}";
        }

        return null;
    }

    /**
     * price_yen を整数化する。空 → null。カンマ・「円」・空白・￥ を除去して整数化。
     * 整数にできなければ false を返す（バリデーションで検出）。
     */
    private function parsePrice(string $value): int|null|false
    {
        if ($value === '') {
            return null;
        }

        $cleaned = str_replace([',', '，', '円', '￥', '¥', ' ', '　'], '', $value);

        if ($cleaned === '' || ! ctype_digit($cleaned)) {
            return false;
        }

        return (int) $cleaned;
    }

    /** Y-m-d 形式かどうか（実在する日付か含めて判定）。 */
    private function isValidDate(string $value): bool
    {
        $dt = \DateTime::createFromFormat('Y-m-d', $value);

        return $dt !== false && $dt->format('Y-m-d') === $value;
    }

    /** キー5列を人が読める1行にする（新規/更新表示用）。 */
    private function keyLabel(array $keys): string
    {
        return "{$keys['vehicle_class']}/{$keys['transmission']}/{$keys['prerequisite']}/{$keys['enrollment_type']}";
    }

    /**
     * 既存レコードと新しい値の差分。
     *
     * @return array<string, array{0: string, 1: string}> col => [from, to]
     */
    private function diff(DrivingSchoolCourse $existing, array $attrs): array
    {
        $diff = [];
        foreach ($attrs as $col => $new) {
            $oldStr = $this->normalize($col, $existing->getAttribute($col));
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

        if ($value === null || $value === '') {
            return '(null)';
        }

        return (string) $value;
    }
}
