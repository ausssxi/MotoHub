<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReportResource\Pages;
use App\Models\ParkingReview;
use App\Models\Report;
use App\Models\ShopAcceptanceReport;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ReportResource extends Resource
{
    protected static ?string $model = Report::class;

    protected static ?string $navigationIcon = 'heroicon-o-flag';

    protected static ?string $navigationLabel = '通報';

    protected static ?int $navigationSort = 3;

    /** 未対応（open）件数をナビにバッジ表示（0なら非表示）。 */
    public static function getNavigationBadge(): ?string
    {
        $open = static::getModel()::where('status', Report::STATUS_OPEN)->count();

        return $open > 0 ? (string) $open : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('reportable');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('通報日時')->dateTime('Y-m-d H:i')->sortable(),

                Tables\Columns\TextColumn::make('reason')
                    ->label('理由')
                    ->formatStateUsing(fn (?string $state) => Report::REASONS[$state] ?? '—')
                    ->badge(),

                Tables\Columns\TextColumn::make('target')
                    ->label('対象の投稿')
                    ->getStateUsing(function (Report $record): string {
                        $r = $record->reportable;
                        if ($r instanceof ShopAcceptanceReport) {
                            $shop = $r->shop?->name ?? '(店舗不明)';

                            return '口コミ／'.$shop.'：「'.\Illuminate\Support\Str::limit((string) $r->comment, 40).'」';
                        }
                        if ($r instanceof ParkingReview) {
                            $parking = $r->bikeParking?->name ?? '(駐車場不明)';

                            return '駐車場レビュー／'.$parking.'：「'.\Illuminate\Support\Str::limit((string) $r->body, 40).'」';
                        }

                        return '(削除済み or 対象なし)';
                    })
                    ->wrap(),

                Tables\Columns\SelectColumn::make('status')
                    ->label('対応')
                    ->options(Report::STATUSES),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('対応状態')->options(Report::STATUSES),
            ])
            ->actions([
                // 対象のショップ詳細を新規タブで開いて内容を確認 → 既存の削除/非公開で対処。
                Tables\Actions\Action::make('view_target')
                    ->label('対象を開く')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(function (Report $record): ?string {
                        $r = $record->reportable;
                        if ($r instanceof ShopAcceptanceReport && $r->shop) {
                            return route('shops.show', $r->shop);
                        }
                        if ($r instanceof ParkingReview && $r->bikeParking) {
                            return route('parking.show', $r->bikeParking);
                        }

                        return null;
                    })
                    ->openUrlInNewTab()
                    ->visible(function (Report $record): bool {
                        $r = $record->reportable;

                        return ($r instanceof ShopAcceptanceReport && $r->shop !== null)
                            || ($r instanceof ParkingReview && $r->bikeParking !== null);
                    }),

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
            'index' => Pages\ListReports::route('/'),
        ];
    }
}
