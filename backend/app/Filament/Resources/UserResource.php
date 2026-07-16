<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use App\Services\Profile\AvatarImageService;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * ユーザーのアバター・モデレーション（UGC管理）。
 * 不適切アバターの通報(user_avatar)を受けた運営が、対象ユーザーのアバターを削除する導線。
 * 削除するとフロントは <x-user-avatar> のイニシャル/汎用アイコンにフォールバックする。
 * ※ユーザー本体の削除はここでは提供しない（アバターの取り下げに限定＝最小の権限）。
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-circle';

    protected static ?string $navigationGroup = 'UGC管理';

    protected static ?string $navigationLabel = 'ユーザーアバター';

    protected static ?int $navigationSort = 20;

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\ImageColumn::make('avatar_url')
                    ->label('アイコン')->circular()
                    ->getStateUsing(fn (User $r): ?string => $r->avatar_url),

                Tables\Columns\TextColumn::make('review_display_name')
                    ->label('公開表示名')->searchable()->sortable()
                    ->placeholder('（未設定）'),

                // 本名/メールは運営の同定用（Filamentは is_admin 限定・公開面には出ない）。
                Tables\Columns\TextColumn::make('name')
                    ->label('お名前')->searchable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('email')
                    ->label('メール')->searchable()->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('has_uploaded_avatar')
                    ->label('自前アイコン')->boolean()
                    ->getStateUsing(fn (User $r): bool => $r->avatar_path !== null),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('登録日')->dateTime('Y-m-d')->sortable()->toggleable(),
            ])
            ->filters([
                Tables\Filters\Filter::make('has_uploaded_avatar')
                    ->label('自前アイコンあり')
                    ->query(fn (Builder $q): Builder => $q->whereNotNull('avatar_path')),
            ])
            ->actions([
                // 不適切アバターの取り下げ（行＝avatar_path クリア＋実ファイル削除）。
                Tables\Actions\Action::make('removeAvatar')
                    ->label('アイコン削除')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('プロフィールアイコンを削除')
                    ->modalDescription('このユーザーの自前アイコンを削除します。既定のアイコンに戻ります。')
                    ->visible(fn (User $r): bool => $r->avatar_path !== null)
                    ->action(fn (User $r) => app(AvatarImageService::class)->remove($r)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
        ];
    }
}
