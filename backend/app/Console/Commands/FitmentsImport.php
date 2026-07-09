<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BikeModel;
use App\Models\ModelFitment;
use App\Services\Fitment\FitmentSummaryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
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
        $header = fgetcsv($handle);
        if (! is_array($header)) {
            fclose($handle);
            $this->error('CSVヘッダが読めません。');

            return self::FAILURE;
        }
        $header = array_map(fn ($h) => trim((string) $h), $header);
        $colIndex = array_flip($header);
        $headerCount = count($header);

        // 必須列の存在チェック（列位置ではなく列名ベース）
        $required = ['bike_model_id', 'model_name_check', 'model_slug', 'task', 'recommended_part_no', 'verified_at'];
        $missing = array_diff($required, array_keys($colIndex));
        if ($missing) {
            fclose($handle);
            $this->error('必須列が不足しています: '.implode(', ', $missing));

            return self::FAILURE;
        }

        // 備考の列形式判定（新: note_public/note_internal ／ 旧: note）
        $hasPublic = isset($colIndex['note_public']);
        $hasNote = isset($colIndex['note']);
        if ($hasPublic && $hasNote) {
            $this->warnings[] = 'ヘッダに note と note_public が併存 → 旧 note は安全側で note_internal へ寄せます';
        }

        $rows = [];            // 有効行（グループ化前）
        $lineNo = 1;
        while (($cols = fgetcsv($handle)) !== false) {
            $lineNo++;
            // 空行スキップ
            if (count($cols) === 1 && trim((string) $cols[0]) === '') {
                continue;
            }
            $row = $this->validateRow($cols, $lineNo, $allowedTasks, $colIndex, $headerCount, $hasPublic, $hasNote);
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

        // 適合公開状態が変わったので、店ページの適合リンクマップをピンポイント失効（cache:clearは使わない）
        if (! $dryRun) {
            Cache::forget(FitmentSummaryService::TASK_MAP_CACHE_KEY);
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
    private function validateRow(array $cols, int $lineNo, array $allowedTasks, array $colIndex, int $headerCount, bool $hasPublic, bool $hasNote): ?array
    {
        if (count($cols) !== $headerCount) {
            $this->warnings[] = ("行{$lineNo}: 列数不一致（".count($cols)."/{$headerCount}）→ skip");

            return null;
        }

        // 列名ベースの取得（列順に依存しない）
        $get = fn (string $name): string => isset($colIndex[$name], $cols[$colIndex[$name]]) ? trim((string) $cols[$colIndex[$name]]) : '';

        $modelId = $get('bike_model_id');
        $nameCheck = $get('model_name_check');
        $modelSlug = $get('model_slug');
        $task = $get('task');
        $frameCode = $get('frame_code');
        $yearRange = $get('year_range');
        $oem = $get('oem_part_no');
        $recommended = $get('recommended_part_no');
        $compatibles = $get('compatibles');
        $spec = $get('spec');
        $s1name = $get('source_1_name');
        $s1url = $get('source_1_url');
        $s2name = $get('source_2_name');
        $s2url = $get('source_2_url');
        $verifiedAt = $get('verified_at');

        // 備考: 新形式は note_public/note_internal、旧形式は note を内部へ退避（公開に漏らさない）
        if ($hasPublic) {
            $notePublic = $get('note_public');
            $noteInternal = $get('note_internal');
            if ($hasNote && ($legacy = $get('note')) !== '') {
                // note と note_public が併存する異常は安全側（内部）へ寄せる
                $noteInternal = $noteInternal !== '' ? $noteInternal.' / '.$legacy : $legacy;
            }
        } else {
            $notePublic = '';                 // 旧形式は公開noteを持たない
            $noteInternal = $get('note');     // 旧 note は内部メモへ
        }

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
                'note' => null,                                     // 旧列は今後書かない（後方互換の受け皿）
                'note_public' => $notePublic !== '' ? $notePublic : null,
                'note_internal' => $noteInternal !== '' ? $noteInternal : null,
                // 交換費用の目安（列名ベース。旧CSVに列が無ければ $get は '' を返し NULL 格納＝後方互換）
                'cost_oil_range' => ($v = $get('cost_oil_range')) !== '' ? $v : null,
                'cost_shop_range' => ($v = $get('cost_shop_range')) !== '' ? $v : null,
                'cost_diy_range' => ($v = $get('cost_diy_range')) !== '' ? $v : null,
                'cost_updated_at' => ($v = $get('cost_updated_at')) !== '' ? $v : null,
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
