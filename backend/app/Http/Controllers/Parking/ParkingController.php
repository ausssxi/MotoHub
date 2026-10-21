<?php

declare(strict_types=1);

namespace App\Http\Controllers\Parking;

use App\Http\Controllers\Controller;
use App\Http\Requests\Parking\StoreParkingRequest;
use App\Http\Requests\Parking\StoreParkingReviewRequest;
use App\Http\Requests\Parking\UpdateParkingRequest;
use App\Models\BikeParking;
use App\Models\ParkingUsage;
use App\Services\Parking\ParkingService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ParkingController extends Controller
{
    public function __construct(
        private readonly ParkingService $parkingService
    ) {}

    /**
     * マップ一覧ページ
     */
    public function index(): View
    {
        $topReviewed = BikeParking::active()
            ->where('reviews_count', '>', 0)
            ->orderByDesc('reviews_count')
            ->limit(5)
            ->get();

        return view('parking.index', compact('topReviewed'));
    }

    /**
     * 詳細ページ
     */
    public function show(BikeParking $bikeParking): View
    {
        // 承認制: 非公開（is_active=false）の投稿は直リンクでも閲覧・共有させない。
        // RentalGarageController::show() が is_active=true で絞って firstOrFail するのと同作法。
        // 公開中データ（is_active=1）はそのまま通る。
        abort_if(! $bikeParking->is_active, 404);

        $data = $this->parkingService->getParkingDetail($bikeParking->id);

        return view('parking.show', $data);
    }

    /**
     * 全駐車場横断「みんなの口コミ（新着）」フィード（/shops/reviews の駐車場版）。
     */
    public function reviewsFeed(): View
    {
        return view('parking.reviews_index', [
            'reviews' => $this->parkingService->getRecentReviewsFeed(20)->withQueryString(),
        ]);
    }

    /**
     * 地図用エリア検索API
     */
    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ne_lat' => 'required|numeric',
            'ne_lng' => 'required|numeric',
            'sw_lat' => 'required|numeric',
            'sw_lng' => 'required|numeric',
            'parking_type' => 'nullable|string',
        ]);

        $parkingType = $validated['parking_type'] ?? null;
        $parkings = $this->parkingService->getParkingsInArea($validated, $parkingType);

        return response()->json($parkings);
    }

    /**
     * 登録フォーム
     */
    public function create(): View
    {
        return view('parking.create');
    }

    /**
     * 駐車場登録処理
     */
    public function store(StoreParkingRequest $request): RedirectResponse
    {
        $this->parkingService->registerParking(
            $request->user(),
            $request->validated()
        );

        // 承認制（is_active=false で作成）。確認後に公開される旨を投稿フォームで通知する。
        return redirect()->route('parking.create')
            ->with('submission_success', '1');
    }

    /**
     * 編集フォーム
     */
    public function edit(BikeParking $bikeParking): View|RedirectResponse
    {
        $user = auth()->user();
        if (! $user || (! $user->is_admin && $bikeParking->user_id !== $user->id)) {
            abort(403);
        }

        $bikeParking->load('images');

        return view('parking.edit', ['parking' => $bikeParking]);
    }

    /**
     * 更新処理
     */
    public function update(UpdateParkingRequest $request, BikeParking $bikeParking): RedirectResponse
    {
        $this->parkingService->updateParking(
            $bikeParking,
            $request->user(),
            $request->validated()
        );

        return redirect()->route('parking.show', $bikeParking)
            ->with('success', '駐車場情報を更新しました！');
    }

    /**
     * 「使ったことある」ワンタップ反応（ログイン不要）。
     *
     * レビューより軽い反応として件数だけ数える。生IPは保存せず sha256(ip|ua|app.key) の
     * visitor_hash で重複を弾く（同じ訪問者は2回目以降も成功扱いで現在の件数を返すだけ）。
     */
    public function used(BikeParking $bikeParking, Request $request): JsonResponse
    {
        // 非公開（is_active=false）の投稿は show と同じく直リンクでも扱わない。
        abort_if(! $bikeParking->is_active, 404);

        $visitorHash = hash('sha256', $request->ip().'|'.$request->userAgent().'|'.config('app.key'));

        // (parking_id, visitor_hash) のユニーク制約が重複を防ぐ。既存なら wasRecentlyCreated=false。
        $usage = ParkingUsage::firstOrCreate([
            'parking_id' => $bikeParking->id,
            'visitor_hash' => $visitorHash,
        ]);

        return response()->json([
            'count' => ParkingUsage::where('parking_id', $bikeParking->id)->count(),
            'already' => ! $usage->wasRecentlyCreated,
        ]);
    }

    /**
     * レビュー投稿（ログイン不要）
     */
    public function storeReview(BikeParking $bikeParking, StoreParkingReviewRequest $request): RedirectResponse
    {
        // 生IPは保存せず sha256(ip|app.key) のみ（連投・濫用の単位／将来の事後対応用）。
        $data = $request->validated();
        $data['submitter_ip_hash'] = hash('sha256', $request->ip().'|'.config('app.key'));
        // 即反映（NGは保存前に弾く）。ただし新規ユーザー投稿駐車場は自演対策で承認待ち。
        $data['is_approved'] = ! $bikeParking->isNewUserParking();

        $this->parkingService->addReview(
            $bikeParking->id,
            $request->user(),
            $data
        );

        return redirect()->route('parking.show', $bikeParking)
            ->with('review_success', json_encode([
                'name' => $bikeParking->name,
                'url' => route('parking.show', $bikeParking),
            ]));
    }
}
