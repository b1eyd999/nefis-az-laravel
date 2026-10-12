<?php

namespace App\Filament\Widgets;

use App\Models\Material;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

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
        // Anbar is the owner's everywhere else in the panel; this must agree.
        return (bool) auth()->user()?->isAdmin();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Material::query()->where('is_active', true))
            /* Counted first, lowest at the top; a roll nobody has counted yet
               is not news and must not take the whole first page. Written as
               the default sort rather than baked into the query, so clicking
               the «Qalıq» header replaces it instead of landing behind it. */
            ->defaultSort(fn (Builder $query, string $direction) => $query
                ->knownFirst()
                ->orderBy('stock', $direction))
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Material')->weight('bold')->wrap(),
                Tables\Columns\TextColumn::make('stock')
                    ->label('Qalıq')
                    ->formatStateUsing(fn ($state, Material $m) => match (true) {
                        $m->isNew() => 'sayılmayıb',
                        (float) $state <= 0 => 'bitib',
                        default => rtrim(rtrim(number_format((float) $state, 2, '.', ' '), '0'), '.') . ' ' . $m->unit,
                    })
                    ->badge()
                    ->color(fn ($state, Material $m) => match (true) {
                        $m->isNew() => 'gray',
                        (float) $state <= 0 => 'danger',
                        $m->low_stock > 0 && (float) $state <= $m->low_stock => 'warning',
                        default => 'success',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('boxes_left')
                    // Not always a box: the wrapping paper and the ribbons are
                    // spent on wraps, and most boxes are not wrapped.
                    ->label('Nəyə bəs edir')
                    ->state(fn (Material $m) => match (true) {
                        $m->per_box <= 0 => '—',
                        $m->isNew() => 'hələ sayılmayıb',
                        $m->stock <= 0 => 'almaq lazımdır',
                        default => floor($m->stock / $m->per_box) . ' ' . $m->covers(),
                    })
                    // On a phone the two columns that matter are the name and what is left.
                    ->visibleFrom('md')
                    ->color(fn (Material $m) => match (true) {
                        $m->isNew() => 'gray',
                        $m->stock <= 0 => 'danger',
                        $m->per_box > 0 && $m->stock / $m->per_box < 10 => 'warning',
                        default => 'gray',
                    }),
            ])
            ->emptyStateHeading('Anbar boşdur')
            ->emptyStateDescription('Materialları "Anbar" bölməsində əlavə edin.');
    }
}
