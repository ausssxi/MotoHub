<?php

declare(strict_types=1);

namespace App\Http\Controllers\Social;

use App\Http\Controllers\Controller;
use App\Models\Follow;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * ユーザーフォローのトグル（NewsController::toggleCommentLike と同流儀）。
 * 公開範囲: フォロワー数のみ公開・一覧は非公開。followee は public_token で解決。
 */
final class FollowController extends Controller
{
    public function toggle(string $token): JsonResponse
    {
        $follower = Auth::user();
        $followee = User::where('public_token', $token)->firstOrFail();

        // 自己フォロー禁止（UI でも非表示だが二重で弾く）
        abort_if($followee->id === $follower->id, 422);

        $following = DB::transaction(function () use ($follower, $followee) {
            $existing = Follow::where('follower_id', $follower->id)
                ->where('followee_id', $followee->id)
                ->first();

            if ($existing) {
                $existing->delete();
                $followee->decrement('followers_count');
                $follower->decrement('following_count');

                return false;
            }

            Follow::create(['follower_id' => $follower->id, 'followee_id' => $followee->id]);
            $followee->increment('followers_count');
            $follower->increment('following_count');

            return true;
        });

        return response()->json([
            'success' => true,
            'following' => $following,
            'followers_count' => $followee->fresh()->followers_count,
        ]);
    }
}
