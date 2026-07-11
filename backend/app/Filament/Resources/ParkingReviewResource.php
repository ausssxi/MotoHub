<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ParkingReviewResource\Pages;
use App\Models\ParkingReview;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * 駐車場レビューのUGC管理（荒らし・不適切投稿の削除）。物理削除。通報は deleting でpurge。
 */
class ParkingReviewResource extends Resource
{
    protected static ?string $model = ParkingReview::class;

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';

    protected static ?string $navigationGroup = 'UGC管理';

    protected static ?string $navigationLabel = '駐車場レビュー';

    protected static ?int $navigationSort = 12;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('bikeParking');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('投稿日')->dateTime('Y-m-d H:i')->sortable(),

                Tables\Columns\TextColumn::make('bikeParking.name')
                    ->label('駐車場')->searchable()->sortable(),

                Tables\Columns\TextColumn::make('rating')
                    ->label('評価')->badge()->color('gray'),

                Tables\Columns\TextColumn::make('body')
                    ->label('本文')->limit(50)->searchable()->wrap(),

                Tables\Columns\TextColumn::make('display_name')
                    ->label('投稿者'),

                Tables\Columns\ToggleColumn::make('is_approved')
                    ->label('公開'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('bike_parking_id')
                    ->label('駐車場')
                    ->relationship('bikeParking', 'name')
                    ->searchable()
                    ->preload(),

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
            'index' => Pages\ListParkingReviews::route('/'),
        ];
    }
}
