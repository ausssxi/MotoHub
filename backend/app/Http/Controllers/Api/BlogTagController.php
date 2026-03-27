<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BlogTag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BlogTagController extends Controller
{
    /**
     * タグ候補のサジェスト（Ajax用）
     */
    public function suggest(Request $request): JsonResponse
    {
        $query = $request->query('q', '');

        $tags = BlogTag::where('name', 'like', "%{$query}%")
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name', 'slug']);

        return response()->json($tags);
    }
}
