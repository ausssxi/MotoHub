<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BlogImageController extends Controller
{
    /**
     * Markdownエディタからの画像アップロード
     * drag & drop / ペースト対応
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'image' => 'required|image|max:5120', // 5MB
        ]);

        $path = $request->file('image')
            ->store('blog/images/' . date('Y/m'), config('blog.image_disk'));

        $url = rtrim(config('blog.image_base_url', '/storage'), '/') . '/' . $path;

        return response()->json([
            'url' => $url,
            'path' => $path,
        ]);
    }
}
