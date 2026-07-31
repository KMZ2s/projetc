<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkout_settings', function (Blueprint $table) {
            $table->id();

            // Multi-store ready (singleton hoje, por loja amanhã)
            $table->foreignId('store_id')->nullable()->constrained()->nullOnDelete();

            // -----------------------------------------------------------------
            // Métodos de pagamento
            // -----------------------------------------------------------------
            $table->boolean('credit_card_enabled')->default(false);
            $table->boolean('pix_enabled')->default(true);

            // -----------------------------------------------------------------
            // Cartão de crédito
            // -----------------------------------------------------------------
            $table->unsignedTinyInteger('installments_max')->default(12);
            $table->unsignedTinyInteger('installments_no_interest_max')->default(12);

            // -----------------------------------------------------------------
            // PIX
            // -----------------------------------------------------------------
            $table->unsignedTinyInteger('pix_discount_percent')->default(0);
            $table->unsignedSmallInteger('pix_expires_minutes')->default(10);

            // -----------------------------------------------------------------
            // Urgência (timer no header do checkout)
            // -----------------------------------------------------------------
            $table->boolean('urgency_timer_enabled')->default(false);
            $table->unsignedTinyInteger('urgency_timer_minutes')->default(8);
            $table->string('urgency_message', 200)
                ->default('Despachamos seu pedido ainda hoje!');

            // -----------------------------------------------------------------
            // Downsell (cartão recusado → oferece PIX com desconto extra)
            // -----------------------------------------------------------------
            $table->boolean('downsell_enabled')->default(false);
            $table->unsignedTinyInteger('downsell_pix_discount_percent')->default(10);
            $table->string('downsell_title', 200)
                ->default('Seu cartão de crédito foi recusado.');
            $table->text('downsell_subtitle')
                ->default('Que tal finalizar seu pedido com o pix? É um método rápido, prático e seguro. Geralmente, sua compra é finalizada em poucos instantes.');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkout_settings');
    }
};
