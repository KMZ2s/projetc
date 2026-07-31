<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();

            // Origem do webhook (ex: 'blackcatpay')
            $table->string('source', 50)->index();

            // Tipo do evento (ex: 'transaction.paid')
            $table->string('event_type', 60)->index();

            // ID da transação no gateway — chave para idempotência
            $table->string('transaction_id', 100)->nullable()->index();

            // order_number relacionado (denormalizado para queries rápidas)
            $table->string('external_reference', 100)->nullable()->index();

            // Payload completo recebido (auditoria)
            $table->json('payload');

            // Status do processamento
            $table->enum('status', ['received', 'processed', 'ignored', 'failed'])
                  ->default('received')
                  ->index();

            // IP de origem (auditoria de segurança)
            $table->string('ip_address', 45)->nullable();

            // User-Agent (auditoria)
            $table->string('user_agent', 500)->nullable();

            // Resultado do processamento (mensagem, erro, etc)
            $table->text('result_message')->nullable();

            // Timestamps de cada etapa
            $table->timestamp('received_at')->useCurrent();
            $table->timestamp('processed_at')->nullable();

            $table->timestamps();

            // Idempotência: mesmo gateway + mesma transação + mesmo evento = 1 vez só
            // Tipos diferentes do mesmo transaction_id são permitidos (created, paid, refunded)
            $table->unique(
                ['source', 'transaction_id', 'event_type'],
                'webhook_events_idempotency_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};