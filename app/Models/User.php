<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const CUSTOMER = 'customer';

    public const MANAGER = 'manager';

    public const ADMIN = 'admin';

    /* Not staff: a courier sees the orders handed to him and nothing else of
       the shop, so he never reaches the panel at all. His own screen is
       /kuryer. */
    public const COURIER = 'courier';

    /** The roles, as the admin panel names them. */
    public const ROLES = [
        self::CUSTOMER => 'Müştəri',
        self::COURIER => 'Kuryer',
        self::MANAGER => 'Menecer',
        self::ADMIN => 'Admin',
    ];

    protected static function booted(): void
    {
        // `is_admin` follows the role, which the editors and older code check;
        // code that still sets `is_admin` moves the role with it.
        static::saving(function (User $user) {
            if ($user->isDirty('role') || ! $user->exists && $user->role) {
                $user->is_admin = $user->role === self::ADMIN;
            } elseif ($user->isDirty('is_admin')) {
                $user->role = $user->is_admin ? self::ADMIN : ($user->role === self::ADMIN ? self::CUSTOMER : ($user->role ?? self::CUSTOMER));
            }
            $user->role ??= self::CUSTOMER;
        });
    }

    /** Admins and managers get into the panel; managers see the orders only. */
    /**
     * Whoever signs in with this: an e-mail as it is written, or a phone in
     * any of the shapes people type it (+994 55 123 45 67, 0551234567,
     * 55 123 45 67). The last nine digits are the number itself, so they are
     * what is compared; if two accounts answer to them, neither is taken.
     */
    /**
     * The one string that stands for whoever is being looked up.
     *
     * The lock on the login form counts tries against this, not against what
     * was typed: "+994 50 123 45 67", "0501234567" and "994501234567" all
     * reach the same account, so counted apart they bought five fresh tries
     * each.
     */
    public static function loginKey(string $login): string
    {
        $login = trim($login);
        if (str_contains($login, '@')) {
            return 'e:' . \Illuminate\Support\Str::lower($login);
        }
        $digits = preg_replace('/\D/', '', $login);

        return 'p:' . substr((string) $digits, -9);
    }

    public static function byLogin(string $login): ?self
    {
        $login = trim($login);

        if (str_contains($login, '@')) {
            return static::where('email', $login)->first();
        }

        $digits = preg_replace('/\D/', '', $login);
        if (strlen($digits) < 7) {
            return null;
        }

        $matches = static::whereNotNull('phone')
            ->whereRaw("REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '(', ''), ')', ''), '+', '') LIKE ?",
                ['%'.substr($digits, -9)])
            ->limit(2)
            ->get();

        return $matches->count() === 1 ? $matches->first() : null;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isStaff();
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ADMIN || (bool) $this->is_admin;
    }

    public function isStaff(): bool
    {
        return $this->isAdmin() || $this->role === self::MANAGER;
    }

    /**
     * A courier is not staff and never becomes one by accident: the panel, the
     * phone admin and every resource in them ask `isStaff()`, so adding the
     * role opens nothing. What it opens is the one screen written for him.
     */
    public function isCourier(): bool
    {
        return $this->role === self::COURIER && ! $this->isAdmin();
    }

    /** Everyone the owner can hand a delivery to. */
    public static function couriers(): Collection
    {
        return static::query()->where('role', self::COURIER)->orderBy('name')->get();
    }

    /** The orders handed to this courier. */
    public function deliveries(): HasMany
    {
        return $this->hasMany(Order::class, 'courier_id');
    }

    public function positions(): HasMany
    {
        return $this->hasMany(CourierPosition::class);
    }

    /**
     * Whether his phone is reporting where he is. He turns it on himself when
     * he sets off and it runs out on its own, so nobody is followed quietly:
     * this is the same answer his own screen shows him.
     */
    public function isSharing(): bool
    {
        return $this->sharing_until !== null && $this->sharing_until->isFuture();
    }

    public function lastPosition(): ?CourierPosition
    {
        return $this->positions()->latest('created_at')->latest('id')->first();
    }

    /** Staff with a share of the profit, as the admin handed it out. */
    public static function shareholders(): Collection
    {
        return static::query()->where('profit_percent', '>', 0)->orderByDesc('profit_percent')->orderBy('name')->get();
    }

    public function roleLabel(): string
    {
        return self::ROLES[$this->role] ?? $this->role;
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'is_admin',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            // While this stands in the future, his phone may report its place.
            'sharing_until' => 'datetime',
            // Set by the admin with the role, never from a form the user fills in.
            'profit_percent' => 'float',
        ];
    }
}
