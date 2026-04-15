<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\QuizQuestionGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class QuizController extends Controller
{
    public function __construct(
        private readonly QuizQuestionGenerator $generator,
    ) {}

    /**
     * クイズ問題を返す
     * 50問プールを1時間キャッシュ → ランダムに count 問返す
     */
    public function questions(Request $request): JsonResponse
    {
        $category = $request->query('category', 'price');
        $count = min((int) $request->query('count', 10), 20);

        $validCategories = ['price', 'ranking', 'speed', 'region'];
        if (!in_array($category, $validCategories, true)) {
            return response()->json(['error' => 'Invalid category'], 400);
        }

        // 50問プールを1時間キャッシュ
        $pool = Cache::remember(
            "quiz_pool_{$category}",
            3600,
            fn () => $this->generator->generate($category, 50)
        );

        if (empty($pool)) {
            return response()->json(['error' => 'No questions available'], 404);
        }

        // プールからランダムに count 問を選出
        $selected = collect($pool)->shuffle()->take($count)->values()->toArray();

        return response()->json($selected);
    }
}
