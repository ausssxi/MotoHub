<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RentalGarageResource\Pages;
use App\Models\RentalGarage;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;

class RentalGarageResource extends Resource
{
    protected static ?string $model = RentalGarage::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    // 道の駅（RoadsideStation）と同じ台帳マスタなので同じグループに置く。
    protected static ?string $navigationGroup = 'コンテンツ管理';

    protected static ?string $navigationLabel = 'レンタルガレージ';

    protected static ?string $modelLabel = 'レンタルガレージ';

    protected static ?string $pluralModelLabel = 'レンタルガレージ';

    protected static ?int $navigationSort = 21;

    // garage_type の選択肢（RentalGarage::GARAGE_TYPE_LABELS と対応）。
    private const GARAGE_TYPE_OPTIONS = [
        'indoor' => '屋内ガレージ',
        'container' => '屋外コンテナ',
        'open' => '青空月極',
        'other' => 'その他',
    ];

    // 編集専用リソース：新規作成を無効化する（Create ページも持たない）。
    // データはスクレイパー（rental_garage:fetch）が投入するもので、手動作成しない。
    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('取得元（編集不可）')
                    ->description('スクレイパーの一意キー。書き換えると次回取得で重複するため変更不可。')
                    ->schema([
                        // operator / source / source_url はスクレイパーの同定キー。
                        // 表示のみ（disabled + dehydrated(false) で保存対象から外す）。
                        Forms\Components\TextInput::make('operator')
                            ->label('事業者')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\TextInput::make('source')
                            ->label('取得区分')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\TextInput::make('source_url')
                            ->label('取得元URL')
                            ->disabled()
                            ->dehydrated(false)
                            ->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make('基本情報')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('名称')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\Select::make('garage_type')
                            ->label('タイプ')
                            ->options(self::GARAGE_TYPE_OPTIONS)
                            ->required()
                            ->helperText('スクレイパーでは判定できないため手で補正する（屋内/屋外など）'),

                        Forms\Components\TextInput::make('size_text')
                            ->label('広さ表記')
                            ->maxLength(255)
                            ->helperText('例: 2.0畳～8.0畳'),

                        Forms\Components\TextInput::make('monthly_fee_min')
                            ->label('月額料金(最小)')
                            ->numeric()
                            ->suffix('円'),

                        Forms\Components\TextInput::make('monthly_fee_max')
                            ->label('月額料金(最大)')
                            ->numeric()
                            ->suffix('円'),

                        Forms\Components\TextInput::make('capacity')
                            ->label('収容台数')
                            ->numeric(),

                        Forms\Components\TextInput::make('phone')
                            ->label('電話番号')
                            ->tel()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('website_url')
                            ->label('公式サイトURL')
                            ->url()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('description')
                            ->label('説明')
                            ->rows(4)
                            ->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make('設備（スクレイパー判定不可・手で補正）')
                    ->schema([
                        Forms\Components\Toggle::make('is_24h')->label('24時間利用可'),
                        Forms\Components\Toggle::make('has_power')->label('電源あり'),
                        Forms\Components\Toggle::make('has_security')->label('防犯設備あり'),
                        Forms\Components\Toggle::make('has_shutter')->label('シャッターあり'),
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

                Tables\Columns\TextColumn::make('operator')
                    ->label('事業者')
                    ->sortable(),

                Tables\Columns\TextColumn::make('prefecture')
                    ->label('都道府県')
                    ->sortable(),

                Tables\Columns\TextColumn::make('city')
                    ->label('市区町村'),

                Tables\Columns\TextColumn::make('garage_type')
                    ->label('タイプ')
                    ->formatStateUsing(fn (?string $state): string => self::GARAGE_TYPE_OPTIONS[$state] ?? ($state ?? '')),

                Tables\Columns\TextColumn::make('monthly_fee_min')
                    ->label('月額(最小)')
                    ->money('JPY')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('公開')
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_verified')
                    ->label('検証済み')
                    ->boolean(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('operator')
                    ->label('事業者')
                    ->options(fn (): array => RentalGarage::query()
                        ->whereNotNull('operator')
                        ->where('operator', '!=', '')
                        ->distinct()
                        ->orderBy('operator')
                        ->pluck('operator', 'operator')
                        ->toArray()),

                Tables\Filters\SelectFilter::make('prefecture')
                    ->label('都道府県')
                    ->options(fn (): array => RentalGarage::query()
                        ->whereNotNull('prefecture')
                        ->where('prefecture', '!=', '')
                        ->distinct()
                        ->orderBy('prefecture')
                        ->pluck('prefecture', 'prefecture')
                        ->toArray()),

                Tables\Filters\SelectFilter::make('garage_type')
                    ->label('タイプ')
                    ->options(self::GARAGE_TYPE_OPTIONS),
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
            'index' => Pages\ListRentalGarages::route('/'),
            'edit' => Pages\EditRentalGarage::route('/{record}/edit'),
        ];
    }
}
