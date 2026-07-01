<?php

namespace App\Filament\Resources\ShopAcceptanceReportResource\Pages;

use App\Filament\Resources\ShopAcceptanceReportResource;
use Filament\Resources\Pages\ListRecords;

class ListShopAcceptanceReports extends ListRecords
{
    protected static string $resource = ShopAcceptanceReportResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
