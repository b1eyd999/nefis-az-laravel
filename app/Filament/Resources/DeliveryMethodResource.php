<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AdminOnly;
use App\Filament\Resources\DeliveryMethodResource\Pages;
use App\Models\DeliveryMethod;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * The ways an order is delivered and what each costs. The three kinds (door,
 * post, metro) are fixed, since each asks the customer for different
 * details; the owner names, prices, describes and switches them.
 */
class DeliveryMethodResource extends Resource
{
    use AdminOnly;

    protected static ?string $model = DeliveryMethod::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationLabel = 'Çatdırılma';

    protected static ?string $modelLabel = 'çatdırılma üsulu';

    protected static ?string $pluralModelLabel = 'çatdırılma üsulları';

    protected static ?int $navigationSort = 11;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\Placeholder::make('kind')
                            ->label('Növ')
                            ->content(fn (?DeliveryMethod $record) => match ($record?->type) {
                                DeliveryMethod::DOOR => 'Qapıya — müştəridən ünvan və telefon soruşulur',
                                DeliveryMethod::POST => 'Poçt — ad və soyad, telefon və poçt şöbəsinin indeksi soruşulur',
                                DeliveryMethod::METRO => 'Metro — stansiya və telefon soruşulur',
                                default => '—',
                            })
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('name')->label('Ad (müştəri belə görür)')->required()->maxLength(120),
                        Forms\Components\TextInput::make('price')
                            ->label('Qiymət')
                            ->helperText('0 — pulsuz göstərilir.')
                            ->numeric()->minValue(0)->step(0.01)->suffix('₼')->required(),
                        Forms\Components\TextInput::make('description')
                            ->label('Qısa izah')
                            ->placeholder('Məs. Yalnız Bakı daxilində, 1–2 gün')
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Forms\Components\Toggle::make('is_active')->label('Müştəriyə göstər'),
                        Forms\Components\Textarea::make('options.stations')
                            ->label('Metro stansiyaları (hər sətirdə bir)')
                            ->helperText('Müştəri yalnız bu siyahıdan seçə bilər.')
                            ->rows(12)
                            ->visible(fn (Get $get, ?DeliveryMethod $record) => $record?->type === DeliveryMethod::METRO)
                            ->formatStateUsing(fn ($state) => is_array($state) ? implode("\n", $state) : $state)
                            ->dehydrateStateUsing(fn ($state) => array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $state)))))
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Üsul')
                    ->weight('bold')
                    ->description(fn (DeliveryMethod $r) => $r->description),
                Tables\Columns\TextColumn::make('type')
                    ->label('Növ')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => DeliveryMethod::TYPES[$state] ?? $state),
                // Priced right here, without opening each one.
                Tables\Columns\TextInputColumn::make('price')
                    ->label('Qiymət (₼)')
                    ->type('number')
                    ->rules(['required', 'numeric', 'min:0'])
                    ->extraInputAttributes(['step' => '0.01', 'min' => '0', 'style' => 'max-width:7rem']),
                Tables\Columns\ToggleColumn::make('is_active')->label('Göstər'),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->paginated(false)
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDeliveryMethods::route('/'),
            'edit' => Pages\EditDeliveryMethod::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }
}
