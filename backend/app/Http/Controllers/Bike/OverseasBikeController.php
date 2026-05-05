<?php

declare(strict_types=1);

namespace App\Http\Controllers\Bike;

use App\Http\Controllers\Controller;
use App\Services\Bike\OverseasBikeService;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpFoundation\Response;

final class OverseasBikeController extends Controller
{
    public function __construct(
        private readonly OverseasBikeService $service
    ) {}

    public function index(): View
    {
        $data = $this->service->getIndexData();

        return view('bikes.overseas.index', $data);
    }

    public function maker(string $makerSlug): View|Response
    {
        $data = $this->service->getMakerData($makerSlug);

        if (! $data) {
            abort(404);
        }

        return view('bikes.overseas.maker', $data);
    }
}
