<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AdminOnly;
use App\Filament\Resources\PaymentAccountResource\Pages;
use App\Models\PaymentAccount;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * The owner's own cards, M10 wallets and bank accounts. A new order is sent
 * to the first one that has not taken its share of orders yet.
 */
class PaymentAccountResource extends Resource
{
    use AdminOnly;

    protected static ?string $model = PaymentAccount::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationGroup = 'Mühasibatlıq';

    protected static ?string $navigationLabel = 'Ödəniş hesabları';

    protected static ?string $modelLabel = 'hesab';

    protected static ?string $pluralModelLabel = 'ödəniş hesabları';

    protected static ?int $navigationSort = 0;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Hesab')
                    ->description('Müştəri bu hesaba köçürür. Nömrəni yalnız siz və ödəniş edən müştəri görür.')
                    ->schema([
                        Forms\Components\Select::make('type')
                            ->label('Növ')
                            ->options(fn () => collect(PaymentAccount::TYPES)->map(fn ($t) => $t[0] . ' — ' . $t[1])->all())
                            ->default(PaymentAccount::CARD)
                            ->live()
                            ->required(),
                        Forms\Components\TextInput::make('label')
                            ->label('Bank və ad')
                            ->placeholder('Məs. Kapital Bank / Elxan Elxanlı')
                            ->required()
                            ->maxLength(120),
                        Forms\Components\TextInput::make('number')
                            ->label(fn (Forms\Get $get) => match ($get('type')) {
                                PaymentAccount::M10 => 'M10 nömrəsi',
                                PaymentAccount::IBAN => 'IBAN',
                                default => 'Kart nömrəsi (16 rəqəm)',
                            })
                            ->placeholder(fn (Forms\Get $get) => match ($get('type')) {
                                PaymentAccount::M10 => '+994 50 000 00 00',
                                PaymentAccount::IBAN => 'AZ00XXXX00000000000000000000',
                                default => '0000 0000 0000 0000',
                            })
                            ->required()
                            ->maxLength(40)
                            ->rule(fn (Forms\Get $get) => function (string $attribute, $value, \Closure $fail) use ($get) {
                                $raw = preg_replace('/\s+/', '', (string) $value);
                                $ok = match ($get('type')) {
                                    PaymentAccount::CARD => (bool) preg_match('/^\d{16,19}$/', $raw),
                                    PaymentAccount::IBAN => (bool) preg_match('/^AZ\d{2}[A-Z0-9]{20,28}$/i', $raw),
                                    default => (bool) preg_match('/^\+?\d{9,15}$/', $raw),
                                };
                                if (! $ok) {
                                    $fail(match ($get('type')) {
                                        PaymentAccount::CARD => 'Kart nömrəsi 16 rəqəm olmalıdır.',
                                        PaymentAccount::IBAN => 'IBAN AZ ilə başlamalıdır, məs. AZ21NABZ00000000137010001944.',
                                        default => 'Nömrə +994 50 000 00 00 şəklində olmalıdır.',
                                    });
                                }
                            })
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('note')
                            ->label('Qeyd (müştəri görür)')
                            ->placeholder('Məs. köçürmədə sifariş nömrəsini yazın')
                            ->maxLength(160)
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('sort_order')->label('Sıra nömrəsi')->numeric()->default(0),
                        Forms\Components\Toggle::make('is_active')->label('İstifadə olunur')->default(true),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('type')
                    ->label('Növ')
                    ->badge()
                    ->formatStateUsing(fn (PaymentAccount $r) => $r->typeLabel()),
                Tables\Columns\TextColumn::make('label')
                    ->label('Hesab')
                    ->weight('bold')
                    ->description(fn (PaymentAccount $r) => $r->masked()),
                Tables\Columns\TextColumn::make('used')
                    ->label('Son ' . PaymentAccount::windowHours() . ' saatda')
                    ->getStateUsing(fn (PaymentAccount $r) => $r->used() . ' / ' . PaymentAccount::limit() . ' sifariş')
                    ->color(fn (PaymentAccount $r) => $r->isFull() ? 'warning' : null)
                    ->description(fn (PaymentAccount $r) => $r->isFull() ? 'Doludur — növbəti hesaba keçilir' : null),
                Tables\Columns\IconColumn::make('is_active')->label('İşləkdir')->boolean(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()])
            ->emptyStateHeading('Hələ ödəniş hesabı yoxdur')
            ->emptyStateDescription('Hesab əlavə edilməyincə müştəri ödəniş səhifəsini görmür, sifariş sadəcə qeydə alınır.');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPaymentAccounts::route('/'),
            'create' => Pages\CreatePaymentAccount::route('/create'),
            'edit' => Pages\EditPaymentAccount::route('/{record}/edit'),
        ];
    }
}
