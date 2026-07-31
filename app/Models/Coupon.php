<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'type',
        'value',
        'min_order_value',
        'usage_limit',
        'usage_per_customer',
        'used_count',
        'valid_from',
        'valid_to',
        'status',
    ];

    protected $casts = [
        'value'              => 'decimal:2',
        'min_order_value'    => 'decimal:2',
        'usage_limit'        => 'integer',
        'usage_per_customer' => 'integer',
        'used_count'         => 'integer',
        'valid_from'         => 'date',
        'valid_to'           => 'date',
    ];

    // -------------------------------------------------------------------------
    // Relacionamentos
    // -------------------------------------------------------------------------

    /**
     * Todos os usos deste cupom.
     * Renomeado de usage() → usages() para semântica correta (hasMany).
     */
    public function usages()
    {
        return $this->hasMany(CouponUsage::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Verifica se o cupom ainda está dentro do limite de usos globais.
     */
    public function hasUsagesAvailable(): bool
    {
        if (is_null($this->usage_limit)) {
            return true;
        }

        return $this->usages()->count() < $this->usage_limit;
    }

    /**
     * Verifica se um usuário específico ainda pode usar este cupom.
     */
    public function isAvailableForUser(int $userId): bool
    {
        if (!$this->hasUsagesAvailable()) {
            return false;
        }

        if (is_null($this->usage_per_customer)) {
            return true;
        }

        $used = $this->usages()->where('user_id', $userId)->count();

        return $used < $this->usage_per_customer;
    }

    /**
     * Verifica se o cupom está válido considerando datas e status.
     */
    public function isValid(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if ($this->valid_from && now()->lt($this->valid_from)) {
            return false;
        }

        if ($this->valid_to && now()->gt($this->valid_to)) {
            return false;
        }

        return true;
    }
}