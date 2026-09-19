<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AdminOnly;
use App\Filament\Resources\ChocolateResource\Pages\ListChocolates;
use App\Filament\Resources\MarketResource\Pages;
use App\Models\Market;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

/**
 * The shops the chocolate bars come from — each one a category of bars.
 */
class MarketResource extends Resource
{
    use AdminOnly;

    protected static ?string $model = Market::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationGroup = 'Şokolad';

    protected static ?string $navigationLabel = 'Marketlər';

    protected static ?string $modelLabel = 'market';

    protected static ?string $pluralModelLabel = 'marketlər';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Market')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Ad')
                            ->required()
                            ->maxLength(120)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Set $set, ?string $state, ?Market $record) => $record ? null : $set('slug', Str::slug((string) $state))),
                        Forms\Components\TextInput::make('slug')
                            ->label('Qısa ad (URL)')
                            ->required()
                            ->unique(ignoreRecord: true),
                        Forms\Components\TextInput::make('website')
                            ->label('Sayt')
                            ->url()
                            ->placeholder('https://…'),
                        Forms\Components\TextInput::make('sort_order')
                            ->label('Sıra nömrəsi')
                            ->numeric()
                            ->default(0),
                        Forms\Components\Placeholder::make('auto')
                            ->label('Qiymətlər')
                            ->content(fn (?Market $record) => $record?->canSync()
                                ? 'Saytından avtomatik oxunur — şokoladlar və qiymətlər (endirimlər daxil) hər yeniləmədə gəlir.'
                                : 'Şokoladları əl ilə əlavə edirsiniz (Şokoladlar → Şokolad əlavə et).')
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Market')
                    ->weight('bold')
                    ->description(fn (Market $r) => $r->website),
                Tables\Columns\TextColumn::make('chocolates_count')
                    ->label('Şokolad')
                    ->counts('chocolates')
                    ->badge(),
                Tables\Columns\TextColumn::make('importer')
                    ->label('Qiymətlər')
                    ->formatStateUsing(fn ($state, Market $r) => $r->canSync() ? 'Avtomatik' : 'Əl ilə')
                    ->placeholder('Əl ilə')
                    ->badge()
                    ->color(fn (Market $r) => $r->canSync() ? 'success' : 'gray'),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->actions([
                Tables\Actions\Action::make('chocolates')
                    ->label('Şokoladları')
                    ->icon('heroicon-o-cake')
                    ->url(fn (Market $r) => ChocolateResource::getUrl('index', ['activeTab' => 'market-' . $r->id])),
                Tables\Actions\Action::make('sync')
                    ->label('Yenilə')
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn (Market $r) => $r->canSync())
                    ->requiresConfirmation()
                    ->action(fn (Market $r) => ListChocolates::runSync($r)),
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMarkets::route('/'),
            'create' => Pages\CreateMarket::route('/create'),
            'edit' => Pages\EditMarket::route('/{record}/edit'),
        ];
    }
}
