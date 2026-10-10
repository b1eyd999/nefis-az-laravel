<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AdminOnly;
use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\Chocolate;
use App\Models\Product;
use App\Models\SavedCart;
use App\Models\User;
use App\Models\Wrapping;
use App\Support\Contact;
use App\Support\Letter;
use App\Support\LiveMaterials;
use App\Support\Price;
use Closure;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Hash;

/**
 * The site's users, and the role each has: customer, manager (the orders
 * only) or admin (everything). Users are never deleted from here — their
 * orders would go with them.
 */
class UserResource extends Resource
{
    use AdminOnly;

    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'İstifadəçilər';

    protected static ?string $modelLabel = 'istifadəçi';

    protected static ?string $pluralModelLabel = 'istifadəçilər';

    protected static ?int $navigationSort = 20;

    public const ROLE_HELP = [
        User::CUSTOMER => 'Saytda sifariş verir; admin panelinə girə bilməz.',
        User::COURIER => 'Öz telefonunda yalnız ona verilmiş sifarişləri görür (/kuryer); admin panelinə girməz.',
        User::MANAGER => 'Admin panelinə girir, sifarişləri və öz balansını görür, statusu dəyişir.',
        User::ADMIN => 'Hər şeyə: dizaynlar, səhnələr, şokoladlar, sifarişlər, istifadəçilər.',
    ];

    /**
     * The share of the net profit the admin gives a manager or admin. All the
     * shares together can never pass 100 %.
     */
    public static function percentField(): Forms\Components\TextInput
    {
        return Forms\Components\TextInput::make('profit_percent')
            ->label('Mənfəətdən pay')
            ->numeric()->minValue(0)->maxValue(100)->step(0.01)->suffix('%')->default(0)
            ->visible(fn (Forms\Get $get) => in_array($get('role'), [User::MANAGER, User::ADMIN], true))
            ->helperText(function (?User $record) {
                $given = (float) User::where('id', '!=', $record?->id ?? 0)->sum('profit_percent');

                return 'Başqalarına verilib: '.rtrim(rtrim(number_format($given, 2, '.', ''), '0'), '.').'%. Menecer öz balansını "Balans"da görür.';
            })
            ->rule(fn (?User $record) => function (string $attribute, $value, Closure $fail) use ($record) {
                $given = (float) User::where('id', '!=', $record?->id ?? 0)->sum('profit_percent');
                if ($given + (float) $value > 100.001) {
                    $fail('Payların cəmi 100%-dən çox ola bilməz — başqalarına artıq '.rtrim(rtrim(number_format($given, 2, '.', ''), '0'), '.').'% verilib.');
                }
            });
    }

    /**
     * Saves the role and the share together. Neither a customer nor a courier
     * has a share of the profit, so moving someone to either takes it away
     * rather than leaving a figure nobody can see on a screen.
     */
    public static function applyRole(User $user, string $role, $percent): void
    {
        $user->role = $role;
        $user->forceFill([
            'profit_percent' => in_array($role, [User::CUSTOMER, User::COURIER], true)
                ? 0
                : round((float) $percent, 2),
        ])->save();
    }

