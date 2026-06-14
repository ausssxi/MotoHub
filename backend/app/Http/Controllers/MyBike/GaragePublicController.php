<?php

declare(strict_types=1);

namespace App\Http\Controllers\MyBike;

use App\Http\Controllers\Controller;
use App\Models\MyBike;

class GaragePublicController extends Controller
{
    public function index()
    {
        // 公開 opt-in 済みの愛車のみ
        $bikes = MyBike::where('is_public', true)
            ->with(['user', 'bikeModel.manufacturer', 'images'])
            ->latest()
            ->paginate(20);

        return view('mybikes.public_index', compact('bikes'));
    }

    public function show(MyBike $myBike)
    {
        // 非公開の愛車は公開URLで一切描画しない（情報漏洩防止）
        abort_unless($myBike->is_public, 404);

        $myBike->load(['user', 'bikeModel.manufacturer', 'fuelLogs', 'maintenanceLogs', 'images']);

        return view('mybikes.public_show', compact('myBike'));
    }
}
