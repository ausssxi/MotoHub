<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ShopAcceptanceReportResource\Pages;
use App\Models\ShopAcceptanceReport;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class ShopAcceptanceReportResource extends Resource
{
    protected static ?string $model = ShopAcceptanceReport::class;

    protected static ?string $navigationIcon = 'heroicon-o-hand-thumb-up';

    protected static ?string $navigationLabel = '店舗受け入れ情報';

    protected static ?int $navigationSort = 2;

    /** 承認待ち件数をナビにバッジ表示（0なら非表示）。 */
    public static function getNavigationBadge(): ?string
    {
        $pending = static::getModel()::where('is_approved', false)->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('受け入れ情報')->schema([
                Forms\Components\Select::make('shop_id')
                    ->relationship('shop', 'name')
                    ->label('店舗')
                    ->searchable()
                    ->preload()
                    ->required(),

                Forms\Components\Toggle::make('is_approved')
                    ->label('事実系フラグを承認して公開する')
                    ->onColor('success')
                    ->offColor('danger')
                    ->default(false),

                // コメントは即反映（投稿時に true）。不適切なら OFF にして個別に非表示化できる。
                Forms\Components\Toggle::make('comment_approved')
                    ->label('コメントを公開する（即反映・不適切ならOFF）')
                    ->onColor('success')
                    ->offColor('danger')
                    ->default(false),

                Forms\Components\Fieldset::make('報告された受け入れ情報')->schema([
                    Forms\Components\Toggle::make('accepts_other_store')->label('他店購入車OK'),
                    Forms\Components\Toggle::make('accepts_bring_in')->label('持ち込みOK'),
                    Forms\Components\Toggle::make('pickup_service')->label('引き取り対応'),
                    Forms\Components\Toggle::make('walk_in_ok')->label('飛び込みOK'),
                ])->columns(2),

                Forms\Components\Textarea::make('comment')
                    ->label('コメント')
                    ->maxLength(120)
                    ->rows(3)
                    ->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('ID')->sortable()->width(50),

                Tables\Columns\TextColumn::make('shop.name')
                    ->label('店舗')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->url(fn (ShopAcceptanceReport $r): string => route('shops.show', $r->shop_id), true),

                Tables\Columns\TextColumn::make('flags')
                    ->label('受け入れ情報')
                    ->state(function (ShopAcceptanceReport $r): string {
                        $on = [];
                        foreach (ShopAcceptanceReport::FLAGS as $col => $label) {
                            if ($r->{$col}) {
                                $on[] = $label;
                            }
                        }

                        return $on ? implode(' / ', $on) : '—';
                    })
                    ->wrap(),

                Tables\Columns\TextColumn::make('submitter_name')
                    ->label('投稿者')
                    ->badge(fn (ShopAcceptanceReport $r): bool => $r->user_id !== null)
                    ->formatStateUsing(fn (?string $state, ShopAcceptanceReport $r): string => ($state ?: '名無しライダー').($r->user_id ? '（ログイン）' : ''))
                    ->toggleable(),

                Tables\Columns\TextColumn::make('comment')
                    ->label('コメント')
                    ->limit(60)
                    ->wrap()
                    ->toggleable(),

                Tables\Columns\ToggleColumn::make('is_approved')->label('事実系公開'),

                // コメントは即反映（comment_approved=true）。運営はここでコメントだけ個別に非表示化できる。
                Tables\Columns\ToggleColumn::make('comment_approved')->label('コメント公開'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('投稿日')
                    ->dateTime('Y/m/d H:i')
                    ->sortable()
                    ->color('gray'),
            ])
            ->defaultSort('created_at', 'desc')
            // 既定で「承認待ち」を表示（運用のメイン画面）
            ->filters([
                Tables\Filters\SelectFilter::make('is_approved')
                    ->label('ステータス')
                    ->options([0 => '承認待ち', 1 => '公開中'])
                    ->default(0),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('approve')
                        ->label('承認する')
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(fn (Collection $records) => $records->each->update(['is_approved' => true])),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListShopAcceptanceReports::route('/'),
            'edit' => Pages\EditShopAcceptanceReport::route('/{record}/edit'),
        ];
    }
}
