<?php

declare(strict_types=1);

use App\Support\TouringNearby;

it('derives the search radius from distance_km with clamps', function () {
    expect(TouringNearby::radiusKm(50))->toBe(12.0);   // 50/5=10 → 下限12
    expect(TouringNearby::radiusKm(80))->toBe(16.0);   // 80/5=16
    expect(TouringNearby::radiusKm(200))->toBe(40.0);  // 200/5=40
    expect(TouringNearby::radiusKm(300))->toBe(45.0);  // 300/5=60 → 上限45
});

it('falls back to the default radius when distance is unknown', function () {
    expect(TouringNearby::radiusKm(null))->toBe(20.0);
    expect(TouringNearby::radiusKm(0))->toBe(20.0);
});
