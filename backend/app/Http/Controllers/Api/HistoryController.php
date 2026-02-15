<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use App\Services\Bike\BrowsingHistoryService;

class HistoryController extends Controller
{
    public function __construct(
        private readonly BrowsingHistoryService $service
    ) {}

    /**
     * 履歴を記録する
     */
    public function record(Request $request): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(['message' => 'Unauthenticated.'], 200);
        }

        $listingId = (int)$request->input('listing_id');
        
        $this->service->recordHistory(Auth::user(), $listingId);

        return response()->json(['status' => 'recorded']);
    }

    /**
     * ログインユーザーの履歴ID一覧を取得
     */
    public function index(): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json([]);
        }

        $ids = $this->service->getUserHistoryIds(Auth::user());

        return response()->json($ids);
    }
    
    /**
     * 一括同期
     */
    public function sync(Request $request): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(['status' => 'skipped']);
        }
        
        $ids = $request->input('ids', []);
        if (empty($ids)) {
            return response()->json(['status' => 'no_data']);
        }
        
        $this->service->syncLocalHistory(Auth::user(), $ids);
        
        return response()->json(['status' => 'synced']);
    }
}