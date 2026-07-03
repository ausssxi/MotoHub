<?php

namespace App\Filament\Resources\ShopSubmissionResource\Pages;

use App\Filament\Resources\ShopSubmissionResource;
use App\Models\Shop;
use App\Models\ShopSubmission;
use App\Services\Shop\ShopSubmissionImageService;
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
        $isUrlSuggestion = fn (): bool => $this->record->target_shop_id !== null;

        return [
            // 0. 公式URL提案の承認（対象店にURL充填・新規店は作らない）
            Actions\Action::make('approveUrl')
                ->label('承認（公式URL反映）')
                ->icon('heroicon-o-link')
                ->color('success')
                ->requiresConfirmation()
                ->modalDescription('対象店の公式サイトURLが空なら反映します（既存値があれば保持・新規店は作りません）。')
                ->visible(fn (): bool => $isPending() && $isUrlSuggestion())
                ->action(function () {
                    app(ShopSubmissionService::class)->approveUrlSuggestion($this->record);
                    Notification::make()->success()->title('公式URLを反映しました')->send();
                    $this->redirect(ShopSubmissionResource::getUrl('index'));
                }),

            // 1. 承認（新規登録）※新規店投稿のみ
            Actions\Action::make('approveNew')
                ->label('承認（新規登録）')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalDescription('新しい店舗として登録します（source=user・repair_only）。住所があればジオコーディングします。')
                ->visible(fn (): bool => $isPending() && ! $isUrlSuggestion())
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
                ->visible(fn (): bool => $isPending() && ! $isUrlSuggestion())
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

            // 不適切画像だけ削除して店自体は承認する等のケース用
            Actions\Action::make('deleteImage')
                ->label('画像を削除')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn (): bool => (bool) $this->record->image_path)
                ->action(function () {
                    app(ShopSubmissionImageService::class)->deletePending($this->record->image_path);
                    $this->record->update(['image_path' => null]);
                    Notification::make()->success()->title('投稿画像を削除しました')->send();
                    $this->redirect(ShopSubmissionResource::getUrl('edit', ['record' => $this->record]));
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
