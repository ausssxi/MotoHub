<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReviewResource\Pages;
use App\Models\Review;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ReviewResource extends Resource
{
    protected static ?string $model = Review::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationGroup = 'UGC管理';

    protected static ?string $navigationLabel = '車種レビュー';

    protected static ?int $navigationSort = 14;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('レビュー詳細')
                    ->schema([
                        Forms\Components\Select::make('bike_model_id')
                            ->relationship('bikeModel', 'name')
                            ->label('車種')
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\TextInput::make('nickname')
                            ->label('ニックネーム')
                            ->required()
                            ->maxLength(50),

                        Forms\Components\Select::make('rating')
                            ->label('評価')
                            ->options([
                                1 => '★1 (悪い)',
                                2 => '★2 (いまいち)',
                                3 => '★3 (普通)',
                                4 => '★4 (良い)',
                                5 => '★5 (最高)',
                            ])
                            ->required()
                            ->native(false),

                        Forms\Components\Toggle::make('is_approved')
                            ->label('公開する')
                            ->onColor('success')
                            ->offColor('danger')
                            ->default(true),

                        Forms\Components\TextInput::make('title')
                            ->label('タイトル')
                            ->required()
                            ->maxLength(100)
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('body')
                            ->label('レビュー本文')
                            ->required()
                            ->rows(10)
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // ID
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->width(50),

                // 車種名（検索可能）
                Tables\Columns\TextColumn::make('bikeModel.name')
                    ->label('車種')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                // 評価（★表示）
                Tables\Columns\TextColumn::make('rating')
                    ->label('評価')
                    ->formatStateUsing(fn (string $state): string => str_repeat('★', (int) $state))
                    ->color('warning')
                    ->sortable(),

                // タイトルと本文（縦並び）
                Tables\Columns\TextColumn::make('title')
                    ->label('内容')
                    ->description(fn (Review $record): string => mb_strimwidth($record->body, 0, 60, '...'))
                    ->searchable(['title', 'body'])
                    ->wrap(),

                // 投稿者
                Tables\Columns\TextColumn::make('nickname')
                    ->label('投稿者')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                // 公開ステータス（スイッチで切り替え可能）
                Tables\Columns\ToggleColumn::make('is_approved')
                    ->label('公開'),

                // 投稿日時
                Tables\Columns\TextColumn::make('created_at')
                    ->label('投稿日')
                    ->dateTime('Y/m/d H:i')
                    ->sortable()
                    ->color('gray'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('is_approved')
                    ->label('ステータス')
                    ->options([
                        1 => '公開中',
                        0 => '非公開',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReviews::route('/'),
            'create' => Pages\CreateReview::route('/create'),
            'edit' => Pages\EditReview::route('/{record}/edit'),
        ];
    }
}
