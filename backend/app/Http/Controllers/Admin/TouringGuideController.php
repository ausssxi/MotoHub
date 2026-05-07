<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TouringGuide;
use Illuminate\Http\Request;

final class TouringGuideController extends Controller
{
    public function index(Request $request)
    {
        $query = TouringGuide::with('author')->latest();

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $guides = $query->paginate(20);

        return view('admin.touring.index', compact('guides'));
    }

    public function create()
    {
        return view('admin.touring.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'excerpt' => 'nullable|string',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'zoom_level' => 'nullable|integer|min:1|max:18',
            'prefecture' => 'required|string|max:10',
            'difficulty' => 'required|in:初級,中級,上級',
            'distance_km' => 'nullable|integer|min:0',
            'duration_text' => 'nullable|string|max:50',
            'best_season' => 'nullable|string|max:100',
            'status' => 'required|in:draft,published',
        ]);

        $guide = TouringGuide::create([
            'author_id' => $request->user()->id,
            'title' => $validated['title'],
            'body' => $validated['body'],
            'excerpt' => $validated['excerpt'] ?? null,
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'zoom_level' => $validated['zoom_level'] ?? 12,
            'prefecture' => $validated['prefecture'],
            'difficulty' => $validated['difficulty'],
            'distance_km' => $validated['distance_km'] ?? null,
            'duration_text' => $validated['duration_text'] ?? null,
            'best_season' => $validated['best_season'] ?? null,
            'status' => $validated['status'],
            'published_at' => $validated['status'] === 'published' ? now() : null,
        ]);

        return redirect()->route('admin.touring.index')
            ->with('success', 'ツーリングガイドを作成しました。');
    }

    public function edit(int $id)
    {
        $guide = TouringGuide::findOrFail($id);

        return view('admin.touring.edit', compact('guide'));
    }

    public function update(Request $request, int $id)
    {
        $guide = TouringGuide::findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'excerpt' => 'nullable|string',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'zoom_level' => 'nullable|integer|min:1|max:18',
            'prefecture' => 'required|string|max:10',
            'difficulty' => 'required|in:初級,中級,上級',
            'distance_km' => 'nullable|integer|min:0',
            'duration_text' => 'nullable|string|max:50',
            'best_season' => 'nullable|string|max:100',
            'status' => 'required|in:draft,published',
        ]);

        if ($validated['status'] === 'published' && !$guide->published_at) {
            $validated['published_at'] = now();
        }

        $validated['zoom_level'] = $validated['zoom_level'] ?? 12;

        $guide->update($validated);

        return redirect()->route('admin.touring.edit', $guide->id)
            ->with('success', 'ツーリングガイドを更新しました。');
    }

    public function destroy(int $id)
    {
        $guide = TouringGuide::findOrFail($id);
        $guide->delete();

        return redirect()->route('admin.touring.index')
            ->with('success', 'ツーリングガイドをゴミ箱に移動しました。');
    }
}
