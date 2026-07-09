<?php

declare(strict_types=1);

namespace App\Http\Controllers\Parking;

use App\Http\Controllers\Controller;
use App\Http\Requests\Parking\StoreParkingRequest;
use App\Http\Requests\Parking\StoreParkingReviewRequest;
use App\Http\Requests\Parking\UpdateParkingRequest;
use App\Models\BikeParking;
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
        $data = $this->parkingService->getParkingDetail($bikeParking->id);

        return view('parking.show', $data);
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
        $parking = $this->parkingService->registerParking(
            $request->user(),
            $request->validated()
        );

        return redirect()->route('parking.show', $parking)
            ->with('success', '駐車場を登録しました！');
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

    /**
     * 「使ったことある」カウントアップ
     */
    public function incrementUsed(BikeParking $bikeParking): JsonResponse
    {
        $bikeParking->increment('used_count');

        return response()->json(['used_count' => $bikeParking->fresh()->used_count]);
    }
}
