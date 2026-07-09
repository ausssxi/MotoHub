<?php

declare(strict_types=1);

namespace App\Services\Shop;

use App\Models\ShopAcceptanceReport;
use Illuminate\Support\Facades\Cache;

/**
 * ユーザー投稿の受け入れ情報の集計・表示ロジック。
 * shops テーブルには一切書き戻さない（スクレイパーとの分離保証）。
 */
final class ShopAcceptanceService
{
    private const CACHE_TTL = 3600;

    private function cacheKey(int $shopId): string
    {
        return "shop_acceptance_v2_{$shopId}"; // v2: コメントに id を含める（通報導線）
    }

    /**
     * 承認済みの集計を返す。
     *
     * @return array{
     *   counts: array<string,int>,                                      // フラグ列 => 報告人数
     *   comments: array<int,array{id:int,name:string,comment:string,verified:bool}>, // 承認済みコメント（通報導線用に id 付き）
     *   total: int                                                      // 承認済み投稿件数
     * }
     */
    public function getApprovedSummary(int $shopId): array
    {
        return Cache::remember($this->cacheKey($shopId), self::CACHE_TTL, function () use ($shopId) {
            $agg = ShopAcceptanceReport::approved()
                ->where('shop_id', $shopId)
                ->selectRaw(
                    'SUM(accepts_other_store) AS accepts_other_store,'
                    .'SUM(accepts_bring_in) AS accepts_bring_in,'
                    .'SUM(pickup_service) AS pickup_service,'
                    .'SUM(walk_in_ok) AS walk_in_ok,'
                    .'COUNT(*) AS total'
                )
                ->first();

            $counts = [];
            foreach (array_keys(ShopAcceptanceReport::FLAGS) as $col) {
                $counts[$col] = (int) ($agg->{$col} ?? 0);
            }

            $comments = ShopAcceptanceReport::approved()
                ->where('shop_id', $shopId)
                ->whereNotNull('comment')
                ->where('comment', '!=', '')
                ->orderByDesc('approved_at')
                ->limit(20)
                ->get(['id', 'comment', 'submitter_name', 'user_id'])
                ->map(fn ($r) => [
                    'id' => $r->id,
                    'name' => $r->submitter_name ?: '名無しライダー',
                    'comment' => $r->comment,
                    'verified' => $r->user_id !== null,
                ])
                ->all();

            return [
                'counts' => $counts,
                'comments' => $comments,
                'total' => (int) ($agg->total ?? 0),
            ];
        });
    }

    /**
     * 承認・却下時にキャッシュを個別失効させる（cache:clear は使わない）。
     */
    public function forgetSummary(int $shopId): void
    {
        Cache::forget($this->cacheKey($shopId));
    }
}
