<?php

declare(strict_types=1);

namespace App\Http\Controllers\MyBike;

use App\Http\Controllers\Controller;
use App\Models\MyBike;

class GaragePublicController extends Controller
{
    public function index()
    {
        $bikes = MyBike::with(['user', 'bikeModel.manufacturer'])
            ->latest()
            ->paginate(20);

        return view('mybikes.public_index', compact('bikes'));
    }

    public function show(MyBike $myBike)
    {
        $myBike->load(['user', 'bikeModel.manufacturer', 'fuelLogs', 'maintenanceLogs']);

        return view('mybikes.public_show', compact('myBike'));
    }
}
