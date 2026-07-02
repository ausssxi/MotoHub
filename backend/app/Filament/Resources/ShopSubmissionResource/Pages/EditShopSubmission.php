<?php

namespace App\Filament\Resources\ShopSubmissionResource\Pages;

use App\Filament\Resources\ShopSubmissionResource;
use App\Models\Shop;
use App\Models\ShopSubmission;
use App\Services\Shop\ShopSubmissionService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditShopSubmission extends EditRecord
{
    protected static string $resource = ShopSubmissionResource::class;

    protected function getHeaderActions(): array
    {
        $isPending = fn (): bool => $this->record->status === ShopSubmission::STATUS_PENDING;

        return [
            // 1. 承認（新規登録）
            Actions\Action::make('approveNew')
                ->label('承認（新規登録）')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalDescription('新しい店舗として登録します（source=user・repair_only）。住所があればジオコーディングします。')
                ->visible($isPending)
                ->action(function () {
                    $shop = app(ShopSubmissionService::class)->approveAsNew($this->record);
                    Notification::make()->success()
                        ->title('新規登録しました')
                        ->body("shop #{$shop->id}「{$shop->name}」を作成しました。")
                        ->send();
                    $this->redirect(ShopSubmissionResource::getUrl('index'));
                }),

            // 2. 既存店に統合
            Actions\Action::make('merge')
                ->label('既存店に統合')
                ->icon('heroicon-o-arrows-pointing-in')
                ->color('primary')
                ->visible($isPending)
                ->form([
                    Forms\Components\Select::make('shop_id')
                        ->label('統合先の既存店舗')
                        ->options(fn (): array => app(ShopSubmissionService::class)
                            ->duplicateCandidates($this->record)
                            ->mapWithKeys(fn (Shop $s): array => [$s->id => "#{$s->id} {$s->name}（{$s->address}）"])
                            ->all())
                        ->required()
                        ->helperText('重複候補のみ表示。既存店のカラム（service_tags・shop_type・住所等）は変更せず、受け入れ情報だけを付与します。公式サイトURLは既存店が未登録の場合のみ反映します（登録済みなら保持）。'),
                ])
                ->action(function (array $data) {
                    $shop = Shop::findOrFail($data['shop_id']);
                    app(ShopSubmissionService::class)->mergeInto($this->record, $shop);
                    Notification::make()->success()
                        ->title('統合しました')
                        ->body("shop #{$shop->id}「{$shop->name}」に受け入れ情報を付与しました。")
                        ->send();
                    $this->redirect(ShopSubmissionResource::getUrl('index'));
                }),

            // 3. 却下
            Actions\Action::make('reject')
                ->label('却下')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->visible($isPending)
                ->action(function () {
                    app(ShopSubmissionService::class)->reject($this->record);
                    Notification::make()->warning()->title('却下しました')->send();
                    $this->redirect(ShopSubmissionResource::getUrl('index'));
                }),
        ];
    }
}
