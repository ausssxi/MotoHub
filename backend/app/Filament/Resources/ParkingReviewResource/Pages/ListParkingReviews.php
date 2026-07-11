<?php

namespace App\Filament\Resources\ParkingReviewResource\Pages;

use App\Filament\Resources\ParkingReviewResource;
use Filament\Resources\Pages\ListRecords;

class ListParkingReviews extends ListRecords
{
    protected static string $resource = ParkingReviewResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
