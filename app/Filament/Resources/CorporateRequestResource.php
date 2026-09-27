<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AdminOnly;
use App\Filament\Resources\CorporateRequestResource\Pages;
use App\Models\CorporateRequest;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

/**
 * Companies that have asked for the small chocolate with their own logo.
 *
 * Each row is a conversation, not an order: the owner reads what they want,
 * names a price, and marks where it got to. The logo they uploaded is here,
 * full size, because it is the first thing he needs to look at.
 */
class CorporateRequestResource extends Resource
{
    use AdminOnly;

    protected static ?string $model = CorporateRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationLabel = 'Şirkət müraciətləri';

    protected static ?string $modelLabel = 'müraciət';

    protected static ?string $pluralModelLabel = 'şirkət müraciətləri';

    protected static ?int $navigationSort = 2;

    /** New ones wait for an answer, so the number sits in the menu. */
    public static function getNavigationBadge(): ?string
    {
        $waiting = static::getModel()::where('status', 'new')->count();

        return $waiting > 0 ? (string) $waiting : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Müraciət')
                ->schema([
                    Forms\Components\Select::make('status')->label('Vəziyyət')
                        ->options(CorporateRequest::STATUSES)->required(),
                    Forms\Components\TextInput::make('company')->label('Şirkət')->required()->maxLength(150),
                    Forms\Components\TextInput::make('person')->label('Əlaqədar şəxs')->maxLength(150),
                    Forms\Components\TextInput::make('phone')->label('Telefon')->tel()->required()->maxLength(40),
                    Forms\Components\TextInput::make('email')->label('E-poçt')->email()->maxLength(150),
                    Forms\Components\TextInput::make('quantity')->label('Say')->numeric()->minValue(1)->required()->suffix('ədəd'),
                ])->columns(2),

            Forms\Components\Section::make('Nə istəyirlər')
                ->schema([
                    Forms\Components\Placeholder::make('logo_view')->label('Loqo')
                        ->content(fn (?CorporateRequest $record) => $record?->logoUrl()
                            ? new HtmlString('<a href="' . e($record->logoUrl()) . '" target="_blank" rel="noopener">'
                                . '<img src="' . e($record->logoUrl()) . '" alt="" style="max-height:9rem; max-width:100%; '
                                . 'background:#fff; padding:.6rem; border-radius:.6rem; border:1px solid rgba(128,128,128,.35)"></a>'
                                . '<br><a href="' . e($record->logoUrl()) . '" download style="font-size:.8rem; text-decoration:underline">Yüklə</a>')
                            : 'Loqo göndərməyiblər')
                        ->columnSpanFull(),
                    Forms\Components\Placeholder::make('box_color_view')->label('Qutunun rəngi')
                        ->content(fn (?CorporateRequest $record) => $record?->box_color
                            ? new HtmlString('<span style="display:inline-flex; align-items:center; gap:.5rem">'
                                . '<span style="width:1.4rem; height:1.4rem; border-radius:50%; border:1px solid rgba(128,128,128,.5); '
                                . 'background:' . e($record->box_color) . '"></span>' . e($record->box_color) . '</span>')
                            : 'Seçməyiblər'),
                    Forms\Components\TextInput::make('slogan')->label('Şüar')->maxLength(120),
                    Forms\Components\TextInput::make('qr_target')->label('QR kod hara aparır')->maxLength(300)->columnSpanFull(),
                    Forms\Components\Textarea::make('note')->label('Qeyd')->rows(3)->columnSpanFull(),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label('Tarix')->dateTime('d.m.Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('company')->label('Şirkət')->searchable()->weight('bold')->wrap(),
                Tables\Columns\TextColumn::make('quantity')->label('Say')->numeric()->sortable()->suffix(' ədəd'),
                Tables\Columns\TextColumn::make('phone')->label('Telefon')->searchable()
                    ->url(fn (CorporateRequest $record) => 'tel:' . preg_replace('~[^0-9+]~', '', $record->phone)),
                Tables\Columns\ImageColumn::make('logo')->label('Loqo')->disk('public')->height(38)->visibleFrom('lg'),
                Tables\Columns\TextColumn::make('status')->label('Vəziyyət')->badge()
                    ->formatStateUsing(fn (string $state) => CorporateRequest::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'new' => 'warning', 'talking' => 'info', 'sample' => 'primary',
                        'won' => 'success', 'lost' => 'danger', default => 'gray',
                    }),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')->label('Vəziyyət')->options(CorporateRequest::STATUSES),
            ])
            ->actions([
                Tables\Actions\Action::make('whatsapp')
                    ->label('WhatsApp')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('success')
                    ->url(fn (CorporateRequest $record) => $record->whatsapp(), shouldOpenInNewTab: true)
                    ->visible(fn (CorporateRequest $record) => (bool) $record->whatsapp()),
                Tables\Actions\EditAction::make()->label('Aç'),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCorporateRequests::route('/'),
            'edit' => Pages\EditCorporateRequest::route('/{record}/edit'),
        ];
    }
}
