<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BikeModel;
use App\Support\ConsumableCost;
use Illuminate\Console\Command;

/**
 * 指定車種の消耗品(battery/plug)年額の内訳を1画面で出す（人が数字の妥当性を判断するための確認用）。
 * DB は SELECT のみ・変更なし。画面反映は別タスクで、これはその前段の目視用。
 */
final class ConsumableCostReport extends Command
{
    protected $signature = 'fitment:consumable-cost
        {slug : 車種の slug（見つからなければ数値 id でも可）}
        {--km= : 年間走行距離（未指定は config/consumables.default_annual_km）}';

    protected $description = '指定車種の消耗品(battery/plug)年額の内訳（単価・サイクル・計算過程・小計・未算入項目）を出す';

    public function handle(): int
    {
        $arg = (string) $this->argument('slug');
        $model = BikeModel::where('slug', $arg)->first()
            ?? (is_numeric($arg) ? BikeModel::find((int) $arg) : null);

        if ($model === null) {
            $this->error("車種が見つかりません: {$arg}（slug または id を指定してください）");

            return self::FAILURE;
        }

        $km = $this->option('km') !== null
            ? max(0, (int) $this->option('km'))
            : (int) config('consumables.default_annual_km');

        $data = ConsumableCost::forModel($model, $km);

        $this->newLine();
        $this->line(sprintf(
            '%s（%s）／ 年間走行距離 %skm',
            $model->name,
            $model->slug ?? (string) $model->id,
            number_format($data['annual_km'])
        ));

        $subtotal = 0;
        foreach ($data['items'] as $item) {
            if ($item['available']) {
                $subtotal += (int) $item['annual_cost'];
                $this->line('  '.$this->formatAvailable($item, $data['annual_km']));
                // 算定できていても注記（本数仮定・ロングライフ等）があれば添える。
                if (! empty($item['reason'])) {
                    $this->line('               └ '.$item['reason']);
                }
            } else {
                $this->line(sprintf(
                    '  %-10s %-10s 算定不可: %s',
                    $item['label'],
                    $item['part_no'] ?? '(品番なし)',
                    $item['reason'] ?? '不明'
                ));
            }
        }

        $this->line('  ──────────────────────────────');
        $this->line(sprintf('  %-10s %s円/年（算定できた消耗品のみ）', '小計', number_format($subtotal)));
        if (! empty($data['uncounted'])) {
            $this->line('  未算入: '.implode('、', $data['uncounted']));
        }
        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * 単価・サイクル・計算過程が読める1行にする。
     *
     * @param  array<string, mixed>  $item
     */
    private function formatAvailable(array $item, int $annualKm): string
    {
        if ($item['key'] === 'battery') {
            // 例: バッテリー  YTZ7S      3,100円 ÷ 3年 = 1,033円/年
            return sprintf(
                '%-10s %-10s %s円 ÷ %s = %s円/年',
                $item['label'],
                $item['part_no'] ?? '(品番なし)',
                number_format((int) $item['unit_price']),
                $item['cycle'],
                number_format((int) $item['annual_cost'])
            );
        }

        // プラグ 例: プラグ      CR8EH-9    902円 × 1本 ÷ 5,000km × 3,000km = 541円/年
        return sprintf(
            '%-10s %-10s %s円 × %d本 ÷ %s × %skm = %s円/年',
            $item['label'],
            $item['part_no'] ?? '(品番なし)',
            number_format((int) $item['unit_price']),
            (int) ($item['plugs'] ?? 1),
            $item['cycle'],
            number_format($annualKm),
            number_format((int) $item['annual_cost'])
        );
    }
}
