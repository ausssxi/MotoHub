<?php

declare(strict_types=1);

namespace App\Http\Controllers\Parking;

use App\Http\Controllers\Controller;
use App\Http\Requests\Parking\StoreParkingRequest;
use App\Http\Requests\Parking\StoreParkingReviewRequest;
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
        return view('parking.index');
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
     * レビュー投稿
     */
    public function storeReview(BikeParking $bikeParking, StoreParkingReviewRequest $request): RedirectResponse
    {
        $this->parkingService->addReview(
            $bikeParking->id,
            $request->user(),
            $request->validated()
        );

        return redirect()->route('parking.show', $bikeParking)
            ->with('success', 'レビューを投稿しました！');
    }
}
