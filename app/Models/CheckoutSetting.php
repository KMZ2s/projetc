<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CheckoutSetting extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'credit_card_enabled'           => 'boolean',
        'pix_enabled'                   => 'boolean',
        'urgency_timer_enabled'         => 'boolean',
        'downsell_enabled'              => 'boolean',
        'installments_max'              => 'integer',
        'installments_no_interest_max'  => 'integer',
        'pix_discount_percent'          => 'integer',
        'pix_expires_minutes'           => 'integer',
        'urgency_timer_minutes'         => 'integer',
        'downsell_pix_discount_percent' => 'integer',
    ];

    /**
     * Singleton — retorna a configuração global do checkout.
     *
     * Hoje retorna id=1 (singleton global). Quando o sistema virar multi-store
     * de fato, substituir por: static::firstOrCreate(['store_id' => $storeId]).
     */
    public static function current(): self
    {
        return static::firstOrCreate(['id' => 1], [
            'credit_card_enabled' => false,
            'pix_enabled' => true,
            'installments_max' => 12,
            'installments_no_interest_max' => 12,
            'pix_discount_percent' => 0,
            'pix_expires_minutes' => 10,
            'urgency_timer_enabled' => false,
            'urgency_timer_minutes' => 8,
            'urgency_message' => 'Despachamos seu pedido ainda hoje!',
            'downsell_enabled' => false,
            'downsell_pix_discount_percent' => 10,
            'downsell_title' => 'Seu cartão de crédito foi recusado.',
            'downsell_subtitle' => 'Que tal finalizar seu pedido com o pix? É um método rápido, prático e seguro.',
        ]);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Converte `pix_expires_minutes` para o formato aceito pela BlackcatPay.
     *
     * Limitação conhecida da API: o campo `pix.expiresInDays` aceita apenas
     * dias inteiros, com mínimo 1. Configurações abaixo de 1440 minutos (24h)
     * são todas equivalentes a 1 dia na API real.
     *
     * Roadmap: se precisarmos de expiração granular em minutos, terá que
     * trocar de gateway ou usar o endpoint de cancelamento manual.
     */
    public function pixExpiresInDays(): int
    {
        $minutes = (int) ($this->pix_expires_minutes ?? 1440);

        return max(1, (int) ceil($minutes / 1440));
    }
}
