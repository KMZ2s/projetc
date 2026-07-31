<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

class TrackingIntegration extends Model
{
    public const PROVIDERS = [
        'meta' => 'Meta / Facebook',
        'tiktok' => 'TikTok',
        'ga4' => 'Google Analytics 4',
        'google_ads' => 'Google Ads',
        'utmify' => 'UTMify',
    ];

    public const DEFAULT_EVENTS = [
        'page_view' => true,
        'view_content' => true,
        'add_to_cart' => true,
        'initiate_checkout' => true,
        'add_payment_info' => true,
        'purchase' => true,
        'pix_generated' => false,
    ];

    protected $fillable = [
        'name',
        'provider',
        'public_id',
        'access_token',
        'is_active',
        'browser_enabled',
        'server_enabled',
        'events',
        'scope_mode',
        'product_ids',
        'settings',
        'position',
        'last_tested_at',
        'last_test_status',
    ];

    protected $hidden = [
        'access_token',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'browser_enabled' => 'boolean',
        'server_enabled' => 'boolean',
        'events' => 'array',
        'product_ids' => 'array',
        'settings' => 'array',
        'position' => 'integer',
        'last_tested_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        $flush = static fn () => Cache::forget('tracking_integrations.public');

        static::saved($flush);
        static::deleted($flush);
    }

    public function deliveries()
    {
        return $this->hasMany(TrackingEventDelivery::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function enabledEvents(): array
    {
        return array_replace(self::DEFAULT_EVENTS, $this->events ?? []);
    }

    public function eventEnabled(string $event): bool
    {
        return (bool) ($this->enabledEvents()[$event] ?? false);
    }

    public function appliesToProductIds(array $productIds): bool
    {
        $configured = array_map('intval', $this->product_ids ?? []);
        $current = array_map('intval', $productIds);
        $matches = array_intersect($configured, $current) !== [];

        return match ($this->scope_mode) {
            'include' => $matches,
            'exclude' => ! $matches,
            default => true,
        };
    }

    public function providerLabel(): string
    {
        return self::PROVIDERS[$this->provider] ?? $this->provider;
    }

    protected function accessToken(): Attribute
    {
        return Attribute::make(
            get: function (?string $value): ?string {
                if ($value === null || $value === '') {
                    return null;
                }

                try {
                    return Crypt::decryptString($value);
                } catch (DecryptException) {
                    return $value;
                }
            },
            set: function (?string $value): ?string {
                if ($value === null || $value === '') {
                    return null;
                }

                return Crypt::encryptString($value);
            },
        );
    }
}
