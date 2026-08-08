<?php

namespace App\Filament\Resources\RoadsideStationResource\Pages;

use App\Filament\Resources\RoadsideStationResource;
use Filament\Resources\Pages\EditRecord;

class EditRoadsideStation extends EditRecord
{
    protected static string $resource = RoadsideStationResource::class;

    protected function getHeaderActions(): array
    {
        // 削除は一切行わないため、ヘッダーアクションは置かない。
        return [];
    }
}
