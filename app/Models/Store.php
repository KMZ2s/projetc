<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Store extends Model
{
    use HasFactory;

    /**
     * Chave de cache do estado de manutenção.
     * Invalidada via booted() quando os campos mudam.
     */
    public const MAINTENANCE_CACHE_KEY = 'store.maintenance_state';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'address',
        'currency',
        'currency_symbol',
        'tax_rate',
        'timezone',
        'active_theme',
        'logo',
        'favicon',
        'meta_title',
        'meta_description',
        'maintenance_mode',
        'maintenance_message',
    ];

    protected $casts = [
        'tax_rate'         => 'decimal:2',
        'maintenance_mode' => 'boolean',
    ];

    /**
     * Invalida o cache de manutenção quando o toggle ou a mensagem mudam.
     */
    protected static function booted(): void
    {
        static::saved(function (self $store) {
            if ($store->wasChanged(['maintenance_mode', 'maintenance_message'])) {
                Cache::forget(self::MAINTENANCE_CACHE_KEY);
            }
        });

        static::deleted(function () {
            Cache::forget(self::MAINTENANCE_CACHE_KEY);
        });
    }

    /**
     * Retorna o singleton da loja, criando-o se necessário.
     *
     * O Replicantfy é single-store por design — cada instalação serve
     * exatamente uma loja. Use sempre este método em vez de
     * `Store::first()` para garantir que o registro existe e que o
     * código se mantém alinhado com o modelo de produto.
     *
     * Espelha o padrão de `CheckoutSetting::current()`.
     */
    public static function current(): self
    {
        return static::query()->oldest('id')->first()
            ?? static::create([
                'name'  => config('app.name', 'Replicantfy'),
                'email' => config('mail.from.address', 'contato@example.com'),
            ]);
    }

    /**
     * Estado consolidado de manutenção (cache 1h).
     * Usado pelo MaintenanceMode middleware e pela view 503.
     *
     * @return array{on: bool, message: ?string, store_name: string}
     */
    public static function maintenanceState(): array
    {
        return Cache::remember(self::MAINTENANCE_CACHE_KEY, now()->addHour(), function () {
            $store = self::current();

            return [
                'on'         => (bool) $store->maintenance_mode,
                'message'    => $store->maintenance_message,
                'store_name' => $store->name,
            ];
        });
    }

    /**
     * Atalho — true se a loja está em manutenção.
     */
    public static function isInMaintenance(): bool
    {
        return self::maintenanceState()['on'];
    }
}