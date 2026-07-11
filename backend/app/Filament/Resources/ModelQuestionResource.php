<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ModelQuestionResource\Pages;
use App\Models\ModelQuestion;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * 車種Q&A 質問のUGC管理（重複整理・荒らし対応の削除）。
 * 削除は物理削除＝ぶら下がる回答も cascadeOnDelete で消え、通報は deleting でpurgeされる。
 * 一般ユーザー向けUIは無く、is_admin のみがこのパネル(/admin)に到達できる。
 */
class ModelQuestionResource extends Resource
{
    protected static ?string $model = ModelQuestion::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-ellipsis';

    protected static ?string $navigationGroup = 'UGC管理';

    protected static ?string $navigationLabel = 'Q&A質問';

    protected static ?int $navigationSort = 10;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('bikeModel')->withCount('approvedAnswers');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('投稿日')->dateTime('Y-m-d H:i')->sortable(),

                Tables\Columns\TextColumn::make('bikeModel.name')
                    ->label('車種')->searchable()->sortable(),

                Tables\Columns\TextColumn::make('title')
                    ->label('質問')->limit(50)->searchable()->wrap(),

                Tables\Columns\TextColumn::make('approved_answers_count')
                    ->label('回答')->badge()->color('gray'),

                // 投稿者は表示名のみ（本名・IPハッシュ等のPIIは出さない）
                Tables\Columns\TextColumn::make('display_name')
                    ->label('投稿者'),

                Tables\Columns\ToggleColumn::make('is_approved')
                    ->label('公開'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('bike_model_id')
                    ->label('車種')
                    ->relationship('bikeModel', 'name')
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
            'index' => Pages\ListModelQuestions::route('/'),
        ];
    }
}
