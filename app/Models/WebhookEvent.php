<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookEvent extends Model
{
    protected $fillable = [
        'source',
        'event_type',
        'transaction_id',
        'external_reference',
        'payload',
        'status',
        'ip_address',
        'user_agent',
        'result_message',
        'received_at',
        'processed_at',
    ];

    protected $casts = [
        'payload'      => 'array',
        'received_at'  => 'datetime',
        'processed_at' => 'datetime',
    ];

    /**
     * Status constants — evita strings mágicas espalhadas no código.
     */
    public const STATUS_RECEIVED  = 'received';
    public const STATUS_PROCESSED = 'processed';
    public const STATUS_IGNORED   = 'ignored';
    public const STATUS_FAILED    = 'failed';

    public function markAsProcessed(?string $message = null): void
    {
        $this->update([
            'status'         => self::STATUS_PROCESSED,
            'processed_at'   => now(),
            'result_message' => $message,
        ]);
    }

    public function markAsFailed(string $message): void
    {
        $this->update([
            'status'         => self::STATUS_FAILED,
            'processed_at'   => now(),
            'result_message' => $message,
        ]);
    }

    public function markAsIgnored(string $message): void
    {
        $this->update([
            'status'         => self::STATUS_IGNORED,
            'processed_at'   => now(),
            'result_message' => $message,
        ]);
    }
}