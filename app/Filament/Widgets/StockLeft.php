<?php

namespace App\Filament\Widgets;

use App\Models\Material;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * What is left in the store room, the thinnest first, so an empty shelf is
 * seen before an order runs into it.
 */
class StockLeft extends TableWidget
{
    protected static ?int $sort = 3;

    // Drawn with the page: these are three small queries, and a lazy
    // widget can sit empty if its own request never fires.
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Anbarda qalıq';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->isStaff();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Material::query()->where('is_active', true))
            ->defaultSort('stock')
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Material')->weight('bold')->wrap(),
                Tables\Columns\TextColumn::make('stock')
                    ->label('Qalıq')
                    ->formatStateUsing(fn ($state, Material $m) => rtrim(rtrim(number_format((float) $state, 2, '.', ' '), '0'), '.') . ' ' . $m->unit)
                    ->badge()
                    ->color(fn ($state, Material $m) => match (true) {
                        (float) $state <= 0 => 'danger',
                        $m->low_stock > 0 && (float) $state <= $m->low_stock => 'warning',
                        default => 'success',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('boxes_left')
                    ->label('Neçə qutuya bəs edir')
                    ->state(fn (Material $m) => $m->per_box > 0 ? floor($m->stock / $m->per_box) . ' qutu' : '—')
                    ->color(fn (Material $m) => $m->per_box > 0 && $m->stock / $m->per_box < 10 ? 'warning' : 'gray'),
            ])
            ->emptyStateHeading('Anbar boşdur')
            ->emptyStateDescription('Materialları "Anbar" bölməsində əlavə edin.');
    }
}
