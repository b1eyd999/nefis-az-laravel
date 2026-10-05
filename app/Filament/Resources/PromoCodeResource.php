<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PromoCodeResource\Pages;
use App\Models\PromoCode;
use App\Support\Price;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * The owner's promo codes: he makes one, says what it takes off, and the
 * customer types it at the checkout.
 *
 * What a code was worth is copied onto the order when it is used, so editing
 * the percent here changes what the NEXT customer gets and never what someone
 * has already paid.
 */
class PromoCodeResource extends Resource
{
    protected static ?string $model = PromoCode::class;

    protected static ?string $navigationIcon = 'heroicon-o-ticket';

    protected static ?string $navigationGroup = 'Mağaza';

    protected static ?string $modelLabel = 'Promokod';

    protected static ?string $pluralModelLabel = 'Promokodlar';

    protected static ?int $navigationSort = 36;

    /** Money off is the owner's own business, not a manager's. */
    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Kod')->schema([
                Forms\Components\TextInput::make('code')
                    ->label('Promokod')
                    ->required()
                    ->maxLength(32)
                    // Upper-cased before the unique check sees it: the model
                    // stores it upper-cased, so checking the typed case let
                    // "yaz10" through to crash against the stored "YAZ10".
                    ->dehydrateStateUsing(fn (?string $state) => \Illuminate\Support\Str::upper(trim((string) $state)))
                    ->rule(fn (?\App\Models\PromoCode $record) => \Illuminate\Validation\Rule::unique('promo_codes', 'code')
                        ->ignore($record?->id)
                        ->where(fn ($q) => $q->whereRaw('UPPER(code) = ?', [\Illuminate\Support\Str::upper(trim((string) request()->input('data.code')))])))
                    ->helperText('Böyük hərflərlə saxlanır; müştəri necə yazsa da tapılır.')
                    ->default(fn () => PromoCode::make())
                    ->suffixAction(
                        Forms\Components\Actions\Action::make('generate')
                            ->label('Yenisini yarat')
                            ->icon('heroicon-m-arrow-path')
                            ->action(fn (Forms\Set $set) => $set('code', PromoCode::make())),
                    ),
                Forms\Components\TextInput::make('percent')
                    ->label('Endirim, %')
                    ->numeric()->minValue(1)->maxValue(100)->step(0.5)
                    ->required()
                    ->helperText('Yalnız məhsullardan tutulur — çatdırılma və təcili haqqı toxunulmur.'),
                Forms\Components\Toggle::make('is_active')
                    ->label('İşləsin')
                    ->default(true),
                Forms\Components\TextInput::make('note')
                    ->label('Qeyd (yalnız sizin üçün)')
                    ->maxLength(255)
                    ->helperText('Kimə verildiyi, hansı reklamda getdiyi.'),
            ])->columns(2),

            Forms\Components\Section::make('Hədlər')->schema([
                Forms\Components\DateTimePicker::make('starts_at')
                    ->label('Nə vaxtdan')
                    ->seconds(false)
                    ->helperText('Boş buraxsanız — elə indidən.'),
                Forms\Components\DateTimePicker::make('ends_at')
                    ->label('Nə vaxta qədər')
                    ->seconds(false)
                    ->helperText('Boş buraxsanız — müddətsiz.'),
                Forms\Components\TextInput::make('max_uses')
                    ->label('Neçə dəfə işlənə bilər')
                    ->numeric()->minValue(1)
                    ->helperText('Boş buraxsanız — limitsiz.'),
                Forms\Components\TextInput::make('min_total')
                    ->label('Minimal məhsul məbləği, ₼')
                    ->numeric()->minValue(0)->step(0.01)
                    ->helperText('Bundan aşağı səbətə tətbiq olunmur.'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Kod')
                    ->weight('bold')
                    ->copyable()
                    ->copyMessage('Kopyalandı')
                    ->searchable(),
                Tables\Columns\TextColumn::make('percent')
                    ->label('Endirim')
                    ->formatStateUsing(fn ($state) => rtrim(rtrim(number_format((float) $state, 2, '.', ''), '0'), '.') . '%')
                    ->sortable(),
                Tables\Columns\TextColumn::make('state')
                    ->label('Vəziyyət')
                    ->badge()
                    ->getStateUsing(fn (PromoCode $record) => $record->stateLabel())
                    ->color(fn (PromoCode $record) => $record->isUsable() ? 'success' : 'gray'),
                Tables\Columns\TextColumn::make('used_count')
                    ->label('İstifadə')
                    ->formatStateUsing(fn ($state, PromoCode $record) => $record->max_uses
                        ? $state . ' / ' . $record->max_uses
                        : (string) $state),
                Tables\Columns\TextColumn::make('ends_at')
                    ->label('Bitir')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('müddətsiz')
                    ->sortable(),
                Tables\Columns\TextColumn::make('min_total')
                    ->label('Minimal səbət')
                    ->formatStateUsing(fn ($state) => $state ? Price::format((float) $state) : '—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('note')
                    ->label('Qeyd')
                    ->wrap()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('İşləyənlər'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->modalDescription('Kod silinir. Onunla verilmiş sifarişlərə heç nə olmur — endirim onların üstündə saxlanılır.'),
            ])
            ->headerActions([
                Tables\Actions\Action::make('quick')
                    ->label('Tez kod yarat')
                    ->icon('heroicon-m-sparkles')
                    ->form([
                        Forms\Components\TextInput::make('percent')
                            ->label('Endirim, %')
                            ->numeric()->minValue(1)->maxValue(100)->step(0.5)
                            ->default(10)->required(),
                        Forms\Components\TextInput::make('note')->label('Qeyd')->maxLength(255),
                    ])
                    ->action(function (array $data) {
                        $code = PromoCode::create([
                            'code' => PromoCode::make(),
                            'percent' => $data['percent'],
                            'note' => $data['note'] ?? null,
                            'is_active' => true,
                        ]);

                        Notification::make()
                            ->success()
                            ->title('Kod hazırdır: ' . $code->code)
                            ->body('Müştəri onu sifarişi tamamlayarkən yazır.')
                            ->persistent()
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPromoCodes::route('/'),
            'create' => Pages\CreatePromoCode::route('/create'),
            'edit' => Pages\EditPromoCode::route('/{record}/edit'),
        ];
    }
}
