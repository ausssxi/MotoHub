<?php

namespace App\Filament\Resources\ModelQuestionResource\Pages;

use App\Filament\Resources\ModelQuestionResource;
use Filament\Resources\Pages\ListRecords;

class ListModelQuestions extends ListRecords
{
    protected static string $resource = ModelQuestionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
