<?php

namespace App\Filament\Resources\RoadsideStationResource\Pages;

use App\Filament\Resources\RoadsideStationResource;
use Filament\Resources\Pages\ListRecords;

class ListRoadsideStations extends ListRecords
{
    protected static string $resource = RoadsideStationResource::class;

    protected function getHeaderActions(): array
    {
        // 編集専用リソースのため新規作成アクションは置かない。
        return [];
    }
}
