<?php

declare(strict_types=1);

namespace App\Http\Controllers\Social;

use App\Http\Controllers\Controller;
use App\Http\Requests\MyBike\StoreGarageCommentRequest;
use App\Jobs\SendGarageActivityNotification;
use App\Models\GarageComment;
use App\Models\MyBike;
use Illuminate\Http\RedirectResponse;

/**
 * 公開ガレージへの社交コメント投稿（会員限定・ルートは auth middleware 配下）。
 * オーナーへは push 通知（自己コメントは抑制）＝再訪フック。
 */
final class GarageCommentController extends Controller
{
    public function store(StoreGarageCommentRequest $request, MyBike $myBike): RedirectResponse
    {
        // 非公開ガレージにはコメントできない（公開面と同じ防御）
        abort_unless($myBike->is_public, 404);

        $user = $request->user();

        GarageComment::create([
            'my_bike_id' => $myBike->id,
            'user_id' => $user->id,
            'body' => $request->input('body'),
            'status' => 'published', // 即反映・通報でキルスイッチ（status=hidden）
        ]);

        // オーナーへ通知（自己コメントは通知しない）。ワーカー非常駐のため dispatchAfterResponse。
        if ($myBike->user_id !== $user->id) {
            SendGarageActivityNotification::dispatchAfterResponse(
                $myBike->id,
                'comment',
                $user->review_display_name ?? '名無しライダー',
            );
        }

        return redirect(route('garage.public.show', $myBike->id).'#garage-comments')
            ->with('garage_comment_success', true);
    }
}
