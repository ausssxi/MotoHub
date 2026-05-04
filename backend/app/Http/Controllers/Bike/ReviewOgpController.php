<?php

declare(strict_types=1);

namespace App\Http\Controllers\Bike;

use App\Http\Controllers\Controller;
use App\Models\BikeModel;
use App\Models\Manufacturer;
use App\Models\Review;
use App\Services\Twitter\ReviewChartService;
use Illuminate\Support\Facades\Storage;

final class ReviewOgpController extends Controller
{
    private const CACHE_DIR = 'ogp/reviews';

    public function __construct(
        private readonly ReviewChartService $chartService,
    ) {}

    /**
     * slug ベースのルートから呼ばれる（車種全体 or 個別レビュー）
     */
    public function showBySlug(string $mfrSlug, string $modelSlug, ?int $reviewId = null)
    {
        $manufacturer = Manufacturer::where('slug', $mfrSlug)->first();
        if (!$manufacturer) {
            return $this->fallbackImage();
        }

        $bikeModel = BikeModel::where('manufacturer_id', $manufacturer->id)
            ->where('slug', $modelSlug)
            ->first();

        if (!$bikeModel) {
            return $this->fallbackImage();
        }

        if ($reviewId) {
            return $this->generateOgpForReview($reviewId, $bikeModel->id);
        }

        return $this->generateOgpForModel($bikeModel->id);
    }

    /**
     * 車種全体の最新レビューでOGP生成
     */
    private function generateOgpForModel(int $bikeModelId)
    {
        $cachePath = self::CACHE_DIR . "/{$bikeModelId}.png";

        if (Storage::disk('public')->exists($cachePath)) {
            return $this->imageResponse(Storage::disk('public')->path($cachePath));
        }

        $review = Review::where('bike_model_id', $bikeModelId)
            ->where('is_approved', true)
            ->whereNotNull('rating_design')
            ->whereNotNull('rating_engine')
            ->whereNotNull('rating_handling')
            ->whereNotNull('rating_fuel_economy')
            ->whereNotNull('rating_cost_performance')
            ->latest()
            ->first();

        if (!$review) {
            return $this->fallbackImage();
        }

        $review->loadMissing(['bikeModel.manufacturer']);

        $png = $this->chartService->generateReviewCard($review);
        if (!$png) {
            return $this->fallbackImage();
        }

        Storage::disk('public')->makeDirectory(self::CACHE_DIR);
        Storage::disk('public')->put($cachePath, $png);

        return $this->imageResponse(Storage::disk('public')->path($cachePath));
    }

    /**
     * 個別レビューのOGP生成（タイトル・ニックネーム入り）
     */
    private function generateOgpForReview(int $reviewId, int $bikeModelId)
    {
        $cachePath = self::CACHE_DIR . "/{$bikeModelId}-{$reviewId}.png";

        if (Storage::disk('public')->exists($cachePath)) {
            return $this->imageResponse(Storage::disk('public')->path($cachePath));
        }

        $review = Review::where('id', $reviewId)
            ->where('bike_model_id', $bikeModelId)
            ->where('is_approved', true)
            ->whereNotNull('rating_design')
            ->whereNotNull('rating_engine')
            ->whereNotNull('rating_handling')
            ->whereNotNull('rating_fuel_economy')
            ->whereNotNull('rating_cost_performance')
            ->first();

        if (!$review) {
            // レーティング詳細がないレビューは車種全体OGPにフォールバック
            return $this->generateOgpForModel($bikeModelId);
        }

        $review->loadMissing(['bikeModel.manufacturer']);

        $png = $this->chartService->generateReviewCard($review);
        if (!$png) {
            return $this->generateOgpForModel($bikeModelId);
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

    /**
     * レビューやモデルが見つからない場合のデフォルトOGP画像
     */
    private function fallbackImage(): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        return response()->file(public_path('images/twitter_template.png'), [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
