<?php

namespace App\Http\Controllers;

use App\Models\WebhookEvent;
use App\Services\BlackcatPayWebhookProcessor;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function __construct(
        private readonly BlackcatPayWebhookProcessor $processor,
    ) {}

    /**
     * POST /checkout/callback
     *
     * Recebe notificações da BlackcatPay sobre mudanças de status.
     *
     * Camadas de defesa:
     *  1. Rate limiting (configurado em routes/web.php)
     *  2. Idempotência (UNIQUE em webhook_events: source+transaction_id+event_type)
     *  3. Re-consulta ao gateway (no processor — source of truth)
     *  4. Lock pessimista no Order (no processor)
     *
     * O webhook em si NÃO é confiável — a re-consulta no gateway é a defesa
     * principal contra payloads forjados, já que a API exige X-API-Key.
     */
    public function handle(Request $request): Response
    {
        $payload = $request->all();
        $event   = $payload['event'] ?? null;

        if (!$event) {
            Log::info('Webhook sem campo event ignorado', ['payload' => $payload]);
            return response('OK', 200);
        }

        $transactionId     = $payload['transactionId'] ?? null;
        $externalReference = $payload['externalReference'] ?? $payload['externalRef'] ?? null;

        $webhookEvent = WebhookEvent::firstOrCreate(
            [
                'source'         => 'blackcatpay',
                'transaction_id' => $transactionId,
                'event_type'     => $event,
            ],
            [
                'external_reference' => $externalReference,
                'payload'            => $payload,
                'status'             => WebhookEvent::STATUS_RECEIVED,
                'ip_address'         => $request->ip(),
                'user_agent'         => $request->userAgent(),
                'received_at'        => now(),
            ]
        );

        if (!$webhookEvent->wasRecentlyCreated) {
            Log::info('Webhook duplicado ignorado (idempotência)', [
                'webhook_event_id' => $webhookEvent->id,
                'event_type'       => $event,
                'transaction_id'   => $transactionId,
            ]);
            return response('OK', 200);
        }

        $this->processor->process($webhookEvent);

        // Sempre 200 — falhas internas vão pro WebhookEvent.
        // 5xx faria o gateway reentregar e poluir o sistema.
        return response('OK', 200);
    }
}