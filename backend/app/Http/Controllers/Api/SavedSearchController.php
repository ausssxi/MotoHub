<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use App\Services\Bike\SavedSearchService;
use Exception;
use Illuminate\Support\Facades\Log;

class SavedSearchController extends Controller
{
    public function __construct(
        private readonly SavedSearchService $service
    ) {}

    public function store(Request $request): JsonResponse
    {
        // ここ全体を try-catch で囲んでエラーを見える化します
        try {
            if (!Auth::check()) {
                return response()->json(['message' => 'ログインが必要です。'], 401);
            }

            $conditions = $request->except(['_token', 'page', 'sort']);
            
            $checkParams = $conditions;
            unset($checkParams['name_override']);

            if (empty(array_filter($checkParams))) {
                return response()->json(['message' => '検索条件が指定されていません。'], 400);
            }

            // サービス呼び出し
            $savedSearch = $this->service->saveSearchCondition(Auth::user(), $conditions);

            return response()->json([
                'message' => '検索条件を保存しました！',
                'id' => $savedSearch->id
            ]);

        } catch (\Throwable $e) {
            // エラー内容をログに記録
            Log::error($e);
            
            // ★重要: エラーメッセージをJSONで返してアラートで表示できるようにする
            return response()->json([
                'message' => 'サーバーエラー: ' . $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }
    }
}