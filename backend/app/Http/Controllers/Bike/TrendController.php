<?php

declare(strict_types=1);

namespace App\Http\Controllers\Bike;

use App\Http\Controllers\Controller;
use App\Services\Bike\TrendService;
use Illuminate\Contracts\View\View;

final class TrendController extends Controller
{
    public function __construct(
        private readonly TrendService $trendService
    ) {}

    public function index(): View
    {
        // 過去30日間の変動をランキング化して取得
        $trends = $this->trendService->getRanking(30);
        
        return view('bikes.trends', compact('trends'));
    }
}