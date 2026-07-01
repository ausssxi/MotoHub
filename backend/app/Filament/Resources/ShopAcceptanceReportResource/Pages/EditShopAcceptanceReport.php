<?php

namespace App\Filament\Resources\ShopAcceptanceReportResource\Pages;

use App\Filament\Resources\ShopAcceptanceReportResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditShopAcceptanceReport extends EditRecord
{
    protected static string $resource = ShopAcceptanceReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
