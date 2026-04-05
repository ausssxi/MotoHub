<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\BikeModel;
use App\Models\BikeNews;
use App\Models\Listing;
use App\Models\Manufacturer;
use App\Models\NewsComment;
use App\Models\NewsCommentLike;
use App\Models\NewsPick;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class NewsController extends Controller
{
    /**
     * ニュース一覧（メーカー・車種フィルタ対応）
     */
    public function index(Request $request): View
    {
        $manufacturerId = $request->query('manufacturer_id');
        $bikeModelId = $request->query('bike_model_id');

        $query = BikeNews::latest();

        if ($bikeModelId) {
            $query->where('bike_model_id', $bikeModelId);
        } elseif ($manufacturerId) {
            $query->where('manufacturer_id', $manufacturerId);
        }

        $news = $query->paginate(20)->withQueryString();

        // ニュースが存在するメーカー一覧
        $manufacturers = Manufacturer::orderBy('name')
            ->whereIn('id', BikeNews::whereNotNull('manufacturer_id')->distinct()->pluck('manufacturer_id'))
            ->get();

        // 選択メーカーの車種サブタブ（bike_newsに紐づくもののみ、件数順）
        $modelTabs = collect();
        if ($manufacturerId) {
            $modelTabs = BikeModel::select('bike_models.id', 'bike_models.name')
                ->join('bike_news', 'bike_news.bike_model_id', '=', 'bike_models.id')
                ->where('bike_models.manufacturer_id', $manufacturerId)
                ->groupBy('bike_models.id', 'bike_models.name')
                ->orderByRaw('COUNT(*) DESC')
                ->limit(15)
                ->get();
        }

        // 最新コメント1件をプリロード
        $news->load(['comments' => function ($q) {
            $q->latest()->limit(1)->with('user');
        }]);

        // 注目ニュース（1ページ目のみ、コメント+ピック数上位3件）
        $featured = collect();
        if (!$request->filled('page') || (int) $request->query('page') === 1) {
            $featuredQuery = BikeNews::selectRaw('*, (comments_count + picks_count) as engagement')
                ->where(function ($q) {
                    $q->where('comments_count', '>', 0)->orWhere('picks_count', '>', 0);
                })
                ->orderByDesc('engagement')
                ->orderByDesc('published_at')
                ->limit(3);

            if ($bikeModelId) {
                $featuredQuery->where('bike_model_id', $bikeModelId);
            } elseif ($manufacturerId) {
                $featuredQuery->where('manufacturer_id', $manufacturerId);
            }

            $featured = $featuredQuery->get();
        }

        return view('news.index', [
            'news' => $news,
            'manufacturers' => $manufacturers,
            'modelTabs' => $modelTabs,
            'currentManufacturerId' => $manufacturerId,
            'currentBikeModelId' => $bikeModelId,
            'featured' => $featured,
        ]);
    }

    /**
     * ニュース詳細
     */
    public function show(int $id): View
    {
        $newsItem = BikeNews::findOrFail($id);

        // コメント（いいね数順 → 新着順）
        $comments = NewsComment::where('news_id', $id)
            ->with('user')
            ->orderByDesc('likes_count')
            ->orderByDesc('created_at')
            ->get();

        // ピック状態
        $isPicked = false;
        if (auth()->check()) {
            $isPicked = NewsPick::where('news_id', $id)
                ->where('user_id', auth()->id())
                ->exists();
        }

        // ユーザーがいいね済みのコメントID
        $likedCommentIds = [];
        if (auth()->check()) {
            $likedCommentIds = NewsCommentLike::where('user_id', auth()->id())
                ->whereIn('comment_id', $comments->pluck('id'))
                ->pluck('comment_id')
                ->toArray();
        }

        // 関連ニュース（同じメーカーまたは車種）
        $relatedNews = collect();
        if ($newsItem->manufacturer_id || $newsItem->bike_model_id) {
            $relatedNews = BikeNews::where('id', '!=', $id)
                ->where(function ($q) use ($newsItem) {
                    if ($newsItem->bike_model_id) {
                        $q->where('bike_model_id', $newsItem->bike_model_id);
                    }
                    if ($newsItem->manufacturer_id) {
                        $q->orWhere('manufacturer_id', $newsItem->manufacturer_id);
                    }
                })
                ->latest()
                ->limit(5)
                ->get();
        }

        // 関連在庫（bike_model_idがある場合）
        $relatedListings = collect();
        if ($newsItem->bike_model_id) {
            $relatedListings = Listing::with('shop')
                ->where('bike_model_id', $newsItem->bike_model_id)
                ->where('is_sold_out', false)
                ->limit(6)
                ->get();
        }

        return view('news.show', [
            'newsItem' => $newsItem,
            'comments' => $comments,
            'isPicked' => $isPicked,
            'likedCommentIds' => $likedCommentIds,
            'relatedNews' => $relatedNews,
            'relatedListings' => $relatedListings,
        ]);
    }

    /**
     * 車種別ニュース一覧
     */
    public function model(int $bikeModelId): View
    {
        $bikeModel = BikeModel::with('manufacturer')->findOrFail($bikeModelId);

        $news = BikeNews::where('bike_model_id', $bikeModelId)
            ->latest()
            ->paginate(20);

        $news->load(['comments' => function ($q) {
            $q->latest()->limit(1)->with('user');
        }]);

        return view('news.model', [
            'bikeModel' => $bikeModel,
            'news' => $news,
        ]);
    }

    /**
     * コメント投稿
     */
    public function comment(Request $request, int $newsId): JsonResponse
    {
        $request->validate([
            'body' => 'required|string|max:500',
        ]);

        $newsItem = BikeNews::findOrFail($newsId);

        $comment = NewsComment::create([
            'news_id' => $newsId,
            'user_id' => auth()->id(),
            'body'    => $request->input('body'),
        ]);

        $newsItem->increment('comments_count');

        $comment->load('user');

        return response()->json([
            'success' => true,
            'comment' => [
                'id'         => $comment->id,
                'body'       => $comment->body,
                'likes_count' => 0,
                'created_at' => $comment->created_at->format('Y/m/d H:i'),
                'user'       => [
                    'name'   => $comment->user->name,
                    'avatar' => $comment->user->avatar,
                ],
            ],
        ]);
    }

    /**
     * ピックトグル
     */
    public function togglePick(int $newsId): JsonResponse
    {
        $newsItem = BikeNews::findOrFail($newsId);
        $userId = auth()->id();

        $existing = NewsPick::where('news_id', $newsId)
            ->where('user_id', $userId)
            ->first();

        if ($existing) {
            $existing->delete();
            $newsItem->decrement('picks_count');
            $picked = false;
        } else {
            NewsPick::create([
                'news_id'    => $newsId,
                'user_id'    => $userId,
                'created_at' => now(),
            ]);
            $newsItem->increment('picks_count');
            $picked = true;
        }

        return response()->json([
            'success'     => true,
            'picked'      => $picked,
            'picks_count' => $newsItem->fresh()->picks_count,
        ]);
    }

    /**
     * コメントいいねトグル
     */
    public function toggleCommentLike(int $commentId): JsonResponse
    {
        $comment = NewsComment::findOrFail($commentId);
        $userId = auth()->id();

        $existing = NewsCommentLike::where('comment_id', $commentId)
            ->where('user_id', $userId)
            ->first();

        if ($existing) {
            $existing->delete();
            $comment->decrement('likes_count');
            $liked = false;
        } else {
            NewsCommentLike::create([
                'comment_id' => $commentId,
                'user_id'    => $userId,
                'created_at' => now(),
            ]);
            $comment->increment('likes_count');
            $liked = true;
        }

        return response()->json([
            'success'     => true,
            'liked'       => $liked,
            'likes_count' => $comment->fresh()->likes_count,
        ]);
    }
}
