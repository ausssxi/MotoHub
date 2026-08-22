<?php

declare(strict_types=1);

namespace App\Http\Controllers\Bike;

use App\Http\Controllers\Controller;
use App\Support\TireSize;
use Illuminate\Contracts\View\View;

/**
 * タイヤサイズ別ページ（前輪サイズ基準）。
 *  - /bikes/tire-size            … ページ化対象サイズ（5件以上）の一覧
 *  - /bikes/tire-size/{sizeSlug} … サイズ別の適合車種一覧（5件未満は404）
 * 集計・正規化は App\Support\TireSize に集約（normalize は複製しない）。
 */
final class TireSizeController extends Controller
{
    public function index(): View
    {
        $sizes = TireSize::pageableIndex(); // 多い順・[['size','size_slug','count'], ...]

        return view('bikes.tire-size-index', compact('sizes'));
    }

    public function show(string $sizeSlug): View
    {
        $data = TireSize::pageData($sizeSlug);
        if ($data === null) {
            abort(404); // ページ化条件（5件以上）未満、または未知slug
        }

        return view('bikes.tire-size-show', compact('data'));
    }
}
