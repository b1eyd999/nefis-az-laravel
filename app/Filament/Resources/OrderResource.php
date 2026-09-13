<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Filament\Resources\OrderResource\RelationManagers;
use App\Models\Order;
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

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Sifariş məlumatı')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'pending' => 'Gözləmədə',
                                'confirmed' => 'Təsdiqləndi',
                                'completed' => 'Tamamlandı',
                                'cancelled' => 'Ləğv edildi',
                            ])
                            ->required(),
                        Forms\Components\TextInput::make('contact_phone')
                            ->label('Əlaqə nömrəsi')
                            ->tel(),
                        Forms\Components\TextInput::make('delivery_address')
                            ->label('Çatdırılma ünvanı'),
                        Forms\Components\Textarea::make('note')
                            ->label('Qeyd')
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
