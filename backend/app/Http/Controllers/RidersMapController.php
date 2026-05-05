<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\View\View;

final class RidersMapController extends Controller
{
    public function index(): View
    {
        return view('riders.map');
    }
}