    /**
     * A password that can be read down a telephone or pasted into Instagram:
     * no letter that could be a digit, no digit that could be a letter.
     */
    public static function readablePassword(): string
    {
        $alphabet = 'abcdefghjkmnpqrstuvwxyzACDEFGHJKLMNPQRSTUVWXYZ23456789';

        $out = '';
        for ($i = 0; $i < 10; $i++) {
            $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $out;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('İstifadəçi')
                    ->schema([
                        Forms\Components\TextInput::make('name')->label('Ad')->required()->maxLength(255),
                        Forms\Components\TextInput::make('email')->label('E-poçt')->email()->required()->unique(ignoreRecord: true),
                        Forms\Components\TextInput::make('phone')->label('Telefon')->tel()
                            ->rule(fn (?User $record) => function (string $attribute, $value, Closure $fail) use ($record) {
                                $phone = Contact::az((string) $value);
                                if ($phone && User::samePhone($value)->whereKeyNot($record?->id)->exists()) {
                                    $fail('Bu nömrə başqa hesabdadır.');
                                }
                            }),
                        Forms\Components\Placeholder::make('created')
                            ->label('Qeydiyyat')
                            ->content(fn (?User $record) => $record?->created_at?->format('d.m.Y H:i') ?? '—'),
                    ])->columns(2),
                Forms\Components\Section::make('Rol')
                    ->schema([
                        Forms\Components\Radio::make('role')
                            ->label('')
                            ->options(User::ROLES)
                            ->descriptions(self::ROLE_HELP)
                            ->live()
                            ->required(),
                        self::percentField(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Ad')
                    ->searchable()
                    ->weight('bold')
                    ->description(fn (User $u) => $u->email),
                Tables\Columns\TextColumn::make('email')->label('E-poçt')->searchable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('phone')->label('Telefon')->searchable()->placeholder('—'),
                Tables\Columns\TextColumn::make('role')
                    ->label('Rol')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => User::ROLES[$state] ?? $state)
                    ->color(fn (?string $state) => match ($state) {
                        User::ADMIN => 'danger', User::MANAGER => 'warning', default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('profit_percent')
                    ->label('Pay')
                    ->formatStateUsing(fn ($state) => (float) $state > 0 ? rtrim(rtrim(number_format((float) $state, 2, '.', ''), '0'), '.').'%' : '—')
                    ->sortable(),
                Tables\Columns\TextColumn::make('orders_count')
                    ->label('Sifariş')
                    ->counts('orders')
                    ->sortable(),
                /* What is sitting in his basket right now — the thing the
                   owner wants to know before he puts anything else in it. */
                Tables\Columns\TextColumn::make('basket')
                    ->label('Səbət')
                    ->state(fn (User $u) => ($n = count($u->savedCart?->lines() ?? [])) ? $n : '—')
                    ->badge()
                    ->color(fn ($state) => $state === '—' ? 'gray' : 'success')
                    ->visibleFrom('md'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Qeydiyyat')
                    ->dateTime('d.m.Y')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('role')->label('Rol')->options(User::ROLES),
            ])
            ->actions([
                Tables\Actions\Action::make('role')
                    ->label('Rol ver')
                    ->icon('heroicon-o-shield-check')
                    ->fillForm(fn (User $u) => ['role' => $u->role, 'profit_percent' => $u->profit_percent])
                    ->form([
                        Forms\Components\Radio::make('role')
                            ->label('Rol')
                            ->options(User::ROLES)
                            ->descriptions(self::ROLE_HELP)
                            ->live()
                            ->required(),
                        self::percentField(),
                    ])
                    ->action(function (User $u, array $data, Tables\Actions\Action $action) {
                        self::guardRoleChange($u, $data['role'], fn () => $action->halt());
                        self::applyRole($u, $data['role'], $data['profit_percent'] ?? 0);
                        $share = $u->profit_percent > 0 ? ' · pay '.rtrim(rtrim(number_format($u->profit_percent, 2, '.', ''), '0'), '.').'%' : '';
                        Notification::make()->success()->title($u->name.': '.User::ROLES[$data['role']].$share)->send();
                    }),
                /* A customer writes that he cannot sign in — the number he
                   typed at the sign-up was wrong, or he has forgotten what
                   he chose. The owner gives him a new password here and
                   sends it to him himself; the old one stops working. */
                Tables\Actions\Action::make('password')
                    ->label('Şifrə')
                    ->icon('heroicon-o-key')
                    ->color('gray')
                    ->modalHeading(fn (User $u) => $u->name . ' — yeni şifrə')
                    ->modalDescription('Köhnə şifrə dərhal işləməyəcək. Yenisini özünüz müştəriyə göndərin.')
                    ->modalSubmitActionLabel('Şifrəni dəyiş')
                    ->fillForm(fn () => ['password' => self::readablePassword()])
                    ->form([
                        Forms\Components\TextInput::make('password')
                            ->label('Yeni şifrə')
                            ->required()
                            ->minLength(8)
                            ->maxLength(72)
                            ->password()
                            ->revealable()
                            ->helperText('Hazır bir şifrə yazılıb — gözə basıb oxuyun, istəsəniz özünüz dəyişin.'),
                    ])
                    ->action(function (User $u, array $data) {
                        $u->forceFill(['password' => Hash::make($data['password'])])->save();

                        Notification::make()->success()
                            ->title('Şifrə dəyişdirildi')
                            // Shown in full on purpose: he has to send it on.
                            ->body($u->name . ': ' . $data['password'])
                            ->persistent()
                            ->send();
                    }),
                /* A box into somebody's basket, from here.
                   «Hazır səbətlər» is the long way round: the owner builds
                   the box on the site and hands over a link. This is for the
                   short one — a customer who has signed up and is waiting on
                   the telephone while the owner drops a design into his
                   basket. He opens the shop and it is there. */
                Tables\Actions\Action::make('basket')
                    ->label('Səbətə at')
                    ->icon('heroicon-o-shopping-bag')
                    ->color('success')
                    ->modalHeading(fn (User $u) => $u->name . ' — səbətinə məhsul at')
                    ->modalDescription('Məhsul onun səbətinə əlavə olunur; səbətindəki digər məhsullar silinmir. '
                        . 'Xəbərdarlıq getmir — sayta girəndə görəcək.')
                    ->modalSubmitActionLabel('Səbətə at')
                    ->form([
                        Forms\Components\Select::make('product_id')
                            ->label('Dizayn')
                            ->placeholder('Dizaynın adını yazın')
                            ->required()
                            ->searchable()
                            ->live()
                            ->getSearchResultsUsing(fn (string $search) => Product::query()
                                ->where('is_active', true)
                                ->where('name', 'like', '%' . $search . '%')
                                ->orderBy('name')->limit(25)
                                ->pluck('name', 'id')->all())
                            ->getOptionLabelUsing(fn ($value) => Product::find($value)?->name)
                            ->options(fn () => Product::where('is_active', true)
                                ->orderBy('sort_order')->orderBy('name')->limit(25)
                                ->pluck('name', 'id')->all()),
                        Forms\Components\Select::make('chocolate_id')
                            ->label('İçindəki şokolad')
                            ->placeholder('Seçilməsin — müştəri özü seçsin')
                            ->options(fn () => Chocolate::shown()->get()
                                ->map->toCustomer()->sortBy('price')
                                ->mapWithKeys(fn ($c) => [$c['id'] => $c['name'] . ' — ' . Price::format($c['price'])])
                                ->all())
                            ->searchable()
                            ->live(),
                        Forms\Components\Select::make('wrapping_id')
                            ->label('Qablaşdırma')
                            ->placeholder('Yoxdur')
                            ->options(fn () => Wrapping::shown()->get()
                                ->map->toCustomer()
                                ->mapWithKeys(fn ($w) => [$w['id'] => $w['name'] . ' — ' . Price::format($w['price'])])
                                ->all())
                            ->searchable()
                            ->live(),
                        Forms\Components\Toggle::make('letter')
                            ->label('Polaroid məktub (+' . Price::format(Letter::price()) . ')')
                            ->helperText('Mətni və şəkli sonra özünüz yazırsınız — səbətdə yalnız xidmət və qiyməti görünür.')
                            ->visible(fn () => Letter::enabled())
                            ->live(),
                        Forms\Components\Toggle::make('ar')
                            ->label('Canlı şəkil — qutunun canlanması (+' . Price::format(LiveMaterials::price()) . ')')
                            ->helperText('Videonu müştəri sonra göndərir, siz «Canlı şəkillər» bölməsində qoşursunuz.')
                            ->visible(fn () => LiveMaterials::enabled())
                            ->live(),
                        Forms\Components\TextInput::make('quantity')
                            ->label('Ədəd')
                            ->numeric()->default(1)->minValue(1)->maxValue(SavedCart::MOST)
                            ->required()
                            ->live(debounce: 400),
                        /* What he will see in his basket, worked out the way
                           the basket works it out, before anything is put in. */
                        Forms\Components\Placeholder::make('sum')
                            ->label('Səbətində görünəcək')
                            ->content(function (Forms\Get $get) {
                                $product = Product::find($get('product_id'));
                                if (! $product) {
                                    return new \Illuminate\Support\HtmlString('<span style="opacity:.7">Dizayn seçin.</span>');
                                }
                                $bar = $get('chocolate_id') ? Chocolate::find($get('chocolate_id')) : null;
                                $wrap = $get('wrapping_id') ? Wrapping::find($get('wrapping_id')) : null;
                                $one = (float) $product->price
                                    + ($bar ? (float) $bar->price() : 0)
                                    + ($wrap ? (float) $wrap->price : 0)
                                    + ($get('letter') ? Letter::price() : 0)
                                    + ($get('ar') ? LiveMaterials::price() : 0);
                                $n = max(1, (int) $get('quantity'));
                                $extras = array_filter([
                                    $wrap?->tr('name'),
                                    $get('letter') ? 'polaroid məktub' : null,
                                    $get('ar') ? 'canlı şəkil' : null,
                                ]);

                                return new \Illuminate\Support\HtmlString(
                                    e($product->name) . ' · ' . $n . ' ədəd — <b>' . e(Price::format($one * $n)) . '</b>'
                                    . ($extras ? '<br><span style="opacity:.75">' . e(implode(' · ', $extras)) . '</span>' : '')
                                    . ($bar ? '' : '<br><span style="opacity:.75">Şokolad seçilməyib: qutu tək gedəcək.</span>')
                                    . ($product->photoSlots()->count()
                                        ? '<br><span style="opacity:.75">Bu dizayn şəkil istəyir. Buradan şəkilsiz düşür — '
                                          . 'şəkil lazımdırsa, saytda yığıb «Hazır səbət» göndərin.</span>'
                                        : '')
                                );
                            }),
                    ])
                    ->action(function (User $u, array $data) {
                        $product = Product::find($data['product_id']);
                        if (! $product) {
                            Notification::make()->danger()->title('Dizayn tapılmadı')->send();

                            return;
                        }

                        $bar = $data['chocolate_id'] ? Chocolate::find($data['chocolate_id']) : null;
                        $wrap = ($data['wrapping_id'] ?? null) ? Wrapping::find($data['wrapping_id']) : null;

                        /* The letter's words and the live photo's video are not
                           here: the customer sends them afterwards. What goes
                           into the basket is the service and its price, so he
                           sees what he is paying for; the owner attaches the
                           rest from «Polaroid məktub» and «Canlı şəkillər». */
                        $added = SavedCart::give($u, [SavedCart::line(
                            $product->id,
                            (int) $data['quantity'],
                            $bar?->toCustomer(),
                            $wrap?->toCustomer(),
                            empty($data['letter']) ? null : ['text' => null, 'photo' => null, 'price' => Letter::price()],
                            empty($data['ar']) ? null : ['video' => null, 'image' => null, 'mind' => null, 'price' => LiveMaterials::price()],
                        )]);

                        if (! $added) {
                            Notification::make()->warning()
                                ->title('Səbət doludur')
                                ->body('Bir səbətdə ən çoxu ' . SavedCart::MOST . ' sətir olur.')
                                ->send();

                            return;
                        }

                        $extras = array_filter([
                            $wrap?->tr('name'),
                            empty($data['letter']) ? null : 'polaroid məktub',
                            empty($data['ar']) ? null : 'canlı şəkil',
                        ]);

                        Notification::make()->success()
                            ->title($u->name . ' — səbətinə atıldı')
                            ->body($product->name . ' · ' . $data['quantity'] . ' ədəd'
                                . ($extras ? ' · ' . implode(' · ', $extras) : '')
                                . '. O, sayta girəndə görəcək.')
                            ->send();
                    }),
                Tables\Actions\EditAction::make()->label('Bax'),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('role')
                    ->label('Rol ver')
                    ->icon('heroicon-o-shield-check')
                    ->form([
                        Forms\Components\Radio::make('role')->label('Rol')->options(User::ROLES)->required(),
                    ])
                    ->action(function (Collection $records, array $data, Tables\Actions\BulkAction $action) {
                        foreach ($records as $u) {
                            self::guardRoleChange($u, $data['role'], fn () => $action->halt());
                        }
                        // Everyone keeps their share, except whoever becomes a customer.
                        $records->each(fn (User $u) => self::applyRole($u, $data['role'], $u->profit_percent));
                        Notification::make()->success()->title($records->count().' istifadəçi: '.User::ROLES[$data['role']])->send();
                    })
                    ->deselectRecordsAfterCompletion(),
            ]);
    }

    /**
     * An admin cannot take their own admin role away, and the last admin
     * cannot lose theirs — either would lock everyone out of this panel.
     */
    public static function guardRoleChange(User $user, string $role, Closure $halt): void
    {
        if ($user->role !== User::ADMIN || $role === User::ADMIN) {
            return;
        }
        $reason = null;
        if ($user->is(auth()->user())) {
            $reason = 'Öz admin rolunuzu götürə bilməzsiniz — başqa admin bunu edə bilər.';
        } elseif (User::where('role', User::ADMIN)->count() <= 1) {
            $reason = 'Bu, sonuncu admindir — əvvəlcə başqa birinə admin rolu verin.';
        }
        if ($reason) {
            Notification::make()->danger()->title('Rol dəyişmədi')->body($reason)->send();
            $halt();
        }
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\OrdersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;   // people sign up on the site themselves
    }

    public static function canDelete($record): bool
    {
        return false;   // their orders would be deleted with them
    }
}
