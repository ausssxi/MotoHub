<?php

declare(strict_types=1);

namespace App\Http\Controllers\MyBike;

use App\Http\Controllers\Controller;
use App\Models\DiscussionThread;
use App\Models\GarageComment;
use App\Models\NewsComment;
use App\Models\ParkingReview;
use App\Models\Review;
use App\Models\ShopAcceptanceReport;
use App\Models\UserSavedSpot;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * マイページ「マイコンテンツ（投稿履歴）」ハブ。
 * 自分が書いた投稿を1箇所で一覧・元ページ遷移・削除（所有者のみ）できる。
 * 既存UGCロジックは無改変＝自分の分だけを user_id で読み、削除は各モデルの delete() を呼ぶだけ。
 */
final class MyContributionsController extends Controller
{
    /** 削除可能な種別トークン => モデルクラス（任意クラス削除の防止）。 */
    private const DELETABLE = [
        'garage_comment' => GarageComment::class,
        'review' => Review::class,
        'parking_review' => ParkingReview::class,
        'shop_review' => ShopAcceptanceReport::class,
        'news_comment' => NewsComment::class,
        'discussion_thread' => DiscussionThread::class,
    ];

    public function index(): View
    {
        $userId = Auth::id();

        $sections = [
            $this->section('garage_comment', 'ガレージコメント', 'message-circle',
                GarageComment::where('user_id', $userId)->with('myBike.bikeModel')->latest()->get(),
                fn (GarageComment $c) => [
                    'snippet' => $c->body,
                    'target' => $c->myBike?->name ?: ($c->myBike?->bikeModel?->name ?? '愛車ガレージ'),
                    'url' => $c->myBike ? route('garage.public.show', $c->myBike->id) : null,
                    'approved' => $c->status === 'published',
                ]),
            $this->section('review', '車種レビュー', 'star',
                Review::where('user_id', $userId)->with('bikeModel.manufacturer')->latest()->get(),
                fn (Review $r) => [
                    'snippet' => $r->title ?: $r->body,
                    'target' => trim(($r->bikeModel?->manufacturer?->name ?? '').' '.($r->bikeModel?->name ?? '車種')),
                    'url' => $r->bikeModel ? $r->bikeModel->seo_url.'#reviews' : null,
                    'approved' => (bool) $r->is_approved,
                ]),
            $this->section('parking_review', '駐車場レビュー', 'square-parking',
                ParkingReview::where('user_id', $userId)->with('bikeParking')->latest()->get(),
                fn (ParkingReview $r) => [
                    'snippet' => $r->body,
                    'target' => $r->bikeParking?->name ?? '駐車場',
                    'url' => $r->bikeParking ? route('parking.show', $r->bikeParking->id) : null,
                    'approved' => (bool) $r->is_approved,
                ]),
            $this->section('shop_review', 'ショップレビュー', 'store',
                ShopAcceptanceReport::where('user_id', $userId)->with('shop')->latest()->get(),
                fn (ShopAcceptanceReport $r) => [
                    'snippet' => $r->comment,
                    'target' => $r->shop?->name ?? 'ショップ',
                    'url' => $r->shop ? route('shops.show', $r->shop->id) : null,
                    'approved' => (bool) $r->is_approved,
                ]),
            $this->section('news_comment', 'ニュースコメント', 'newspaper',
                NewsComment::where('user_id', $userId)->with('news')->latest()->get(),
                fn (NewsComment $c) => [
                    'snippet' => $c->body,
                    'target' => $c->news?->title ?? 'ニュース',
                    'url' => $c->news ? route('news.show', $c->news->id) : null,
                    'approved' => (bool) $c->is_approved,
                ]),
            $this->section('discussion_thread', 'クチコミ・相談', 'messages-square',
                DiscussionThread::where('user_id', $userId)->with('bikeModel.manufacturer')->latest()->get(),
                fn (DiscussionThread $t) => [
                    'snippet' => $t->display_title,
                    'target' => trim(($t->bikeModel?->manufacturer?->name ?? '').' '.($t->bikeModel?->name ?? '車種')),
                    'url' => $this->threadUrl($t),
                    'approved' => $t->status === 'published',
                ]),
        ];

        $total = collect($sections)->sum(fn ($s) => $s['count']);
        $savedSpotsCount = UserSavedSpot::where('user_id', $userId)->count();

        return view('mypage.contributions', compact('sections', 'total', 'savedSpotsCount'));
    }

    public function destroy(string $type, int $id): RedirectResponse
    {
        $class = self::DELETABLE[$type] ?? abort(404);

        /** @var Model $item */
        $item = $class::findOrFail($id);

        // 所有者本人のみ削除可
        abort_unless((int) $item->user_id === (int) Auth::id(), 403);

        $item->delete(); // PurgesReportsOnDelete 等の既存フックを尊重

        return redirect()->route('mypage.contributions')->with('contribution_deleted', true);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \Illuminate\Database\Eloquent\Model>  $items
     * @param  callable(\Illuminate\Database\Eloquent\Model): array<string, mixed>  $mapper
     * @return array<string, mixed>
     */
    private function section(string $type, string $label, string $icon, $items, callable $mapper): array
    {
        $entries = $items->map(function (Model $item) use ($type, $mapper) {
            $data = $mapper($item);

            return [
                'type' => $type,
                'id' => $item->getKey(),
                'snippet' => Str::limit((string) ($data['snippet'] ?? ''), 80) ?: '(本文なし)',
                'target' => $data['target'] ?? '',
                'url' => $data['url'] ?? null,
                'approved' => $data['approved'] ?? true,
                'created_at' => $item->created_at,
            ];
        })->all();

        return [
            'type' => $type,
            'label' => $label,
            'icon' => $icon,
            'count' => count($entries),
            'entries' => $entries,
        ];
    }

    private function threadUrl(DiscussionThread $t): ?string
    {
        $m = $t->bikeModel;
        if ($m === null) {
            return null;
        }

        return route('bikes.thread', [
            'mfrSlug' => $m->manufacturer->slug ?? $m->manufacturer_id,
            'modelSlug' => $m->slug ?? $m->id,
            'id' => $t->id,
        ]);
    }
}
