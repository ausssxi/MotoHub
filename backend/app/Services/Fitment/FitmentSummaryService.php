<?php

declare(strict_types=1);

namespace App\Services\Fitment;

use App\Models\BikeModel;
use App\Models\ModelFitment;
use Illuminate\Support\Collection;

/**
 * 車種詳細ページ用の fitments サマリ（規格・区分レベル＋内部リンク）。
 *
 * カニバリ回避が最重要：battery/plug の品番（recommended_part_no・compatibles）は
 * 一切出さない。区分（voltage/type/heat/plugs/jaso/capacity）と行数だけで要約し、
 * 「型番」の勝負ワードは適合表ページに一本化する。
 * ※ oil の recommended_part_no は「粘度(10W-30等)」＝型番ではないため表示可。
 */
final class FitmentSummaryService
{
    /**
     * @return array<int,array{task:string,label:string,summary:string,anchor:string,url:string}>
     */
    public function forModel(BikeModel $model): array
    {
        if (empty($model->slug)) {
            return [];
        }

        $rowsByTask = ModelFitment::query()
            ->where('bike_model_id', $model->id)
            ->verified()
            ->get()
            ->groupBy('task');

        $out = [];
        // 表示順は config('fitments.tasks') のキー順（task追加時に自動で行が増える）
        foreach (array_keys(config('fitments.tasks', [])) as $task) {
            $rows = $rowsByTask->get($task);
            if (! $rows || $rows->isEmpty()) {
                continue;
            }
            $out[] = [
                'task' => $task,
                'label' => config("fitments.tasks.{$task}.label", $task),
                'summary' => $this->summarize($task, $rows),
                'anchor' => $this->anchor($task, (string) $model->name),
                'url' => route('fitments.show', ['bikeModel' => $model->slug, 'task' => $task]),
            ];
        }

        return $out;
    }

    /**
     * 規格サマリ文字列を組み立てる（型番非依存）。行間で値が違うキーは「型式による」。
     */
    private function summarize(string $task, Collection $rows): string
    {
        $count = $rows->count();

        $parts = match ($task) {
            'battery' => [
                $this->field($rows, 'spec.voltage', fn ($v) => $v, '電圧は型式による'),
                $this->field($rows, 'spec.type', fn ($v) => $v, 'タイプは型式による'),
            ],
            'plug' => [
                $this->field($rows, 'spec.heat', fn ($v) => "熱価{$v}番", '熱価は型式による'),
                $this->field($rows, 'spec.plugs', fn ($v) => "{$v}本", '本数は型式による'),
            ],
            'oil' => [
                $this->field($rows, 'spec.jaso', fn ($v) => "JASO {$v}", 'JASOは型式による'),
                $this->field($rows, 'recommended_part_no', fn ($v) => $v, '粘度は型式による'), // 粘度＝型番ではない
                $this->field($rows, 'spec.capacity_change', fn ($v) => "交換時{$v}", '交換量は型式による'),
            ],
            default => [],
        };

        $parts = array_values(array_filter($parts));

        if ($parts === []) {
            return "適合表を掲載中（{$count}型式）";
        }

        // battery/plug は型式数を添える（§4 例に準拠）。oil は規格のみ。
        $suffix = in_array($task, ['battery', 'plug'], true) ? "｜{$count}型式を掲載" : '';

        return implode('・', $parts).$suffix;
    }

    /**
     * 行群からキーの値を集約。全行ユニーク→その値、行間で相違→$varies、全欠損→null。
     */
    private function field(Collection $rows, string $path, callable $fmt, string $varies): ?string
    {
        $vals = $rows
            ->map(fn ($r) => data_get($r, $path))
            ->map(fn ($v) => is_string($v) ? trim($v) : $v)
            ->filter(fn ($v) => $v !== null && $v !== '')
            ->values();

        if ($vals->isEmpty()) {
            return null;
        }
        if ($vals->unique()->count() > 1) {
            return $varies;
        }

        return $fmt($vals->first());
    }

    /**
     * 検索語を含めたアンカーテキスト（リンクジュースの要）。
     */
    private function anchor(string $task, string $name): string
    {
        return match ($task) {
            'battery' => "{$name}のバッテリー型番・適合表を見る",
            'plug' => "{$name}のプラグ品番・適合表を見る",
            'oil' => "{$name}のオイル量・粘度を見る",
            default => "{$name}の".config("fitments.tasks.{$task}.label", $task).'適合表を見る',
        };
    }
}
