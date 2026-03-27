<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MarkdownService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BlogPreviewController extends Controller
{
    /**
     * サーバーサイドMarkdownプレビュー
     */
    public function store(Request $request, MarkdownService $markdown): JsonResponse
    {
        $body = $request->input('body', '');
        $html = $markdown->toHtml($body);

        return response()->json(['html' => $html]);
    }
}
