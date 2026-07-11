<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NewsCommentResource\Pages;
use App\Models\NewsComment;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * ニュースコメントのUGC管理（荒らし・不適切投稿の削除）。物理削除。通報は deleting でpurge。
 */
class NewsCommentResource extends Resource
{
    protected static ?string $model = NewsComment::class;

    protected static ?string $navigationIcon = 'heroicon-o-newspaper';

    protected static ?string $navigationGroup = 'UGC管理';

    protected static ?string $navigationLabel = 'ニュースコメント';

    protected static ?int $navigationSort = 13;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('news');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('投稿日')->dateTime('Y-m-d H:i')->sortable(),

                Tables\Columns\TextColumn::make('news.title')
                    ->label('記事')->limit(30)->searchable()->wrap(),

                Tables\Columns\TextColumn::make('body')
                    ->label('コメント')->limit(50)->searchable()->wrap(),

                Tables\Columns\TextColumn::make('display_name')
                    ->label('投稿者'),

                Tables\Columns\TextColumn::make('likes_count')
                    ->label('いいね')->badge()->color('gray'),

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
            'index' => Pages\ListNewsComments::route('/'),
        ];
    }
}
