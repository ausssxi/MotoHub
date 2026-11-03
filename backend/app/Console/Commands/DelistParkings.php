<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BikeParking;
use Illuminate\Console\Command;

/**
 * 施設・自治体・管理者の依頼で駐車場を「非公開」にする運用コマンド。
 *
 * 2つのモードを持つ:
 *   1) 検索モード（--search / 既定）: 名前・住所で候補を探して id を一覧表示する。読み取り専用。
 *      本番ではまずこれで対象の id を確定させてから非公開にする。
 *   2) 非公開モード（--id）: 指定 id を is_active=false にし、delisted_at / delisted_reason を記録する。
 *      データは削除しない（記録を残す方針）。--dry-run で先に結果を確認する。
 *
 * 非公開にすると詳細ページは 404（ParkingController::show の abort_if）になり、
 * 検索・一覧・地図ピン・サイトマップ・近くの駐車場からも消える（いずれも scopeActive 経由）。
 * delisted_at が立っている行は週次取り込み（parking:import-jmpsa 等）で公開へ戻らない。
 *
 * ★is_active の自動化は「非公開方向のみ」。公開（--relist）は人が明示したときだけ。
 */
final class DelistParkings extends Command
{
    protected $signature = 'parking:delist
        {--search=* : 名前・住所で検索する語（複数指定で AND 検索）。読み取り専用で id を表示}
        {--id=* : 非公開にする駐車場 ID（複数指定可）}
        {--reason= : 掲載削除の理由（--id 指定時は必須。依頼元・事情を残す）}
        {--relist : 非公開を取り消して公開に戻す（delisted_at / delisted_reason をクリア）}
        {--dry-run : 書き込まず結果だけ表示}';

    protected $description = '依頼に基づき駐車場を非公開にする（検索モードで id 確認 → --id で非公開）。データは消さず記録を残す。';

    public function handle(): int
    {
        /** @var array<int, string> $search */
        $search = array_values(array_filter((array) $this->option('search'), static fn ($v) => trim((string) $v) !== ''));
        /** @var array<int, string> $ids */
        $ids = array_values(array_filter((array) $this->option('id'), static fn ($v) => trim((string) $v) !== ''));

        if ($ids === []) {
            return $this->searchMode($search);
        }

        return $this->mutateMode(array_map('intval', $ids));
    }

    /**
     * 検索モード: 名前・住所で候補を探して id を表示する。何も書き込まない。
     *
     * @param  array<int, string>  $terms
     */
    private function searchMode(array $terms): int
    {
        if ($terms === []) {
            $this->error('検索語がありません。例: php artisan parking:delist --search=福井駅 --search=福井市');
            $this->line('（非公開にするには確定後に --id=123 --reason="..." を指定）');

            return self::FAILURE;
        }

        $query = BikeParking::query();
        foreach ($terms as $term) {
            $like = '%'.$term.'%';
            $query->where(function ($q) use ($like) {
                $q->where('name', 'like', $like)
                    ->orWhere('address', 'like', $like);
            });
        }

        $parkings = $query->orderBy('id')->get();

        if ($parkings->isEmpty()) {
            $this->warn('該当する駐車場は見つかりませんでした: '.implode(' / ', $terms));

            return self::SUCCESS;
        }

        $this->info('検索語: '.implode(' AND ', $terms)."  → {$parkings->count()} 件");
        $this->table(
            ['ID', '名前', '住所', '公開中', '削除依頼(delisted_at)'],
            $parkings->map(fn (BikeParking $p) => [
                $p->id,
                $p->name,
                $p->address,
                $p->is_active ? '公開' : '非公開',
                $p->delisted_at?->format('Y-m-d H:i') ?? '-',
            ])->all()
        );
        $this->line('非公開にするには: php artisan parking:delist --id='.$parkings->pluck('id')->implode(' --id=').' --reason="（依頼元・理由）"');

        return self::SUCCESS;
    }

    /**
     * 非公開（または --relist で公開へ戻す）モード。
     *
     * @param  array<int, int>  $ids
     */
    private function mutateMode(array $ids): int
    {
        $relist = (bool) $this->option('relist');
        $dryRun = (bool) $this->option('dry-run');
        $reason = trim((string) $this->option('reason'));

        if (! $relist && $reason === '') {
            $this->error('--reason が必須です（非公開にする理由・依頼元を記録します）。');

            return self::FAILURE;
        }

        $parkings = BikeParking::whereIn('id', $ids)->orderBy('id')->get();
        $found = $parkings->pluck('id')->all();
        $missing = array_diff($ids, $found);
        foreach ($missing as $id) {
            $this->warn("ID {$id} は存在しません（スキップ）。");
        }

        if ($parkings->isEmpty()) {
            $this->error('対象の駐車場が見つかりませんでした。');

            return self::FAILURE;
        }

        $verb = $relist ? '公開に戻す' : '非公開にする';
        $prefix = $dryRun ? '[dry-run] ' : '';

        foreach ($parkings as $parking) {
            $before = $parking->is_active ? '公開' : '非公開';
            $this->line("{$prefix}{$verb}: ID {$parking->id} / {$parking->name} / {$parking->address}（現在: {$before}）");

            if ($dryRun) {
                continue;
            }

            if ($relist) {
                $parking->is_active = true;
                $parking->delisted_at = null;
                $parking->delisted_reason = null;
            } else {
                $parking->is_active = false;
                // 既に削除依頼済みなら最初の記録を優先して残す（理由だけ追記される事故を防ぐ）。
                if ($parking->delisted_at === null) {
                    $parking->delisted_at = now();
                    $parking->delisted_reason = $reason;
                }
            }
            $parking->save();
        }

        if ($dryRun) {
            $this->warn('dry-run のため書き込みません。本実行は --dry-run を外してください。');
        } else {
            $this->info(($relist ? '公開に戻しました' : '非公開にしました').': '.$parkings->count().' 件');
        }

        return self::SUCCESS;
    }
}
