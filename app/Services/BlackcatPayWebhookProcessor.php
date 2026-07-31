<?php

namespace App\Services;

use App\Models\Order;
use App\Models\WebhookEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Processa webhooks da BlackcatPay.
 *
 * Responsável por:
 *  - Validar idempotência (não processar o mesmo evento 2x)
 *  - Re-consultar status no gateway (source of truth — webhook é só um trigger)
 *  - Aplicar mudanças no Order com lock pessimista
 *  - Registrar auditoria completa
 */
class BlackcatPayWebhookProcessor
{
    public function __construct(
        private readonly BlackcatPayService $gateway,
    ) {}

    /**
     * Ponto de entrada. Sempre retorna sem lançar exceção —
     * falhas são registradas no WebhookEvent.
     */
    public function process(WebhookEvent $event): void
    {
        try {
            match ($event->event_type) {
                'transaction.paid'    => $this->handlePaid($event),
                'transaction.failed'  => $this->handleFailed($event),
                'transaction.created' => $this->handleCreated($event),
                default               => $event->markAsIgnored("Evento '{$event->event_type}' não tratado."),
            };
        } catch (\Throwable $e) {
            Log::error('Webhook processing failed', [
                'webhook_event_id' => $event->id,
                'error'            => $e->getMessage(),
                'trace'            => $e->getTraceAsString(),
            ]);
            $event->markAsFailed($e->getMessage());
        }
    }

    // -------------------------------------------------------------------------
    // Handlers
    // -------------------------------------------------------------------------

    private function handlePaid(WebhookEvent $event): void
    {
        $order = $this->findOrder($event);
        if (!$order) {
            $event->markAsIgnored('Pedido não encontrado.');
            return;
        }

        // Source of truth: confirmar status no gateway antes de marcar como pago.
        // Se o webhook foi forjado, esta consulta vai retornar status real.
        $confirmed = $this->confirmStatusInGateway($event->transaction_id, 'PAID');
        if (!$confirmed) {
            $event->markAsFailed('Status PAID não confirmado pelo gateway na re-consulta.');
            Log::warning('Webhook claimed PAID but gateway did not confirm', [
                'order_number'   => $order->order_number,
                'transaction_id' => $event->transaction_id,
            ]);
            return;
        }

        // Lock pessimista para evitar race condition em retries simultâneos
        DB::transaction(function () use ($order, $event) {
            $fresh = Order::lockForUpdate()->find($order->id);

            // Se outro processo já marcou como pago, não fazemos nada
            if ($fresh->payment_status === 'paid') {
                $event->markAsIgnored('Pedido já estava pago (race condition evitada).');
                return;
            }

            $payload     = $event->payload;
            $paymentData = array_merge($fresh->payment_data ?? [], [
                'status'                  => 'PAID',
                'paid_at'                 => $payload['paidAt'] ?? now()->toIso8601String(),
                'acquirer_transaction_id' => $payload['acquirerTransactionId'] ?? null,
                'end_to_end_id'           => $payload['endToEndId'] ?? null,
            ]);

            $fresh->update([
                'payment_status' => 'paid',
                'status'         => 'processing',
                'payment_data'   => $paymentData,
            ]);

            $event->markAsProcessed("Pedido {$fresh->order_number} marcado como pago.");

            // TODO Fase B4: disparar OrderPaid mailable
            // Mail::to($fresh->user)->queue(new OrderPaid($fresh));
        });
    }

    private function handleFailed(WebhookEvent $event): void
    {
        $order = $this->findOrder($event);
        if (!$order) {
            $event->markAsIgnored('Pedido não encontrado.');
            return;
        }

        DB::transaction(function () use ($order, $event) {
            $fresh = Order::lockForUpdate()->find($order->id);

            if (in_array($fresh->payment_status, ['paid', 'failed'], true)) {
                $event->markAsIgnored("Pedido em estado terminal ({$fresh->payment_status}).");
                return;
            }

            $payload     = $event->payload;
            $paymentData = array_merge($fresh->payment_data ?? [], [
                'status' => 'CANCELLED',
                'reason' => $payload['reason'] ?? 'Transação cancelada ou expirada',
            ]);

            $fresh->update([
                'payment_status' => 'failed',
                'status'         => 'cancelled',
                'payment_data'   => $paymentData,
            ]);

            $event->markAsProcessed("Pedido {$fresh->order_number} marcado como falho.");
        });
    }

    private function handleCreated(WebhookEvent $event): void
    {
        // 'transaction.created' é apenas confirmação — pedido já foi criado no checkout.
        // Marcamos como processado para auditoria sem ação adicional.
        $event->markAsProcessed('Confirmação de criação registrada.');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function findOrder(WebhookEvent $event): ?Order
    {
        // Prioridade 1: pelo external_reference (order_number)
        if ($event->external_reference) {
            $order = Order::where('order_number', $event->external_reference)->first();
            if ($order) return $order;
        }

        // Prioridade 2: pelo transaction_id já salvo no Order
        if ($event->transaction_id) {
            $order = Order::where('blackcat_transaction_id', $event->transaction_id)->first();
            if ($order) return $order;
        }

        return null;
    }

    /**
     * Re-consulta o gateway para confirmar o status real da transação.
     * Esta é a defesa principal contra webhooks forjados.
     */
    private function confirmStatusInGateway(?string $transactionId, string $expectedStatus): bool
    {
        if (!$transactionId) {
            return false;
        }

        $response = $this->gateway->getStatus($transactionId);

        $actualStatus = $response['data']['status'] ?? null;

        return $actualStatus === $expectedStatus;
    }
}