<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RoadsideStationResource\Pages;
use App\Models\RoadsideStation;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;

class RoadsideStationResource extends Resource
{
    protected static ?string $model = RoadsideStation::class;

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';

    // 既存リソースは全てユーザー投稿のモデレーション系（UGC管理）なので、
    // 台帳マスタである道の駅はそれとは別グループに置く。
    protected static ?string $navigationGroup = 'コンテンツ管理';

    protected static ?string $navigationLabel = '道の駅';

    protected static ?string $modelLabel = '道の駅';

    protected static ?string $pluralModelLabel = '道の駅';

    protected static ?int $navigationSort = 20;

    // 編集専用リソース：新規作成を無効化する（Create ページも持たない）。
    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('基本情報')
                    ->schema([
                        // station_code は公開URL /michinoeki/{station_code} とサイトマップのキー。
                        // 変更させないため読み取り専用にする。
                        Forms\Components\TextInput::make('station_code')
                            ->label('駅コード')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\TextInput::make('name')
                            ->label('名称')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('nickname')
                            ->label('別名（nickname）')
                            ->maxLength(255)
                            ->helperText('パイプ区切りの別名リスト（例: あさひかわ|旭川地場産業振興センター）。詳細ページでは別名ごとにバッジで表示されます'),

                        Forms\Components\TextInput::make('designated_year')
                            ->label('登録年')
                            ->numeric(),

                        Forms\Components\Textarea::make('summary')
                            ->label('概要')
                            ->rows(4)
                            ->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make('所在地・座標')
                    ->schema([
                        Forms\Components\TextInput::make('prefecture')
                            ->label('都道府県')
                            ->maxLength(10),

                        Forms\Components\TextInput::make('city')
                            ->label('市区町村')
                            ->maxLength(50)
                            ->helperText('郡がある場合は郡を含める（例: 茅部郡森町）'),

                        Forms\Components\TextInput::make('address')
                            ->label('住所')
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('route')
                            ->label('接続道路')
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('latitude')
                            ->label('緯度')
                            ->numeric()
                            ->helperText('地図から設定する場合は専用の座標編集画面を使ってください'),

                        Forms\Components\TextInput::make('longitude')
                            ->label('経度')
                            ->numeric()
                            ->helperText('地図から設定する場合は専用の座標編集画面を使ってください'),
                    ])->columns(2),

                Forms\Components\Section::make('画像')
                    ->schema([
                        Forms\Components\TextInput::make('image_url')
                            ->label('画像URL')
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('image_author')
                            ->label('画像の著者')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('image_license')
                            ->label('ライセンス')
                            ->maxLength(100),

                        Forms\Components\TextInput::make('image_license_url')
                            ->label('ライセンスURL')
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make('外部リンク')
                    ->schema([
                        Forms\Components\TextInput::make('website_url')
                            ->label('公式サイトURL')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('wikipedia_url')
                            ->label('Wikipedia URL')
                            ->maxLength(255),
                    ])->columns(2),

                Forms\Components\Section::make('設備')
                    ->schema([
                        Forms\Components\Toggle::make('has_atm')->label('ATM'),
                        Forms\Components\Toggle::make('has_restaurant')->label('レストラン'),
                        Forms\Components\Toggle::make('has_onsen')->label('温泉'),
                        Forms\Components\Toggle::make('has_ev_charging')->label('EV充電'),
                        Forms\Components\Toggle::make('has_wifi')->label('Wi-Fi'),
                        Forms\Components\Toggle::make('has_shower')->label('シャワー'),
                        Forms\Components\Toggle::make('has_camp')->label('キャンプ'),
                        Forms\Components\Toggle::make('has_gas_station')->label('ガソリンスタンド'),
                        Forms\Components\Toggle::make('has_observatory')->label('展望台'),
                        Forms\Components\Toggle::make('has_shop')->label('売店'),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('station_code')
                    ->label('駅コード')
                    ->sortable()
                    // 単一検索ボックスで station_code / name / nickname / city / address を横断検索する。
                    ->searchable(['station_code', 'name', 'nickname', 'city', 'address']),

                Tables\Columns\TextColumn::make('name')
                    ->label('名称')
                    ->weight(FontWeight::Bold)
                    ->wrap(),

                Tables\Columns\TextColumn::make('nickname')
                    ->label('別名')
                    ->limit(30)
                    ->tooltip(fn (RoadsideStation $record): ?string => $record->nickname)
                    ->color('gray'),

                Tables\Columns\TextColumn::make('prefecture')
                    ->label('都道府県')
                    ->sortable(),

                Tables\Columns\TextColumn::make('city')
                    ->label('市区町村'),

                Tables\Columns\TextColumn::make('route')
                    ->label('接続道路')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('designated_year')
                    ->label('登録年')
                    ->sortable(),
            ])
            ->defaultSort('station_code', 'asc')
            ->filters([
                Tables\Filters\SelectFilter::make('prefecture')
                    ->label('都道府県')
                    ->options(fn (): array => RoadsideStation::query()
                        ->whereNotNull('prefecture')
                        ->where('prefecture', '!=', '')
                        ->distinct()
                        ->orderBy('prefecture')
                        ->pluck('prefecture', 'prefecture')
                        ->toArray()),

                Tables\Filters\Filter::make('no_address')
                    ->label('住所なし')
                    ->query(fn ($query) => $query->whereNull('address')),

                Tables\Filters\Filter::make('no_coordinates')
                    ->label('座標なし')
                    ->query(fn ($query) => $query->where(function ($q) {
                        $q->whereNull('latitude')->orWhereNull('longitude');
                    })),

                Tables\Filters\Filter::make('no_image')
                    ->label('画像なし')
                    ->query(fn ($query) => $query->where(function ($q) {
                        $q->whereNull('image_url')->orWhere('image_url', '');
                    })),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('open_public')
                    ->label('公開ページを開く')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (RoadsideStation $record): string => route('michinoeki.show', ['station_code' => $record->station_code]))
                    ->openUrlInNewTab(),
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
            'index' => Pages\ListRoadsideStations::route('/'),
            'edit' => Pages\EditRoadsideStation::route('/{record}/edit'),
        ];
    }
}
