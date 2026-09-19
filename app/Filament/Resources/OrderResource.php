<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Filament\Resources\OrderResource\RelationManagers;
use App\Models\DeliveryMethod;
use App\Models\Order;
use App\Support\Price;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationLabel = 'Sifarişlər';

    protected static ?string $modelLabel = 'sifariş';

    protected static ?string $pluralModelLabel = 'sifarişlər';

    public const STATUSES = [
        'pending' => 'Gözləmədə',
        'confirmed' => 'Təsdiqləndi',
        'completed' => 'Tamamlandı',
        'cancelled' => 'Ləğv edildi',
    ];

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Sifariş məlumatı')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options(self::STATUSES)
                            ->required(),
                        Forms\Components\TextInput::make('contact_phone')
                            ->label('Telefon')
                            ->tel(),
                        Forms\Components\Textarea::make('note')
                            ->label('Müştərinin qeydi')
                            ->columnSpanFull(),
                    ])->columns(2),
                // How it goes out, as the customer chose it at checkout.
                Forms\Components\Section::make('Çatdırılma')
                    ->schema([
                        Forms\Components\Placeholder::make('delivery_method')
                            ->label('Üsul')
                            ->content(fn (?Order $record) => $record?->delivery_name
                                ? $record->delivery_name . ' — ' . ($record->delivery_price > 0 ? Price::format($record->delivery_price) : 'pulsuz')
                                : 'Seçilməyib (köhnə sifariş)'),
                        Forms\Components\TextInput::make('recipient_name')
                            ->label('Ad və soyad')
                            ->visible(fn (?Order $record) => $record?->delivery_type === DeliveryMethod::POST),
                        Forms\Components\TextInput::make('postal_index')
                            ->label('Poçt şöbəsinin indeksi')
                            ->visible(fn (?Order $record) => $record?->delivery_type === DeliveryMethod::POST),
                        Forms\Components\TextInput::make('metro_station')
                            ->label('Metro stansiyası')
                            ->visible(fn (?Order $record) => $record?->delivery_type === DeliveryMethod::METRO),
                        Forms\Components\TextInput::make('delivery_address')
                            ->label('Ünvan')
                            ->visible(fn (?Order $record) => ! in_array($record?->delivery_type, [DeliveryMethod::POST, DeliveryMethod::METRO], true)),
                        Forms\Components\Placeholder::make('totals')
                            ->label('Məbləğ')
                            ->content(fn (?Order $record) => $record
                                ? 'Məhsullar ' . Price::format($record->itemsTotal())
                                    . ' + çatdırılma ' . Price::format($record->delivery_price ?? 0)
                                    . ' = ' . Price::format($record->total())
                                : '—')
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('№')
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Müştəri')
                    ->searchable(),
                Tables\Columns\TextColumn::make('contact_phone')
                    ->label('Telefon'),
                Tables\Columns\TextColumn::make('items_count')
                    ->label('Məhsul sayı')
                    ->counts('items'),
                Tables\Columns\TextColumn::make('delivery_name')
                    ->label('Çatdırılma')
                    ->placeholder('—')
                    ->description(fn (Order $r) => $r->delivery_type ? $r->deliverySummary() : null)
                    ->wrap(),
                Tables\Columns\TextColumn::make('total')
                    ->label('Məbləğ')
                    ->getStateUsing(fn (Order $r) => $r->total() > 0 ? Price::format($r->total()) : '—'),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'warning' => 'pending',
                        'info' => 'confirmed',
                        'success' => 'completed',
                        'danger' => 'cancelled',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'Gözləmədə',
                        'confirmed' => 'Təsdiqləndi',
                        'completed' => 'Tamamlandı',
                        'cancelled' => 'Ləğv edildi',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Tarix')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->modifyQueryUsing(fn ($query) => $query->with('items'))
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'pending' => 'Gözləmədə',
                        'confirmed' => 'Təsdiqləndi',
                        'completed' => 'Tamamlandı',
                        'cancelled' => 'Ləğv edildi',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', 'pending')->count() ?: null;
    }
}
