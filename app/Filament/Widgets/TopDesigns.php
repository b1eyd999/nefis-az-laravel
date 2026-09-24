<?php

namespace App\Filament\Widgets;

use App\Models\OrderItem;
use App\Support\Price;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * What actually sells: the designs ordered most over the last three months,
 * with how many boxes went out and what they brought in.
 */
class TopDesigns extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Ən çox sifariş olunan dizaynlar (son 3 ay)';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->isStaff();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                OrderItem::query()
                    ->selectRaw('MIN(order_items.id) as id, order_items.product_name,
                        SUM(order_items.quantity) as boxes,
                        SUM(order_items.quantity * (COALESCE(order_items.price, 0) + COALESCE(order_items.chocolate_price, 0)
                            + COALESCE(order_items.wrapping_price, 0) + COALESCE(order_items.letter_price, 0)
                            + COALESCE(order_items.ar_price, 0))) as money')
                    ->whereHas('order', fn (Builder $q) => $q->where('status', '!=', 'cancelled')
                        ->where('created_at', '>=', Carbon::now()->subMonths(3)))
                    ->groupBy('order_items.product_name')
            )
            ->defaultSort('boxes', 'desc')
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->columns([
                Tables\Columns\TextColumn::make('product_name')->label('Dizayn')->weight('bold')->wrap(),
                Tables\Columns\TextColumn::make('boxes')->label('Ədəd')->sortable(),
                Tables\Columns\TextColumn::make('money')
                    ->label('Məbləğ')
                    ->formatStateUsing(fn ($state) => Price::format((float) $state))
                    ->sortable()
                    ->visible(fn () => (bool) auth()->user()?->isAdmin()),
            ])
            ->emptyStateHeading('Hələ sifariş yoxdur')
            ->emptyStateDescription('İlk sifarişdən sonra burada ən çox satılan dizaynlar görünəcək.');
    }
}
