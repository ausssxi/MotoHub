<?php

declare(strict_types=1);

namespace App\Http\Controllers\MyBike;

use App\Http\Controllers\Controller;
use App\Models\NewsComment;
use App\Models\Review;
use App\Models\User;
use App\Services\MyBike\MyBikeService;

/**
 * 公開ライダープロフィール（/riders/{token}）。
 * ★privacy: 公開ハンドル(review_display_name)＋公開ガレージ(is_public)のみ。
 *   本名(user->name)・メール・内部ID・非公開ガレージは一切出さない。URLキーは非連番トークン。
 */
final class RiderProfileController extends Controller
{
    public function __construct(
        private readonly MyBikeService $service,
    ) {}

    public function show(string $token)
    {
        // 不明なトークンは 404（列挙・推測対策＝トークンは公開面を持つユーザーのみ保有）
        $user = User::where('public_token', $token)->firstOrFail();

        $garages = $this->service->publicGarageCardsForUser($user);

        // 公開ガレージが1台も無ければ公開面なし＝404（孤立した空プロフィールを作らない）
        abort_if($garages->isEmpty(), 404);

        $handle = $user->review_display_name ?? '名無しライダー';

        // 第1段集約（位置リスク低・PII整合済み）：本人の公開済みレビュー＋ニュースコメント。
        // 表示名は保存済み nickname ではなく現 review_display_name(=$handle) を使う（handle変更時も一貫）。
        $reviews = Review::where('user_id', $user->id)
            ->where('is_approved', true)            // 承認済みのみ（未承認・下書きは出さない）
            ->with('bikeModel.manufacturer')
            ->latest()
            ->get();

        // ニュースコメントは規模が大きい（1ユーザ159件規模）ためページング必須。
        $comments = NewsComment::where('user_id', $user->id)
            ->with('news')
            ->latest()
            ->paginate(20, ['*'], 'comments')
            ->withQueryString();

        return view('mybikes.rider_profile', [
            'handle' => $handle,
            'garages' => $garages,
            'token' => $token,
            'reviews' => $reviews,
            'comments' => $comments,
        ]);
    }
}
