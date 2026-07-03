<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShopSubmission;
use App\Services\Shop\ShopSubmissionImageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 承認前の投稿画像を「管理者のみ」に配信する（非公開ディスク上のため直リンク不可）。
 * 未ログイン・非管理者は 404（存在を悟らせない）。route:cache 可（クロージャ不使用）。
 */
final class ShopSubmissionImageController extends Controller
{
    public function show(Request $request, ShopSubmission $submission, ShopSubmissionImageService $imageService): StreamedResponse
    {
        abort_unless((bool) $request->user()?->is_admin, 404);
        abort_if(empty($submission->image_path), 404);

        $disk = Storage::disk($imageService->pendingDisk());
        abort_unless($disk->exists($submission->image_path), 404);

        return $disk->response($submission->image_path);
    }
}
