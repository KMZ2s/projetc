<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Database\Eloquent\Factories\HasFactory;
class Card extends Model
{
    use HasFactory;
    protected $table = 'cards';

    protected $fillable = [
        'user_id',
        'number',
        'holder_name',
        'expiry_month',
        'expiry_year',
        'cvv',
        'cpf_cnpj',
    ];

    // =====================
    // Criptografia automática
    // =====================
    public function setNumberAttribute($value): void
    {
        $this->attributes['number'] = Crypt::encryptString($value);
    }

    public function getNumberAttribute($value): ?string
    {
        if (empty($value)) return null;
        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return null;
        }
    }

    public function setCvvAttribute($value): void
    {
        $this->attributes['cvv'] = Crypt::encryptString($value);
    }

    public function getCvvAttribute($value): ?string
    {
        if (empty($value)) return null;
        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return null;
        }
    }

    // =====================
    // Relacionamento
    // =====================
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}