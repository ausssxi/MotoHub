<?php

namespace App\Filament\Resources\BikeParkingResource\Pages;

use App\Filament\Resources\BikeParkingResource;
use Filament\Resources\Pages\ListRecords;

class ListBikeParkings extends ListRecords
{
    protected static string $resource = BikeParkingResource::class;

    protected function getHeaderActions(): array
    {
        // 編集専用リソースのため新規作成アクションは置かない。
        return [];
    }
}
