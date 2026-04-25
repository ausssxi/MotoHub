<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Listing;
use App\Services\Twitter\DealChartService;
use Illuminate\Support\Facades\Storage;

final class DealOgpController extends Controller
{
    private const CACHE_DIR = 'ogp/deals';

    public function __construct(
        private readonly DealChartService $chartService,
    ) {}

    public function show(Listing $listing)
    {
        $cachePath = self::CACHE_DIR . "/{$listing->id}.png";

        if (Storage::disk('public')->exists($cachePath)) {
            return $this->imageResponse(Storage::disk('public')->path($cachePath));
        }

        $png = $this->chartService->generateChartImage($listing);
        if (!$png) {
            abort(404);
        }

        Storage::disk('public')->makeDirectory(self::CACHE_DIR);
        Storage::disk('public')->put($cachePath, $png);

        return $this->imageResponse(Storage::disk('public')->path($cachePath));
    }

    private function imageResponse(string $path): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        return response()->file($path, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
