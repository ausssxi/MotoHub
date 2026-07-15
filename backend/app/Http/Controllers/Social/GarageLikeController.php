<?php

declare(strict_types=1);

namespace App\Http\Controllers\Social;

use App\Http\Controllers\Controller;
use App\Jobs\SendGarageActivityNotification;
use App\Models\GarageLike;
use App\Models\MyBike;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * 公開ガレージへのいいねトグル（NewsController::toggleCommentLike と同流儀）。
 * 公開範囲: いいね数のみ公開・いいねした人の一覧は非公開。非公開ガレージは404。自分のガレージは422。
 */
final class GarageLikeController extends Controller
{
    public function toggle(MyBike $myBike): JsonResponse
    {
        // 非公開ガレージにはいいねできない（公開面と同じ防御）
        abort_unless($myBike->is_public, 404);

        $userId = Auth::id();

        // 自分のガレージへのいいね禁止（UI でも非表示だが二重で弾く）
        abort_if($myBike->user_id === $userId, 422);

        $liked = DB::transaction(function () use ($myBike, $userId) {
            $existing = GarageLike::where('user_id', $userId)
                ->where('my_bike_id', $myBike->id)
                ->first();

            if ($existing) {
                $existing->delete();
                $myBike->decrement('like_count');

                return false;
            }

            GarageLike::create(['user_id' => $userId, 'my_bike_id' => $myBike->id]);
            $myBike->increment('like_count');

            return true;
        });

        // いいねが付いた瞬間だけ、オーナーへ通知（解除では通知しない・自己いいねは 422 で既に排除済み）＝再訪フック
        if ($liked) {
            SendGarageActivityNotification::dispatchAfterResponse(
                $myBike->id,
                'like',
                Auth::user()?->review_display_name ?? '名無しライダー',
            );
        }

        return response()->json([
            'success' => true,
            'liked' => $liked,
            'like_count' => $myBike->fresh()->like_count,
        ]);
    }
}
