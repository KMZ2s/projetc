<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class PaymentGateway extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'is_active',
        'api_key',
        'additional_settings',
        'position',
    ];

    protected $casts = [
        'is_active'           => 'boolean',
        'additional_settings' => 'array',
        'position'            => 'integer',
    ];

    /**
     * api_key é tratada como sensível e nunca aparece em toArray()/toJson().
     * Quem precisa dela acessa explicitamente via $gateway->api_key, o que
     * dispara o accessor de descriptografia.
     */
    protected $hidden = [
        'api_key',
    ];

    // -------------------------------------------------------------------------
    // Criptografia da api_key
    // -------------------------------------------------------------------------
    //
    // Defensivo: o accessor tenta decriptar, mas se o valor no banco estiver
    // em texto puro (instalação legada antes desta feature), retorna como
    // está. No próximo save, o mutator encripta — migração lazy sem precisar
    // de comando de console.
    //
    // Cuidado: o BlackcatPayService cacheia o valor JÁ DECRIPTADO via
    // Cache::remember por 1h. Isso é um trade-off consciente entre
    // performance e exposição em storage de cache. Se a política de
    // segurança da empresa exigir, basta remover o Cache::remember do
    // service — o accessor é barato (~0.5ms).
    //
    // -------------------------------------------------------------------------

    protected function apiKey(): Attribute
    {
        return Attribute::make(
            get: function (?string $value): ?string {
                if ($value === null || $value === '') {
                    return $value;
                }

                try {
                    return Crypt::decryptString($value);
                } catch (DecryptException $e) {
                    // Valor legacy em texto puro — devolve como está.
                    // Será re-encriptado no próximo set.
                    return $value;
                }
            },
            set: function (?string $value): ?string {
                if ($value === null || $value === '') {
                    return $value;
                }

                return Crypt::encryptString($value);
            },
        );
    }
}