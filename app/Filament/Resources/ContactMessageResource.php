<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AdminOnly;
use App\Filament\Resources\ContactMessageResource\Pages;
use App\Models\ContactMessage;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Messages written from the contact page.
 *
 * Each one goes to the owner's Telegram the moment it arrives; it is kept
 * here as well, because a message that exists only in a chat is lost the
 * first time the bot's token changes or the phone is wiped. The list is the
 * owner's queue: he rings or writes, then ticks it off.
 */
class ContactMessageResource extends Resource
{
    use AdminOnly;

    protected static ?string $model = ContactMessage::class;

    protected static ?string $navigationIcon = 'heroicon-o-envelope';

    protected static ?string $navigationLabel = 'Saytdan mesajlar';

    protected static ?string $modelLabel = 'mesaj';

    protected static ?string $pluralModelLabel = 'saytdan mesajlar';

    protected static ?int $navigationSort = 3;

    /** Unanswered ones sit as a number in the menu. */
    public static function getNavigationBadge(): ?string
    {
        $waiting = static::getModel()::whereNull('answered_at')->count();

        return $waiting > 0 ? (string) $waiting : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Mesaj')
                ->schema([
                    Forms\Components\TextInput::make('name')->label('Ad')->required()->maxLength(150),
                    Forms\Components\TextInput::make('phone')->label('Telefon')->tel()->required()->maxLength(40),
                    Forms\Components\TextInput::make('email')->label('E-poçt')->email()->maxLength(150),
                    Forms\Components\TextInput::make('about')->label('Hansı sifariş barədə')->maxLength(150),
                    Forms\Components\Textarea::make('message')->label('Yazdığı')->rows(6)->required()
                        ->maxLength(2000)->columnSpanFull(),
                ])->columns(2),

            Forms\Components\Section::make('Cavab')
                ->description('Zəng etdikdən və ya yazdıqdan sonra buranı işarələyin — menyudaki rəqəm azalır.')
                ->schema([
                    Forms\Components\Toggle::make('answered')
                        ->label('Cavab verildi')
                        ->default(false)
                        ->afterStateHydrated(fn (Forms\Components\Toggle $component, ?ContactMessage $record) => $component->state((bool) $record?->answered_at))
                        ->dehydrated(false)
                        ->live()
                        ->columnSpanFull(),
                    Forms\Components\Placeholder::make('answered_when')
                        ->label('Nə vaxt')
                        ->content(fn (?ContactMessage $record) => $record?->answered_at
                            ? $record->answered_at->format('d.m.Y H:i')
                                . ($record->answerer ? ' — ' . $record->answerer->name : '')
                            : 'Hələ yox')
                        ->columnSpanFull(),
                    Forms\Components\Textarea::make('note')->label('Daxili qeyd')->rows(3)
                        ->helperText('Müştəri bunu görmür.')->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label('Tarix')->dateTime('d.m.Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('name')->label('Ad')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('phone')->label('Telefon')->searchable()
                    ->url(fn (ContactMessage $record) => 'tel:' . preg_replace('~[^0-9+]~', '', $record->phone)),
                Tables\Columns\TextColumn::make('message')->label('Yazdığı')->limit(70)->wrap()->searchable(),
                Tables\Columns\TextColumn::make('about')->label('Sifariş')->toggleable()->visibleFrom('lg'),
                Tables\Columns\IconColumn::make('answered_at')->label('Cavab')->boolean()
                    ->trueIcon('heroicon-o-check-circle')->falseIcon('heroicon-o-clock')
                    ->trueColor('success')->falseColor('warning'),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Tables\Filters\Filter::make('waiting')
                    ->label('Cavab gözləyənlər')
                    ->query(fn ($query) => $query->whereNull('answered_at'))
                    ->default(),
            ])
            ->actions([
                Tables\Actions\Action::make('whatsapp')
                    ->label('WhatsApp')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('success')
                    ->url(fn (ContactMessage $record) => $record->whatsapp(), shouldOpenInNewTab: true)
                    ->visible(fn (ContactMessage $record) => (bool) $record->whatsapp()),
                /* One tap for the common case: he has just rung the person
                   back and wants the number in the menu to go down. */
                Tables\Actions\Action::make('answered')
                    ->label(fn (ContactMessage $record) => $record->answered_at ? 'Cavabsız et' : 'Cavab verildi')
                    ->icon('heroicon-o-check')
                    ->color(fn (ContactMessage $record) => $record->answered_at ? 'gray' : 'success')
                    ->action(fn (ContactMessage $record) => $record->forceFill([
                        'answered_at' => $record->answered_at ? null : now(),
                        'answered_by' => $record->answered_at ? null : auth()->id(),
                    ])->save()),
                Tables\Actions\EditAction::make()->label('Aç'),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContactMessages::route('/'),
            'edit' => Pages\EditContactMessage::route('/{record}/edit'),
        ];
    }
}
