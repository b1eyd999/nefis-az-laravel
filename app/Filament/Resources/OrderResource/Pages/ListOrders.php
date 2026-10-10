<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\Order;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    /**
     * The day's work, across the top.
     *
     * The list was one long column newest-first, and the one question the
     * owner asks it every morning — what has to go out today, and what
     * should have gone out already — could only be answered by reading the
     * delivery date on every row. Each tab is that question.
     *
     * «Gecikən» is the one that matters: a box whose day has passed and which
     * is still not handed over is a customer about to write and ask why.
     */
    public function getTabs(): array
    {
        /* Orders still being worked on. A cancelled order's date is not late,
           and a finished one's date is history. */
        $live = fn (Builder $query) => $query
            ->whereNotIn('status', [...Order::OFF_THE_BOOKS, 'completed']);

        return [
            'today' => Tab::make('Bu gün')
                ->icon('heroicon-o-sun')
                ->modifyQueryUsing(fn (Builder $query) => $live($query)->whereDate('delivery_date', today()))
                ->badge(Order::query()->tap($live)->whereDate('delivery_date', today())->count())
                ->badgeColor('primary'),

            'late' => Tab::make('Gecikən')
                ->icon('heroicon-o-exclamation-triangle')
                ->modifyQueryUsing(fn (Builder $query) => $live($query)->whereDate('delivery_date', '<', today()))
                ->badge(Order::query()->tap($live)->whereDate('delivery_date', '<', today())->count())
                ->badgeColor('danger'),

            'soon' => Tab::make('Qabaqda')
                ->icon('heroicon-o-calendar-days')
                ->modifyQueryUsing(fn (Builder $query) => $live($query)->whereDate('delivery_date', '>', today()))
                ->badge(Order::query()->tap($live)->whereDate('delivery_date', '>', today())->count()),

            'unpaid' => Tab::make('Ödəniş gözləyir')
                ->icon('heroicon-o-credit-card')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->whereIn('status', ['awaiting_payment', 'payment_check'])
                    ->whereNull('payment_confirmed_at'))
                ->badge(Order::whereIn('status', ['awaiting_payment', 'payment_check'])
                    ->whereNull('payment_confirmed_at')->count())
                ->badgeColor('warning'),

            'all' => Tab::make('Hamısı')
                ->badge(Order::count()),
        ];
    }

    /**
     * The page still opens on everything.
     *
     * Opening on «Bu gün» was tried and is wrong: an order with no delivery
     * day — one the owner wrote himself, or an old one from before the day
     * was asked for — belongs to no day tab at all, so the first thing he
     * would see is a list missing real orders. The numbers on the tabs
     * answer the morning question without hiding anything to do it.
     */
    public function getDefaultActiveTab(): string|int|null
    {
        return 'all';
    }
}
