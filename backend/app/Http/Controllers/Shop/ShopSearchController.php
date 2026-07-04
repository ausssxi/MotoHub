<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Support\ShopNameNormalizer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * 店名でショップを探す内部検索（noindex）。
 *
 * 本当の狙いはゼロヒット導線: 「店名で検索→見つからない→その場で投稿」。
 * Meilisearchは使わず MySQL の name_normalized（正規化済み）＋LIKE で照合する
 * （11k行・スクレイパーSQLAlchemy直書きのためMeili同期に見合わない）。
 */
final class ShopSearchController extends Controller
{
    private const MIN_LENGTH = 2;

    private const PER_PAGE = 20;

    public function index(Request $request): View
    {
        $rawQ = trim((string) $request->query('q', ''));
        $pref = trim((string) $request->query('pref', ''));

        $type = (string) $request->query('type', '');
        if (! in_array($type, ['dealer', 'repair_only'], true)) {
            $type = '';
        }

        $normQ = ShopNameNormalizer::normalize($rawQ);
        $tooShort = mb_strlen($normQ) < self::MIN_LENGTH;

        $shops = null;

        if (! $tooShort) {
            // LIKE のワイルドカードをエスケープ（ユーザー入力の % _ \ を無害化）
            $escaped = addcslashes($normQ, '\\%_');
            $contains = '%'.$escaped.'%';
            $prefix = $escaped.'%';

            $query = Shop::query()
                ->where(function ($q) use ($contains) {
                    // 通常は正規化列で照合。まだバックフィルされていない新規スクレイプ行
                    // （name_normalized IS NULL）は name で拾うフォールバック。
                    $q->where('name_normalized', 'like', $contains)
                        ->orWhere(function ($q2) use ($contains) {
                            $q2->whereNull('name_normalized')->where('name', 'like', $contains);
                        });
                });

            if ($pref !== '') {
                $query->where('prefecture', $pref);
            }
            if ($type !== '') {
                $query->where('shop_type', $type);
            }

            // ランキング: 完全一致 → 前方一致 → 部分一致。同一段は都道府県→店名順。
            $query->orderByRaw(
                'CASE WHEN name_normalized = ? THEN 0 WHEN name_normalized LIKE ? THEN 1 ELSE 2 END',
                [$normQ, $prefix]
            )->orderBy('prefecture')->orderBy('name');

            $shops = $query->paginate(self::PER_PAGE)->withQueryString();

            if ($shops->total() === 0) {
                // ゼロヒット = 「探されているのに未掲載の店」。掲載拡充の優先順位データ。
                Log::info('shop_search_zero_hit', ['q' => $rawQ, 'pref' => $pref, 'type' => $type]);
            }
        }

        return view('shops.search', [
            'rawQ' => $rawQ,
            'pref' => $pref,
            'type' => $type,
            'tooShort' => $tooShort,
            'minLength' => self::MIN_LENGTH,
            'shops' => $shops,
        ]);
    }
}
