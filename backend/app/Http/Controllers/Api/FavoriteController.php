<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class FavoriteController extends Controller
{
    /**
     * お気に入りの切り替え (Toggle)
     */
    public function toggle(Request $request): JsonResponse
    {
        // ログインしていない場合はエラー
        if (!Auth::check()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $user = Auth::user();
        $listingId = $request->input('listing_id');

        // トグル処理（あれば削除、なければ追加）
        $attached = $user->favorites()->toggle($listingId);

        // attached配列にIDが入っていれば「追加された」、なければ「削除された」
        $isFavorited = !empty($attached['attached']);

        return response()->json([
            'favorited' => $isFavorited,
            'count' => $user->favorites()->count()
        ]);
    }

    /**
     * ログインユーザーのお気に入りID一覧を取得
     */
    public function index(): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json([]);
        }

        return response()->json(Auth::user()->favorites()->pluck('listings.id'));
    }
}