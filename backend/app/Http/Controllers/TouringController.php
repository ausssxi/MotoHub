<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\TouringGuide;
use App\Models\TouringSpot;
use App\Services\Blog\ShortcodeService;
use App\Services\MarkdownService;
use App\Support\SeasonalSpots;
use App\Support\TouringNearby;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

final class TouringController extends Controller
{
    public function index(Request $request)
    {
        $query = TouringGuide::published()
            ->with('author')
            ->orderByDesc('published_at');

        if ($prefecture = $request->query('prefecture')) {
            $query->where('prefecture', $prefecture);
        }

        $guides = $query->paginate(12)->withQueryString();

        $prefectures = TouringGuide::published()
            ->select('prefecture')
            ->distinct()
            ->orderBy('prefecture')
            ->pluck('prefecture');

        $spotsByPrefecture = TouringSpot::orderBy('name')
            ->get(['prefecture', 'slug', 'name'])
            ->groupBy('prefecture');

        return view('touring.index', compact('guides', 'prefectures', 'spotsByPrefecture'));
    }

    /**
     * 紅葉ツーリング特集（季節ハブ・1ページのみ）。確度別スコア＋エリア別に集約。
     * データ更新は稀なので 24h キャッシュ（世代 v1）。route:clear のみで反映可。
     */
    public function autumn()
    {
        $keyword = (string) config('touring.autumn_keyword', '紅葉');

        $data = Cache::remember('touring:autumn:v1', 86400, function () use ($keyword) {
            $spots = SeasonalSpots::forKeyword($keyword);

            // 秋に強いガイド（best_season に 10月/11月/秋）。0本ならセクションごと出さない。
            $guides = TouringGuide::published()
                ->where(function ($q) {
                    $q->where('best_season', 'like', '%10月%')
                        ->orWhere('best_season', 'like', '%11月%')
                        ->orWhere('best_season', 'like', '%秋%');
                })
                ->orderByDesc('published_at')
                ->get(['slug', 'title', 'excerpt', 'best_season'])
                ->map(fn (TouringGuide $g): array => [
                    'title' => (string) $g->title,
                    'excerpt' => (string) $g->excerpt,
                    'best_season' => (string) $g->best_season,
                    'url' => route('touring.show', $g->slug),
                ])->all();

            return $spots + ['guides' => $guides];
        });

        // キーワードはビューに直書きしない（見出し・リードで {{ $keyword }} を使う）。
        return view('touring.autumn', $data + ['keyword' => $keyword]);
    }

    public function show(string $slug, MarkdownService $markdown, ShortcodeService $shortcode)
    {
        $guide = TouringGuide::where('slug', $slug)
            ->published()
            ->with('author')
            ->firstOrFail();

        $html = $markdown->toHtml($guide->body);
        $shortcodeResult = $shortcode->processShortcodes($html);
        $html = $shortcodeResult['html'];
        $hasMap = true;
        $toc = $markdown->generateToc($guide->body);

        // ルート周辺の立ち寄り先（スポット/道の駅/GS/洗車場）。空間クエリは TouringNearby に集約。
        $nearby = (new TouringNearby)->forGuide($guide);

        return view('touring.show', compact('guide', 'html', 'hasMap', 'toc', 'nearby'));
    }
}
