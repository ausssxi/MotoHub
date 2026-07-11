<?php

namespace App\Filament\Resources\ModelAnswerResource\Pages;

use App\Filament\Resources\ModelAnswerResource;
use Filament\Resources\Pages\ListRecords;

class ListModelAnswers extends ListRecords
{
    protected static string $resource = ModelAnswerResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
