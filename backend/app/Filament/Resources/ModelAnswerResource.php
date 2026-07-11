<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ModelAnswerResource\Pages;
use App\Models\ModelAnswer;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * 車種Q&A 回答のUGC管理（個別の荒らし・不適切回答の削除）。物理削除。通報は deleting でpurge。
 */
class ModelAnswerResource extends Resource
{
    protected static ?string $model = ModelAnswer::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-oval-left';

    protected static ?string $navigationGroup = 'UGC管理';

    protected static ?string $navigationLabel = 'Q&A回答';

    protected static ?int $navigationSort = 11;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('question.bikeModel');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('投稿日')->dateTime('Y-m-d H:i')->sortable(),

                Tables\Columns\TextColumn::make('question.bikeModel.name')
                    ->label('車種')->searchable()->sortable(),

                Tables\Columns\TextColumn::make('question.title')
                    ->label('質問')->limit(30)->wrap(),

                Tables\Columns\TextColumn::make('body')
                    ->label('回答')->limit(50)->searchable()->wrap(),

                Tables\Columns\TextColumn::make('display_name')
                    ->label('投稿者'),

                Tables\Columns\TextColumn::make('helpful_count')
                    ->label('参考')->badge()->color('gray'),

                Tables\Columns\ToggleColumn::make('is_approved')
                    ->label('公開'),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_approved')
                    ->label('公開状態'),
            ])
            ->actions([
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListModelAnswers::route('/'),
        ];
    }
}
