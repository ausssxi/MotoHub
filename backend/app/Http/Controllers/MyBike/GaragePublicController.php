<?php

declare(strict_types=1);

namespace App\Http\Controllers\MyBike;

use App\Http\Controllers\Controller;
use App\Models\GarageLike;
use App\Models\MyBike;
use App\Services\MyBike\MyBikeService;
use Illuminate\Support\Facades\Auth;

class GaragePublicController extends Controller
{
    public function __construct(
        private readonly MyBikeService $service,
    ) {}

    public function index()
    {
        // 公開 opt-in 済みの愛車のみ
        $bikes = MyBike::where('is_public', true)
            ->with(['user', 'bikeModel.manufacturer', 'images'])
            ->latest()
            ->paginate(20);

        // いいね済み判定を一括取得（N+1防止）
        $likedGarageIds = GarageLike::likedIdsFor(Auth::id(), $bikes->pluck('id')->all());
        $viewerId = Auth::id();

        return view('mybikes.public_index', compact('bikes', 'likedGarageIds', 'viewerId'));
    }

    public function show(MyBike $myBike)
    {
        // 非公開の愛車は公開URLで一切描画しない（情報漏洩防止）
        abort_unless($myBike->is_public, 404);

        $myBike->load(['user', 'bikeModel.manufacturer', 'fuelLogs', 'maintenanceLogs', 'customRecords', 'images']);

        // シェア文言用の集計（OGP画像と同じ単一ソース・本名は含めない）
        $share = $this->service->garageShareStats($myBike);

        // ソーシャル②: いいね状態（自分のガレージはボタン非表示）
        $liked = Auth::check() && GarageLike::where('user_id', Auth::id())->where('my_bike_id', $myBike->id)->exists();
        $isOwner = Auth::id() === $myBike->user_id;

        return view('mybikes.public_show', compact('myBike', 'share', 'liked', 'isOwner'));
    }
}
