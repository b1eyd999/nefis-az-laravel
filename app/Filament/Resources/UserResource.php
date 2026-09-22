<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AdminOnly;
use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\User;
use Closure;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

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

                return 'Başqalarına verilib: ' . rtrim(rtrim(number_format($given, 2, '.', ''), '0'), '.') . '%. Menecer öz balansını "Balans"da görür.';
            })
            ->rule(fn (?User $record) => function (string $attribute, $value, Closure $fail) use ($record) {
                $given = (float) User::where('id', '!=', $record?->id ?? 0)->sum('profit_percent');
                if ($given + (float) $value > 100.001) {
                    $fail('Payların cəmi 100%-dən çox ola bilməz — başqalarına artıq ' . rtrim(rtrim(number_format($given, 2, '.', ''), '0'), '.') . '% verilib.');
                }
            });
    }

    /** Saves the role and the share together; a customer has no share. */
    public static function applyRole(User $user, string $role, $percent): void
    {
        $user->role = $role;
        $user->forceFill(['profit_percent' => $role === User::CUSTOMER ? 0 : round((float) $percent, 2)])->save();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('İstifadəçi')
                    ->schema([
                        Forms\Components\TextInput::make('name')->label('Ad')->required()->maxLength(255),
                        Forms\Components\TextInput::make('email')->label('E-poçt')->email()->required()->unique(ignoreRecord: true),
                        Forms\Components\TextInput::make('phone')->label('Telefon')->tel(),
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
                    ->formatStateUsing(fn ($state) => (float) $state > 0 ? rtrim(rtrim(number_format((float) $state, 2, '.', ''), '0'), '.') . '%' : '—')
                    ->sortable(),
                Tables\Columns\TextColumn::make('orders_count')
                    ->label('Sifariş')
                    ->counts('orders')
                    ->sortable(),
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
                        $share = $u->profit_percent > 0 ? ' · pay ' . rtrim(rtrim(number_format($u->profit_percent, 2, '.', ''), '0'), '.') . '%' : '';
                        Notification::make()->success()->title($u->name . ': ' . User::ROLES[$data['role']] . $share)->send();
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
                        Notification::make()->success()->title($records->count() . ' istifadəçi: ' . User::ROLES[$data['role']])->send();
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
