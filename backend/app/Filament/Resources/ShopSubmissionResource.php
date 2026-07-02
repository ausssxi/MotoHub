<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ShopSubmissionResource\Pages;
use App\Models\ShopAcceptanceReport;
use App\Models\ShopSubmission;
use App\Services\Shop\ShopSubmissionService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class ShopSubmissionResource extends Resource
{
    protected static ?string $model = ShopSubmission::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationLabel = '店舗登録申請';

    protected static ?int $navigationSort = 3;

    public static function getNavigationBadge(): ?string
    {
        $pending = static::getModel()::where('status', ShopSubmission::STATUS_PENDING)->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('投稿内容（承認前に修正可）')->schema([
                Forms\Components\TextInput::make('shop_name')->label('店名')->required()->maxLength(100),
                Forms\Components\Grid::make(2)->schema([
                    Forms\Components\TextInput::make('prefecture')->label('都道府県')->required()->maxLength(10),
                    Forms\Components\TextInput::make('city')->label('市区町村')->required()->maxLength(50),
                ]),
                Forms\Components\TextInput::make('address')->label('住所')->maxLength(200)
                    ->helperText('※ 住所があれば承認時にGSIでジオコーディング。空なら緯度経度なしで登録。'),
                Forms\Components\Grid::make(2)->schema([
                    Forms\Components\TextInput::make('phone')->label('電話')->maxLength(20),
                    Forms\Components\TextInput::make('website_url')->label('サイトURL')->maxLength(200),
                ]),
                Forms\Components\CheckboxList::make('service_tags')->label('対応サービス')
                    ->options(array_combine(ShopSubmission::SERVICE_TAG_OPTIONS, ShopSubmission::SERVICE_TAG_OPTIONS))
                    ->columns(3),
                Forms\Components\CheckboxList::make('acceptance_flags')->label('受け入れ情報')
                    ->options(ShopAcceptanceReport::FLAGS)
                    ->columns(2),
                Forms\Components\Textarea::make('comment')->label('コメント')->rows(3)->maxLength(1000),
            ])->columns(1),

            Forms\Components\Section::make('投稿者・ステータス')->schema([
                Forms\Components\Placeholder::make('submitter')->label('投稿者')
                    ->content(fn (?ShopSubmission $record) => $record
                        ? ($record->submitter_name ?: '名無しライダー').($record->user_id ? '（ログインユーザー）' : '（匿名）')
                        : '—'),
                Forms\Components\Placeholder::make('status_display')->label('ステータス')
                    ->content(fn (?ShopSubmission $record) => $record?->status ?? '—'),
            ])->columns(2),

            Forms\Components\Section::make('重複候補（同一都道府県＋市区町村）')
                ->description('正規化店名の一致/包含、または電話番号一致で抽出。統合の判断に使う。')
                ->schema([
                    Forms\Components\Placeholder::make('duplicate_candidates')->hiddenLabel()
                        ->content(function (?ShopSubmission $record): HtmlString {
                            if (! $record) {
                                return new HtmlString('—');
                            }
                            $cands = app(ShopSubmissionService::class)->duplicateCandidates($record);
                            if ($cands->isEmpty()) {
                                return new HtmlString('<span style="color:#059669;font-weight:bold;">重複候補なし（新規登録推奨）</span>');
                            }
                            $items = $cands->map(function ($c) {
                                $url = route('shops.show', $c->id);
                                $type = $c->shop_type ?? '-';
                                $src = $c->source ?? '-';

                                return '<li style="margin-bottom:6px;">#'.$c->id.' <a href="'.$url.'" target="_blank" style="color:#2563eb;text-decoration:underline;">'
                                    .e($c->name).'</a> <span style="color:#6b7280;">['.e($type).' / '.e($src).']</span><br><span style="color:#6b7280;font-size:12px;">'.e((string) $c->address).'</span></li>';
                            })->implode('');

                            return new HtmlString('<ul style="list-style:disc;padding-left:18px;">'.$items.'</ul>');
                        }),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('ID')->sortable()->width(50),
                Tables\Columns\TextColumn::make('shop_name')->label('店名')->searchable()->weight('bold')->wrap(),
                Tables\Columns\TextColumn::make('area')->label('所在地')
                    ->state(fn (ShopSubmission $record): string => $record->prefecture.$record->city),
                Tables\Columns\TextColumn::make('submitter_name')->label('投稿者')
                    ->formatStateUsing(fn (?string $state, ShopSubmission $record): string => ($state ?: '名無しライダー').($record->user_id ? '（ログイン）' : ''))
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')->label('状態')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        ShopSubmission::STATUS_PENDING => 'warning',
                        ShopSubmission::STATUS_APPROVED, ShopSubmission::STATUS_MERGED => 'success',
                        ShopSubmission::STATUS_REJECTED => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')->label('投稿日')->dateTime('Y/m/d H:i')->sortable()->color('gray'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')->label('ステータス')
                    ->options([
                        ShopSubmission::STATUS_PENDING => '承認待ち',
                        ShopSubmission::STATUS_APPROVED => '承認(新規)',
                        ShopSubmission::STATUS_MERGED => '統合',
                        ShopSubmission::STATUS_REJECTED => '却下',
                    ])
                    ->default(ShopSubmission::STATUS_PENDING),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('確認・処理'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListShopSubmissions::route('/'),
            'edit' => Pages\EditShopSubmission::route('/{record}/edit'),
        ];
    }
}
