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

    /** The roles, as the admin panel names them. */
    public const ROLES = [
        self::CUSTOMER => 'Müştəri',
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
                ['%' . substr($digits, -9)])
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
            // Set by the admin with the role, never from a form the user fills in.
            'profit_percent' => 'float',
        ];
    }
}
