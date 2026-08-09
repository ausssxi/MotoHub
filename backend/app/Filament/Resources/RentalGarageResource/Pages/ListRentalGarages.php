<?php

namespace App\Filament\Resources\RentalGarageResource\Pages;

use App\Filament\Resources\RentalGarageResource;
use Filament\Resources\Pages\ListRecords;

class ListRentalGarages extends ListRecords
{
    protected static string $resource = RentalGarageResource::class;

    protected function getHeaderActions(): array
    {
        // 編集専用リソースのため新規作成アクションは置かない。
        return [];
    }
}
