<?php

declare(strict_types=1);

namespace App\Http\Controllers\RentalGarage;

use App\Http\Controllers\Controller;
use App\Models\RentalGarage;
use App\Models\RentalGarageClick;
use App\Support\BotDetector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * レンタルガレージ 事業者サイトへの送客中継ルート。
 *
 * /go/rental-garage/{id} でクリックを1件記録し、302 で外部URLへ飛ばす。
 * JS に依存せず確実に取れ、bot 除外もサーバー側で行う。IPアドレスは保存しない。
 */
final class RentalGarageClickController extends Controller
{
    public function go(int $id, Request $request): RedirectResponse
    {
        // 詳細ページと同じ公開条件（is_active=true）。ここでは is_active の扱いを変えない。
        $garage = RentalGarage::query()->where('id', $id)->where('is_active', true)->firstOrFail();

        // 外部リンクが無ければ送客先が無いので詳細ページへ戻す（記録しない）。
        if ($garage->website_url === null || $garage->website_url === '') {
            return redirect()->route('rental-garage.show', $garage->id);
        }

        RentalGarageClick::create([
            'rental_garage_id' => $garage->id,
            'clicked_at' => now(),
            'referrer_path' => $this->internalReferrerPath($request),
            'is_bot' => BotDetector::isBot($request->userAgent()),
        ]);

        return redirect()->away($garage->website_url, 302);
    }

    /**
     * Referer からサイト内パスのみを取り出す（ホスト無し）。自サイト以外・不明は null。
     * クエリ文字列は個人情報が紛れ込みうるため落とし、パスのみ残す。
     */
    private function internalReferrerPath(Request $request): ?string
    {
        $referer = $request->headers->get('referer');
        if ($referer === null || $referer === '') {
            return null;
        }

        $refHost = parse_url($referer, PHP_URL_HOST);
        if ($refHost !== null && $refHost !== $request->getHost()) {
            return null; // 外部サイトからの流入はパスを持たない
        }

        $path = parse_url($referer, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            return '/';
        }

        return mb_substr($path, 0, 255);
    }
}
