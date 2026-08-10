<?php

namespace App\Filament\Resources\BikeParkingResource\Pages;

use App\Filament\Resources\BikeParkingResource;
use Filament\Resources\Pages\EditRecord;

class EditBikeParking extends EditRecord
{
    protected static string $resource = BikeParkingResource::class;

    protected function getHeaderActions(): array
    {
        // 削除は一切行わないため、ヘッダーアクションは置かない。
        return [];
    }
}
