<?php

namespace App\Filament\Resources\RentalGarageResource\Pages;

use App\Filament\Resources\RentalGarageResource;
use Filament\Resources\Pages\EditRecord;

class EditRentalGarage extends EditRecord
{
    protected static string $resource = RentalGarageResource::class;

    protected function getHeaderActions(): array
    {
        // 削除は一切行わないため、ヘッダーアクションは置かない。
        return [];
    }
}
