<?php

declare(strict_types=1);

namespace App\Http\Controllers\Parking;

use App\Http\Controllers\Controller;
use App\Models\BikeParking;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * 駐車場名検索。/shops/search（ShopSearchController）と同方式＝MySQL の LIKE 部分一致。
 * Meilisearch は使わない（bike_parkings は Scout 未インデックス・shops と同じ方針）。
 * name_normalized 列は無いため name / address へ直接 LIKE。複数語は token-AND で
 * 「町田 森野」のような地名＋部分の複合語にも当てる。
 */
final class ParkingSearchController extends Controller
{
    private const MIN_LENGTH = 2;

    private const PER_PAGE = 20;

    public function index(Request $request): View
    {
        $rawQ = trim((string) $request->query('q', ''));
        $tooShort = mb_strlen($rawQ) < self::MIN_LENGTH;

        $parkings = null;

        if (! $tooShort) {
            // 全角/半角スペース区切りのトークンに分解（複合語対応）。
            $tokens = preg_split('/[\s\x{3000}]+/u', $rawQ, -1, PREG_SPLIT_NO_EMPTY) ?: [];

            $query = BikeParking::query()->active(); // 公開中(is_active)のみ＝一覧表示と一致

            foreach ($tokens as $token) {
                $escaped = addcslashes($token, '\\%_'); // % _ \ を無害化
                $like = '%'.$escaped.'%';
                // 各トークンは name か address のどちらかに含まれれば可（AND across tokens）
                $query->where(function ($q) use ($like) {
                    $q->where('name', 'like', $like)->orWhere('address', 'like', $like);
                });
            }

            // ランキング: 先頭トークンが駐車場名の前方一致を優先 → 名前順。
            $firstPrefix = addcslashes($tokens[0] ?? '', '\\%_').'%';
            $query->orderByRaw('CASE WHEN name LIKE ? THEN 0 ELSE 1 END', [$firstPrefix])
                ->orderBy('name');

            $parkings = $query->paginate(self::PER_PAGE)->withQueryString();

            if ($parkings->total() === 0) {
                // ゼロヒット = 「探されているのに未登録の駐車場」。登録導線につなぐ材料。
                Log::info('parking_search_zero_hit', ['q' => $rawQ]);
            }
        }

        return view('parking.search', [
            'rawQ' => $rawQ,
            'tooShort' => $tooShort,
            'minLength' => self::MIN_LENGTH,
            'parkings' => $parkings,
        ]);
    }
}
