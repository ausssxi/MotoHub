<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BikeParkingResource\Pages;
use App\Models\BikeParking;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;

class BikeParkingResource extends Resource
{
    protected static ?string $model = BikeParking::class;

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';

    // レンタルガレージ（RentalGarage）と同じ台帳マスタなので同じグループに置く。
    protected static ?string $navigationGroup = 'コンテンツ管理';

    protected static ?string $navigationLabel = 'バイク駐車場';

    protected static ?string $modelLabel = 'バイク駐車場';

    protected static ?string $pluralModelLabel = 'バイク駐車場';

    protected static ?int $navigationSort = 22;

    // parking_type の選択肢（BikeParking::getParkingTypeLabel() と対応）。
    private const PARKING_TYPE_OPTIONS = [
        'bike_only' => 'バイク専用',
        'car_shared' => '四輪と共用',
        'bicycle_shared' => '自転車と共用',
        'other' => 'その他',
    ];

    // 編集専用リソース：新規作成を無効化する（Create ページも持たない）。
    // データは JMPSA / bikepark からインポートしたもので、手動作成しない（個別の誤りだけ直す）。
    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('取得元・集計（編集不可）')
                    ->description('インポートの一意キーと集計値。書き換えると次回取り込みで重複したり整合性が崩れるため変更不可。')
                    ->schema([
                        // source_url はインポートの同定キー。書き換えると次回取り込みで重複する。
                        // 表示のみ（disabled + dehydrated(false) で保存対象から外す）。
                        Forms\Components\TextInput::make('source_url')
                            ->label('取得元URL')
                            ->disabled()
                            ->dehydrated(false)
                            ->columnSpanFull(),

                        // jmpsa_updated_at / avg_rating / reviews_count / used_count は
                        // 取得元の情報と集計値であり、手で書き換えるものではない。
                        Forms\Components\TextInput::make('jmpsa_updated_at')
                            ->label('JMPSA更新日')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\TextInput::make('avg_rating')
                            ->label('平均評価')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\TextInput::make('reviews_count')
                            ->label('レビュー件数')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\TextInput::make('used_count')
                            ->label('利用登録数')
                            ->disabled()
                            ->dehydrated(false),
                    ])->columns(2),

                Forms\Components\Section::make('基本情報')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('名称')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('address')
                            ->label('住所')
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('tel')
                            ->label('電話番号')
                            ->tel()
                            ->maxLength(255),

                        Forms\Components\Select::make('parking_type')
                            ->label('駐車場タイプ')
                            ->options(self::PARKING_TYPE_OPTIONS),

                        Forms\Components\TextInput::make('parking_form')
                            ->label('駐車形態')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('capacity')
                            ->label('収容台数')
                            ->numeric(),

                        Forms\Components\TextInput::make('closed_days')
                            ->label('定休日')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('available_hours')
                            ->label('利用可能時間')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('management_company')
                            ->label('運営会社')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('vehicle_restriction')
                            ->label('車両制限')
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('notes')
                            ->label('備考')
                            ->rows(3)
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('description')
                            ->label('説明')
                            ->rows(4)
                            ->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make('料金')
                    ->schema([
                        Forms\Components\TextInput::make('price_per_hour')
                            ->label('時間料金')
                            ->numeric()
                            ->suffix('円'),

                        Forms\Components\TextInput::make('price_per_day')
                            ->label('日料金')
                            ->numeric()
                            ->suffix('円'),

                        Forms\Components\TextInput::make('price_per_month')
                            ->label('月極料金')
                            ->numeric()
                            ->suffix('円'),

                        Forms\Components\TextInput::make('price_detail')
                            ->label('料金詳細')
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ])->columns(3),

                Forms\Components\Section::make('設備・区分')
                    ->schema([
                        Forms\Components\Toggle::make('is_free')->label('無料'),
                        Forms\Components\Toggle::make('is_covered')->label('屋根あり'),
                        Forms\Components\Toggle::make('is_locked')->label('施錠あり'),
                        Forms\Components\Toggle::make('has_security_camera')->label('防犯カメラあり'),
                        Forms\Components\Toggle::make('available_24h')->label('24時間利用可'),
                    ])->columns(2),

                Forms\Components\Section::make('公開・検証')
                    ->schema([
                        Forms\Components\Toggle::make('is_active')
                            ->label('公開'),
                        Forms\Components\Toggle::make('is_verified')
                            ->label('検証済み'),
                    ])->columns(2),

                Forms\Components\Section::make('座標')
                    ->schema([
                        Forms\Components\TextInput::make('latitude')
                            ->label('緯度')
                            ->numeric(),

                        Forms\Components\TextInput::make('longitude')
                            ->label('経度')
                            ->numeric(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('名称')
                    ->weight(FontWeight::Bold)
                    ->wrap()
                    // 単一検索ボックスで name / address を横断検索する。
                    ->searchable(['name', 'address']),

                Tables\Columns\TextColumn::make('prefecture')
                    ->label('都道府県')
                    ->sortable(),

                // city は数千種あるためソート対象にしない（既定ソートも重くしない）。
                Tables\Columns\TextColumn::make('city')
                    ->label('市区町村'),

                Tables\Columns\TextColumn::make('parking_type')
                    ->label('タイプ')
                    ->formatStateUsing(fn (?string $state): string => self::PARKING_TYPE_OPTIONS[$state] ?? ($state ?? '')),

                Tables\Columns\TextColumn::make('capacity')
                    ->label('収容台数'),

                Tables\Columns\IconColumn::make('is_free')
                    ->label('無料')
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('公開')
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_verified')
                    ->label('検証済み')
                    ->boolean(),
            ])
            // 44,726件あるため、初期表示は主キー昇順の軽いソートにする（重いソートを既定にしない）。
            ->defaultSort('id', 'asc')
            ->filters([
                // prefecture は47件程度なので distinct で選択肢を引いてよい。
                Tables\Filters\SelectFilter::make('prefecture')
                    ->label('都道府県')
                    ->options(fn (): array => BikeParking::query()
                        ->whereNotNull('prefecture')
                        ->where('prefecture', '!=', '')
                        ->distinct()
                        ->orderBy('prefecture')
                        ->pluck('prefecture', 'prefecture')
                        ->toArray()),

                // city は数千件になるため distinct で引かない（フィルタ自体を作らない）。

                Tables\Filters\SelectFilter::make('parking_type')
                    ->label('タイプ')
                    ->options(self::PARKING_TYPE_OPTIONS),

                Tables\Filters\TernaryFilter::make('is_free')
                    ->label('無料'),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('公開'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
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
        // 編集専用：create ページは持たない。
        return [
            'index' => Pages\ListBikeParkings::route('/'),
            'edit' => Pages\EditBikeParking::route('/{record}/edit'),
        ];
    }
}
