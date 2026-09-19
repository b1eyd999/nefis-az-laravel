<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AdminOnly;
use App\Filament\Resources\ChocolateResource\Pages;
use App\Models\Chocolate;
use App\Models\Setting;
use App\Support\ChocolateBrand;
use App\Support\Price;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * The chocolate bars a customer can have put inside their box: imported from
 * Araz Market, or added by hand.
 */
class ChocolateResource extends Resource
{
    use AdminOnly;

    protected static ?string $model = Chocolate::class;

    protected static ?string $navigationIcon = 'heroicon-o-cake';

    protected static ?string $navigationGroup = 'Şokolad';

    protected static ?string $navigationLabel = 'Şokoladlar';

    protected static ?string $modelLabel = 'şokolad';

    protected static ?string $pluralModelLabel = 'şokoladlar';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Şokolad')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Ad (müştəri belə görür)')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('brand')
                            ->label('Marka')
                            ->maxLength(60)
                            ->datalist(fn () => Chocolate::withTrashed()->whereNotNull('brand')->distinct()->orderBy('brand')->pluck('brand')->all())
                            ->helperText('Müştəri şokoladları markaya görə seçir. Boş qalsa, addan özü tapılır; tapılmasa "' . ChocolateBrand::OTHER . '" qrupuna düşür.')
                            ->columnSpanFull(),
                        Forms\Components\Select::make('market_id')
                            ->label('Market')
                            ->relationship('market', 'name', fn ($query) => $query->orderBy('sort_order'))
                            ->placeholder('Marketsiz')
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')->label('Marketin adı')->required(),
                                Forms\Components\TextInput::make('slug')->label('Qısa ad (URL)')->required()->unique('markets', 'slug'),
                                Forms\Components\TextInput::make('website')->label('Sayt')->url(),
                            ])
                            ->columnSpanFull(),
                        Forms\Components\FileUpload::make('image')
                            ->label('Şəkil')
                            ->image()
                            ->disk('public')
                            ->directory('chocolates')
                            ->maxSize(5120)
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('weight_g')
                            ->label('Çəki (q)')
                            ->numeric()
                            ->suffix('q'),
                        Forms\Components\TextInput::make('sort_order')
                            ->label('Sıra nömrəsi')
                            ->numeric()
                            ->default(0),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Müştəriyə göstər')
                            ->default(true),
                    ])->columns(2),
                Forms\Components\Section::make('Qiymət')
                    ->description('Saytdakı qiymət = orijinal qiymət + əlavə faizi.')
                    ->schema([
                        Forms\Components\TextInput::make('base_price')
                            ->label('Orijinal qiymət')
                            ->numeric()->minValue(0)->step(0.01)->suffix('₼')
                            ->required()
                            ->live(onBlur: true),
                        Forms\Components\TextInput::make('sale_price')
                            ->label('Endirimli qiymət (Araz Market)')
                            ->helperText('Araz Market-dən özü gəlir; əl ilə boş qoya bilərsiniz.')
                            ->numeric()->minValue(0)->step(0.01)->suffix('₼'),
                        Forms\Components\TextInput::make('markup_percent')
                            ->label('Bu şokoladın öz əlavəsi (%)')
                            ->helperText(fn () => 'Boş — ümumi əlavə (' . Setting::get(Setting::CHOCOLATE_MARKUP) . '%) tətbiq olunur.')
                            ->numeric()->minValue(0)->maxValue(500)->suffix('%')
                            ->live(onBlur: true),
                        Forms\Components\Placeholder::make('customer_price')
                            ->label('Müştəri üçün qiymət')
                            ->content(function (Get $get) {
                                $chocolate = new Chocolate([
                                    'base_price' => (float) $get('base_price'),
                                    'sale_price' => $get('sale_price') !== null && $get('sale_price') !== '' ? (float) $get('sale_price') : null,
                                    'markup_percent' => $get('markup_percent') !== null && $get('markup_percent') !== '' ? (int) $get('markup_percent') : null,
                                ]);

                                return Price::format($chocolate->price()) . '  (' . Price::format($chocolate->costPrice()) . ' + ' . $chocolate->markup() . '%)';
                            }),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image')
                    ->label('')
                    ->disk('public')
                    ->height(56),
                Tables\Columns\TextColumn::make('name')
                    ->label('Ad')
                    ->searchable()
                    ->wrap()
                    ->description(fn (Chocolate $r) => ($r->source
                        ? ($r->in_source ? 'Saytdan avtomatik' : 'Marketin saytında artıq yoxdur')
                        : 'Əl ilə əlavə olunub') . ($r->seller ? ' · satıcı: ' . $r->seller : '')),
                Tables\Columns\TextColumn::make('brand')
                    ->label('Marka')
                    ->placeholder(ChocolateBrand::OTHER)
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('market.name')
                    ->label('Market')
                    ->badge()
                    ->placeholder('Marketsiz')
                    ->sortable(),
                Tables\Columns\TextColumn::make('weight_g')
                    ->label('Çəki')
                    ->formatStateUsing(fn (Chocolate $r) => $r->weightLabel() ?? '—')
                    ->sortable(),
                Tables\Columns\TextColumn::make('base_price')
                    ->label('Orijinal')
                    ->formatStateUsing(fn ($state) => Price::format($state))
                    ->sortable(),
                Tables\Columns\TextColumn::make('sale_price')
                    ->label('Endirim')
                    ->formatStateUsing(fn (Chocolate $r) => $r->sale_price ? Price::format($r->sale_price) . ($r->sale_percent ? '  −' . $r->sale_percent . '%' : '') : null)
                    ->placeholder('—')
                    ->badge()
                    ->color('success'),
                Tables\Columns\TextColumn::make('markup_percent')
                    ->label('Əlavə')
                    ->formatStateUsing(fn (Chocolate $r) => $r->markup() . '%' . ($r->markup_percent === null ? ' (ümumi)' : ''))
                    ->placeholder(fn () => Setting::get(Setting::CHOCOLATE_MARKUP) . '% (ümumi)'),
                Tables\Columns\TextColumn::make('price')
                    ->label('Saytda')
                    ->getStateUsing(fn (Chocolate $r) => Price::format($r->price()))
                    ->weight('bold'),
                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Göstər'),
                Tables\Columns\TextColumn::make('synced_at')
                    ->label('Yenilənib')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->filters([
                Tables\Filters\SelectFilter::make('market_id')->label('Market')->relationship('market', 'name'),
                Tables\Filters\SelectFilter::make('brand')->label('Marka')
                    ->options(fn () => Chocolate::whereNotNull('brand')->distinct()->orderBy('brand')->pluck('brand', 'brand')->all()),
                Tables\Filters\TernaryFilter::make('is_active')->label('Göstərilir'),
                Tables\Filters\Filter::make('on_sale')->label('Endirimdə')
                    ->query(fn ($q) => $q->whereNotNull('sale_price')),
                // Deleted bars stay known so imports leave them out; they can come back.
                Tables\Filters\TrashedFilter::make()
                    ->label('Silinənlər')
                    ->placeholder('Silinməyənlər')
                    ->trueLabel('Hamısı (silinənlər də)')
                    ->falseLabel('Yalnız silinənlər'),
            ])
            ->actions([
                Tables\Actions\Action::make('source')
                    ->label('Saytda')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Chocolate $r) => $r->source_url, shouldOpenInNewTab: true)
                    ->visible(fn (Chocolate $r) => filled($r->source_url)),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->modalDescription('Şokolad gizlənir və marketdən yeniləyəndə geri gəlmir. İstəsəniz "Silinənlər" filtrindən bərpa edə bilərsiniz.'),
                Tables\Actions\RestoreAction::make()->label('Bərpa et'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('markup')
                        ->label('Əlavə faizini təyin et')
                        ->icon('heroicon-o-receipt-percent')
                        ->form([
                            Forms\Components\TextInput::make('markup')
                                ->label('Əlavə (%)')
                                ->helperText('Boş — ümumi əlavə tətbiq olunsun.')
                                ->numeric()->minValue(0)->maxValue(500)->suffix('%'),
                        ])
                        ->action(fn (Collection $records, array $data) => $records->each->update([
                            'markup_percent' => $data['markup'] === null || $data['markup'] === '' ? null : (int) $data['markup'],
                        ]))
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('show')->label('Göstər')->icon('heroicon-o-eye')
                        ->action(fn (Collection $records) => $records->each->update(['is_active' => true]))
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('hide')->label('Gizlət')->icon('heroicon-o-eye-slash')
                        ->action(fn (Collection $records) => $records->each->update(['is_active' => false]))
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make()->label('Bərpa et'),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListChocolates::route('/'),
            'create' => Pages\CreateChocolate::route('/create'),
            'edit' => Pages\EditChocolate::route('/{record}/edit'),
        ];
    }
}
