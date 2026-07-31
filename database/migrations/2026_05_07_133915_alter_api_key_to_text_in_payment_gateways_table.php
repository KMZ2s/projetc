<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aumenta a coluna `api_key` em payment_gateways de VARCHAR(255) pra TEXT.
 *
 * Motivo: a partir desta migration, api_key é armazenada criptografada via
 * Crypt::encryptString (AES-256-CBC + HMAC + base64). O envelope cresce o
 * payload em ~5x: uma API key UUID de 36 caracteres vira ~280 caracteres
 * encriptados, estourando VARCHAR(255).
 *
 * Migração lazy: valores existentes em texto puro continuam funcionando
 * (o accessor no model captura DecryptException e retorna o valor cru).
 * No próximo save via admin, eles serão re-encriptados automaticamente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_gateways', function (Blueprint $table) {
            $table->text('api_key')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('payment_gateways', function (Blueprint $table) {
            $table->string('api_key')->nullable()->change();
        });
    }
};