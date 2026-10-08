<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AdminOnly;
use App\Filament\Resources\CartHandoffResource\Pages;
use App\Models\CartHandoff;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

/**
 * The baskets the shop has filled for customers who could not fill them.
 *
 * Nothing is edited here: a basket is built on the site, in the same design
 * page a customer would use, and turned into a link from the basket page.
 * This is the list of those links — who each was for, whether it was opened,
 * and which of them became an order.
 */
class CartHandoffResource extends Resource
{
    use AdminOnly;

    protected static ?string $model = CartHandoff::class;

    protected static ?string $navigationIcon = 'heroicon-o-link';

    protected static ?string $navigationLabel = 'Hazır səbətlər';

    protected static ?string $navigationGroup = 'Mağaza';

    protected static ?string $modelLabel = 'hazır səbət';

    protected static ?string $pluralModelLabel = 'hazır səbətlər';

    protected static ?int $navigationSort = 3;

    /** Sent and not yet ordered: the ones still worth a message. */
    public static function getNavigationBadge(): ?string
    {
        $waiting = static::getModel()::whereNull('order_id')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->count();

        return $waiting > 0 ? (string) $waiting : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label('Hazırlanıb')
                    ->dateTime('d.m.Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('note')->label('Kimin üçün')
                    ->placeholder('—')->searchable()->wrap()->weight('bold'),
                Tables\Columns\TextColumn::make('pieces')->label('Ədəd')
                    ->state(fn (CartHandoff $row) => $row->pieces()),
                Tables\Columns\TextColumn::make('total')->label('Məbləğ')
                    ->state(fn (CartHandoff $row) => $row->totalLabel()),
                Tables\Columns\TextColumn::make('state')->label('Vəziyyət')
                    ->badge()
                    ->state(fn (CartHandoff $row) => $row->stateLabel())
                    ->color(fn (CartHandoff $row) => match ($row->state()) {
                        'ordered' => 'success',
                        'expired' => 'gray',
                        'opened' => 'warning',
                        default => 'info',
                    }),
                Tables\Columns\TextColumn::make('order_id')->label('Sifariş')
                    ->placeholder('—')
                    ->url(fn (CartHandoff $row) => $row->order_id
                        ? OrderResource::getUrl('edit', ['record' => $row->order_id])
                        : null)
                    ->formatStateUsing(fn ($state) => $state ? '#' . $state : '—'),
                Tables\Columns\TextColumn::make('maker.name')->label('Kim hazırlayıb')
                    ->placeholder('—')->visibleFrom('lg'),
            ])
            ->actions([
                /* The link itself, to copy out of the panel and paste into
                   whichever conversation it belongs to. */
                Tables\Actions\Action::make('link')
                    ->label('Link')
                    ->icon('heroicon-o-clipboard')
                    ->modalHeading('Səbətin linki')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Bağla')
                    ->modalContent(fn (CartHandoff $row) => new HtmlString(
                        '<p style="word-break:break-all; font-family:ui-monospace,monospace; font-size:.85rem; '
                        . 'padding:.7rem .8rem; border-radius:.6rem; background:rgba(128,128,128,.12)">'
                        . e($row->url()) . '</p>'
                        . '<p style="font-size:.85rem; opacity:.8">'
                        . ($row->isOpen()
                            ? 'Müştəri bu linki açanda səbət onun qarşısına çıxır.'
                            : 'Bu link artıq işləmir.')
                        . '</p>'
                    )),
                Tables\Actions\DeleteAction::make()->label('Sil'),
            ])
            ->bulkActions([Tables\Actions\DeleteBulkAction::make()])
            ->emptyStateHeading('Hələ hazır səbət yoxdur')
            ->emptyStateDescription('Saytda qutunu özünüz yığın, səbətdə «Linki yarat» düyməsinə basın — link burada görünəcək.');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListCartHandoffs::route('/')];
    }

    public static function canCreate(): bool
    {
        // A basket is built on the site, not typed into a form.
        return false;
    }
}
