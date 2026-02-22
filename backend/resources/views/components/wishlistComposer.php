<?php

namespace App\View\Composers;

use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;
use App\Models\Wishlist;

class WishlistComposer
{
    /**
     * Bind data to the view.
     */
    public function compose(View $view): void
    {
        // デフォルトは0件
        $count = 0;

        if (Auth::check()) {
            // ログインユーザーの場合:
            // 「自分のID」かつ「リレーション先のバイクが販売中(status='selling')」のものだけカウント
            $count = Wishlist::where('user_id', Auth::id())
                ->whereHas('bike', function ($query) {
                    // バイク側のステータス条件 (selling = 販売中)
                    // ※実際のカラム値に合わせて調整してください
                    $query->where('status', 'selling');
                })
                ->count();
        }

        // ビュー変数 'wishlistCount' にセット
        $view->with('wishlistCount', $count);
    }
