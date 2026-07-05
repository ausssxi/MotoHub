<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BikeModel;
use App\Models\ModelFitment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * 車種×作業（型番）適合表のCSVインポート。
 *
 * 取り込み戦略：モデル×task単位の全置換（CSVが常にsource of truth・完全冪等）。
 * データは人手キュレーションのCSVのみ（適合検索サイトのスクレイピングは一切しない）。
 */
final class FitmentsImport extends Command
{
    protected $signature = 'fitments:import {path} {--dry-run}';

    protected $description = '車種×作業の適合CSVを取り込む（モデル×task全置換・冪等）';

    private const COLUMNS = 16;

    private const SLUG_RE = '/^[a-z0-9]+(-[a-z0-9]+)*$/';

    /** @var array<int,string> */
    private array $warnings = [];

    public function handle(): int
    {
        $path = (string) $this->argument('path');
        $dryRun = (bool) $this->option('dry-run');

        if (! is_file($path) || ! is_readable($path)) {
            $this->error("CSVが読めません: {$path}");

            return self::FAILURE;
        }

        $allowedTasks = array_keys(config('fitments.tasks', []));

        $handle = fopen($path, 'r');
        fgetcsv($handle); // ヘッダ行を読み飛ばす

        $rows = [];            // 有効行（グループ化前）
        $lineNo = 1;
        while (($cols = fgetcsv($handle)) !== false) {
            $lineNo++;
            // 空行スキップ
            if (count($cols) === 1 && trim((string) $cols[0]) === '') {
                continue;
            }
            $row = $this->validateRow($cols, $lineNo, $allowedTasks);
            if ($row !== null) {
                $rows[] = $row;
            }
        }
        fclose($handle);

        // モデル×task でグループ化（全置換の単位）
        $groups = [];
        foreach ($rows as $row) {
            $groups[$row['bike_model_id'].'|'.$row['task']][] = $row;
        }

        // slug 計画（モデル単位）
        $slugPlans = $this->resolveSlugPlans($rows);

        // 適用（dry-run は最後にロールバック）
        $planRows = [];
        $importedCount = 0;
        $publishedCount = 0;

        DB::beginTransaction();
        try {
            // slug 反映
            foreach ($slugPlans as $modelId => $plan) {
                if ($plan['action'] === 'set') {
                    BikeModel::whereKey($modelId)->update(['slug' => $plan['slug']]);
                }
            }

            foreach ($groups as $key => $groupRows) {
                [$modelId, $task] = explode('|', $key);
                $deleteCount = ModelFitment::where('bike_model_id', $modelId)->where('task', $task)->count();
                ModelFitment::where('bike_model_id', $modelId)->where('task', $task)->delete();

                foreach ($groupRows as $row) {
                    ModelFitment::create($row['attributes']);
                    $importedCount++;
                    if ($row['attributes']['verified_at'] !== null) {
                        $publishedCount++;
                    }
                }

                $planRows[] = [
                    $row['model_name'] ?? $modelId,
                    $task,
                    $deleteCount,
                    count($groupRows),
                    $slugPlans[$modelId]['display'] ?? '-',
                ];
            }

            if ($dryRun) {
                DB::rollBack();
            } else {
                DB::commit();
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('取り込み中にエラー: '.$e->getMessage());

            return self::FAILURE;
        }

        // 出力
        if ($dryRun) {
            $this->comment('=== DRY-RUN（DBは変更していません）===');
        }
        $this->table(['モデル', 'task', 'delete', 'insert', 'slug'], $planRows ?: [['(対象なし)', '', '', '', '']]);

        $this->info("取込行数: {$importedCount} / 公開対象(verified): {$publishedCount} / skip: ".count($this->warnings));
        if ($this->warnings) {
            $this->comment('--- 警告 ---');
            foreach ($this->warnings as $w) {
                $this->line('  '.$w);
            }
        }

        // 警告があれば非ゼロ終了で区別
        return $this->warnings ? 2 : self::SUCCESS;
    }

    /**
     * 1行を検証し、正常なら ['bike_model_id','task','model_name','attributes'] を返す。異常は null＋警告。
     */
    private function validateRow(array $cols, int $lineNo, array $allowedTasks): ?array
    {
        if (count($cols) !== self::COLUMNS) {
            $this->warnings[] = ("行{$lineNo}: 列数不一致（".count($cols).'/'.self::COLUMNS.'）→ skip');

            return null;
        }

        [$modelId, $nameCheck, $modelSlug, $task, $frameCode, $yearRange,
            $oem, $recommended, $compatibles, $spec,
            $s1name, $s1url, $s2name, $s2url, $verifiedAt, $note] = array_map('trim', $cols);

        $model = BikeModel::find($modelId);
        if (! $model) {
            $this->warnings[] = ("行{$lineNo}: bike_model_id={$modelId} が存在しない → skip");

            return null;
        }
        if (trim((string) $model->name) !== $nameCheck) {
            $this->warnings[] = ("行{$lineNo}: model_name_check不一致（DB「{$model->name}」≠CSV「{$nameCheck}」）→ skip");

            return null;
        }
        if (! in_array($task, $allowedTasks, true)) {
            $this->warnings[] = ("行{$lineNo}: 未対応task「{$task}」→ skip");

            return null;
        }
        if ($recommended === '') {
            $this->warnings[] = ("行{$lineNo}: recommended_part_no が空 → skip");

            return null;
        }
        // 日付
        $verified = null;
        if ($verifiedAt !== '') {
            $d = \DateTime::createFromFormat('Y-m-d', $verifiedAt);
            if (! $d || $d->format('Y-m-d') !== $verifiedAt) {
                $this->warnings[] = ("行{$lineNo}: verified_at 日付不正「{$verifiedAt}」→ skip");

                return null;
            }
            $verified = $verifiedAt;
        }
        // URL
        foreach ([['source_1_url', $s1url], ['source_2_url', $s2url]] as [$label, $url]) {
            if ($url !== '' && ! filter_var($url, FILTER_VALIDATE_URL)) {
                $this->warnings[] = ("行{$lineNo}: {$label} URL不正「{$url}」→ skip");

                return null;
            }
        }
        // slug形式
        if ($modelSlug === '' || ! preg_match(self::SLUG_RE, $modelSlug)) {
            $this->warnings[] = ("行{$lineNo}: model_slug 形式不正「{$modelSlug}」→ skip");

            return null;
        }

        return [
            'bike_model_id' => (int) $modelId,
            'task' => $task,
            'model_name' => $model->name,
            'model_slug' => $modelSlug,
            'model_current_slug' => $model->slug,
            'attributes' => [
                'bike_model_id' => (int) $modelId,
                'task' => $task,
                'frame_code' => $frameCode,
                'year_range' => $yearRange,
                'oem_part_no' => $oem !== '' ? $oem : null,
                'recommended_part_no' => $recommended,
                'compatible_part_nos' => $this->parseCompatibles($compatibles),
                'spec' => $this->parseSpec($spec),
                'source_1_name' => $s1name !== '' ? $s1name : null,
                'source_1_url' => $s1url !== '' ? $s1url : null,
                'source_2_name' => $s2name !== '' ? $s2name : null,
                'source_2_url' => $s2url !== '' ? $s2url : null,
                'verified_at' => $verified,
                'note' => $note !== '' ? $note : null,
            ],
        ];
    }

    /**
     * `台湾ユアサ:YTX5L-BS|古河電池:FTX5L-BS` → [{"brand","part_no"}]
     */
    private function parseCompatibles(string $raw): ?array
    {
        if ($raw === '') {
            return null;
        }
        $out = [];
        foreach (explode('|', $raw) as $pair) {
            $pair = trim($pair);
            if ($pair === '' || ! str_contains($pair, ':')) {
                continue;
            }
            [$brand, $partNo] = array_map('trim', explode(':', $pair, 2));
            if ($brand !== '' && $partNo !== '') {
                $out[] = ['brand' => $brand, 'part_no' => $partNo];
            }
        }

        return $out ?: null;
    }

    /**
     * `voltage=12V;capacity=4Ah;type=VRLA` → {key:value}
     */
    private function parseSpec(string $raw): ?array
    {
        if ($raw === '') {
            return null;
        }
        $out = [];
        foreach (explode(';', $raw) as $kv) {
            $kv = trim($kv);
            if ($kv === '' || ! str_contains($kv, '=')) {
                continue;
            }
            [$k, $v] = array_map('trim', explode('=', $kv, 2));
            if ($k !== '') {
                $out[$k] = $v;
            }
        }

        return $out ?: null;
    }

    /**
     * モデル単位の slug 反映計画。既存slugと異なれば変更せず警告、他モデルと衝突すれば公開不可警告。
     *
     * @return array<int,array{action:string,slug:?string,display:string}>
     */
    private function resolveSlugPlans(array $rows): array
    {
        $byModel = [];
        foreach ($rows as $row) {
            $byModel[$row['bike_model_id']] ??= $row;
        }

        $plans = [];
        foreach ($byModel as $modelId => $row) {
            $target = $row['model_slug'];
            $current = $row['model_current_slug'];

            // 他モデルが同じslugを持つ → URLが曖昧になり公開不可
            $collision = BikeModel::where('slug', $target)->where('id', '!=', $modelId)->exists();
            if ($collision) {
                $this->warnings[] = ("model #{$modelId}「{$row['model_name']}」: slug「{$target}」は他モデルと重複 → slug設定せず（公開URLが曖昧）");
                $plans[$modelId] = ['action' => 'skip', 'slug' => null, 'display' => "衝突:{$target}"];

                continue;
            }

            if (empty($current)) {
                $plans[$modelId] = ['action' => 'set', 'slug' => $target, 'display' => "set:{$target}"];
            } elseif ($current === $target) {
                $plans[$modelId] = ['action' => 'keep', 'slug' => $current, 'display' => $current];
            } else {
                $this->warnings[] = ("model #{$modelId}「{$row['model_name']}」: 既存slug「{$current}」≠CSV「{$target}」→ 変更せず既存を使用");
                $plans[$modelId] = ['action' => 'keep', 'slug' => $current, 'display' => "既存:{$current}"];
            }
        }

        return $plans;
    }
}
