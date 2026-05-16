<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\TouringGuide;
use App\Models\TouringSpot;
use App\Services\Blog\ShortcodeService;
use App\Services\MarkdownService;
use Illuminate\Http\Request;

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

        return view('touring.show', compact('guide', 'html', 'hasMap', 'toc'));
    }
}
