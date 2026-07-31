<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;

#[Fillable([
    'name',
    'first_name',
    'last_name',
    'email',
    'password',
    'phone',
    'cpf_cnpj',
    'status',
    'accepts_marketing',
    'notes',
    'last_login',
    'is_admin',
])]
#[Hidden([
    'password',
    'two_factor_secret',
    'two_factor_recovery_codes',
    'remember_token',
])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'last_login'        => 'datetime',
            'accepts_marketing' => 'boolean',
            'is_admin'          => 'boolean',
        ];
    }

    // -------------------------------------------------------------------------
    // Boot — Abordagem B: sincroniza name quando first/last são definidos
    // -------------------------------------------------------------------------

    protected static function booted(): void
    {
        static::saving(function (User $user) {
            if ($user->first_name || $user->last_name) {
                $user->name = trim($user->first_name . ' ' . $user->last_name);
            }
        });
    }

    // -------------------------------------------------------------------------
    // Accessors
    // -------------------------------------------------------------------------

    /**
     * Nome de exibição — sempre consistente.
     * Usa first+last se disponíveis, caso contrário usa name diretamente.
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->first_name) {
            return trim($this->first_name . ' ' . $this->last_name);
        }

        return $this->name;
    }

    /**
     * Iniciais para avatares.
     */
    public function initials(): string
    {
        $name = $this->display_name;

        return Str::of($name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }

    // -------------------------------------------------------------------------
    // Relacionamentos
    // -------------------------------------------------------------------------

    public function addresses()
    {
        return $this->hasMany(Address::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function cards()
    {
        return $this->hasMany(Card::class);
    }
    
    public function couponUsages()
    {
        return $this->hasMany(CouponUsage::class);
    }

    public function devices()
    {
        return $this->hasMany(CustomerDevice::class);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function isAdmin(): bool
    {
        return (bool) $this->is_admin;
    }

    public function defaultAddress()
    {
        return $this->addresses()->where('is_default', true)->first()
            ?? $this->addresses()->latest()->first();
    }
}