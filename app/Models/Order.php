<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'customer_name',
        'customer_email',
        'customer_phone',
        'customer_document',
        'order_number',
        'status',
        'payment_status',
        'fulfillment_status',
        'subtotal',
        'discount_total',
        'shipping_total',
        'tax_total',
        'total',
        'currency',
        'payment_method',
        'transaction_id',
        'blackcat_transaction_id',
        'payment_data',
        'shipping_method',
        'shipping_address_id',
        'billing_address_id',
        'coupon_id',
        'customer_note',
        'admin_note',
        'placed_at',
        'utm_data',
        'tracking_context',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'shipping_total' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'total' => 'decimal:2',
        'placed_at' => 'datetime',
        'payment_data' => 'array',
        // utm_data é gravado como JSON pelo controller. Sem este cast,
        // o Eloquent devolveria string em vez de array, forçando
        // json_decode() em todo callsite (DataExportService, relatórios,
        // exibição admin). Com o cast, vira array PHP transparentemente.
        'utm_data' => 'array',
        'tracking_context' => 'array',
    ];

    // -------------------------------------------------------------------------
    // Relacionamentos
    // -------------------------------------------------------------------------

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function shippingAddress()
    {
        return $this->belongsTo(Address::class, 'shipping_address_id');
    }

    public function billingAddress()
    {
        return $this->belongsTo(Address::class, 'billing_address_id');
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Cupom aplicado diretamente no pedido.
     */
    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

    /**
     * Registro de uso do cupom (para rastreamento por usuário).
     */
    public function couponUsage()
    {
        return $this->hasOne(CouponUsage::class);
    }

    public function device()
    {
        return $this->hasOne(CustomerDevice::class);
    }

    public function trackingDeliveries()
    {
        return $this->hasMany(TrackingEventDelivery::class);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function isPending3ds(): bool
    {
        return ($this->payment_data['status'] ?? '') === 'PENDING_3DS';
    }

    public function isPixPending(): bool
    {
        return $this->payment_method === 'pix'
            && $this->payment_status === 'pending';
    }

    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }

    public function isCancelled(): bool
    {
        return in_array($this->status, ['cancelled', 'failed']);
    }

    /**
     * Dados imutáveis do comprador no momento do pedido.
     *
     * Pedidos antigos e compras autenticadas continuam compatíveis pelo
     * fallback para o relacionamento User.
     */
    public function customerPayload(): array
    {
        return [
            'name' => $this->customer_name ?? $this->user?->display_name ?? 'Cliente',
            'email' => $this->customer_email ?? $this->user?->email ?? '',
            'phone' => $this->customer_phone ?? $this->user?->phone ?? '',
            'cpf_cnpj' => $this->customer_document ?? $this->user?->cpf_cnpj ?? '',
        ];
    }
}
