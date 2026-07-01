<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\ShopAcceptanceReport;
use App\Services\Shop\ShopAcceptanceService;
use Illuminate\Support\Facades\Auth;

/**
 * 承認状態の変化を一元処理する。どの経路（Filamentトグル/編集/一括/tinker）でも
 * 承認時刻の記録と集計キャッシュの個別失効を保証する。
 */
final class ShopAcceptanceReportObserver
{
    public function __construct(
        private readonly ShopAcceptanceService $acceptanceService,
    ) {}

    /** 承認へ切り替わった瞬間に承認メタ情報を刻む（保存前・再帰なし）。 */
    public function saving(ShopAcceptanceReport $report): void
    {
        if ($report->is_approved && $report->isDirty('is_approved')) {
            if ($report->approved_at === null) {
                $report->approved_at = now();
            }
            if ($report->approved_by === null && Auth::check()) {
                $report->approved_by = Auth::id();
            }
        }
    }

    public function saved(ShopAcceptanceReport $report): void
    {
        // 承認/却下いずれの変化でも表示集計を作り直させる（cache:clearは使わない）。
        $this->acceptanceService->forgetSummary($report->shop_id);
    }

    public function deleted(ShopAcceptanceReport $report): void
    {
        $this->acceptanceService->forgetSummary($report->shop_id);
    }
}
