<?php

declare(strict_types=1);

namespace App\Services\Shop;

use App\Models\ShopAcceptanceReport;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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
        return "shop_acceptance_v4_{$shopId}"; // v4: コメントに投稿者アバター(avatar_url)を追加
    }

    /**
     * 承認済みの集計を返す。
     *
     * @return array{
     *   counts: array<string,int>,                                      // フラグ列 => 報告人数
     *   comments: array<int,array{id:int,name:string,comment:string,verified:bool,avatar_url:?string}>, // 承認済みコメント（通報導線用に id 付き）
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

            // コメントは comment_approved で抽出（即反映）。事実系フラグの集計（上の approved()）
            // とは条件を分ける。承認待ちフラグがコメント表示に影響しない・その逆も無い。
            $comments = ShopAcceptanceReport::commentApproved()
                ->where('shop_id', $shopId)
                ->whereNotNull('comment')
                ->where('comment', '!=', '')
                ->with('user')                  // 投稿者アバター（ゲスト=user_id null は null）
                ->orderByDesc('created_at')
                ->limit(20)
                ->get(['id', 'comment', 'submitter_name', 'user_id'])
                ->map(fn ($r) => [
                    'id' => $r->id,
                    'name' => $r->submitter_name ?: '名無しライダー',
                    'comment' => $r->comment,
                    'verified' => $r->user_id !== null,
                    'avatar_url' => $r->user?->avatar_url, // 未ログイン投稿は null → 既定アイコン
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
     * 全店横断の「新着口コミ」フィード。comment_approved=true のコメントのみを
     * created_at 降順で返す。店情報を eager load（N+1回避）。複合index
     * (comment_approved, created_at) を利用。フィードは頻繁に変わるためキャッシュしない。
     */
    public function getRecentCommentsFeed(int $perPage = 20): LengthAwarePaginator
    {
        return ShopAcceptanceReport::commentApproved()
            ->whereNotNull('comment')
            ->where('comment', '!=', '')
            ->whereHas('shop')       // 店が消えたコメントは出さない
            ->with(['shop', 'user']) // N+1回避（店名・所在地／投稿者アバター）
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * 承認・却下時にキャッシュを個別失効させる（cache:clear は使わない）。
     */
    public function forgetSummary(int $shopId): void
    {
        Cache::forget($this->cacheKey($shopId));
    }
}
