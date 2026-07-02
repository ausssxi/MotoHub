<?php

namespace App\Filament\Resources\ShopSubmissionResource\Pages;

use App\Filament\Resources\ShopSubmissionResource;
use Filament\Resources\Pages\ListRecords;

class ListShopSubmissions extends ListRecords
{
    protected static string $resource = ShopSubmissionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
