<?php

namespace App\Services;

use App\Models\Order;
use App\Models\TrackingEventDelivery;
use App\Models\TrackingIntegration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class OrderTrackingDispatcher
{
    public function __construct(
        private readonly TrackingManager $tracking,
        private readonly UtmifyService $utmify,
        private readonly ConversionApiService $conversionApi,
    ) {}

    /**
     * Deliver an immutable order event to every eligible server integration.
     *
     * A row is claimed before the HTTP request. The unique
     * (tracking_integration_id, event_id) key provides cross-process
     * idempotency, while a stale processing lease allows manual/replayed
     * observer events to recover after a crashed request.
     */
    public function dispatch(Order $order, string $event): array
    {
        $event = $this->normalizeEvent($event);
        $order->refresh()->loadMissing(['items', 'shippingAddress', 'user']);
        $eventId = $this->tracking->eventId($order, $event);
        $results = [];

        foreach ($this->tracking->serverIntegrationsForOrder($order) as $integration) {
            if (! $this->supportsEvent($integration, $event)) {
                continue;
            }

            $delivery = TrackingEventDelivery::query()->firstOrCreate(
                [
                    'tracking_integration_id' => $integration->getKey(),
                    'event_id' => $eventId,
                ],
                [
                    'order_id' => $order->getKey(),
                    'event_name' => $event,
                    'status' => 'pending',
                    'attempts' => 0,
                ],
            );

            if (! $this->claim($delivery)) {
                $results[] = [
                    'integration_id' => (int) $integration->getKey(),
                    'provider' => $integration->provider,
                    'status' => $delivery->fresh()?->status ?? $delivery->status,
                    'skipped' => true,
                ];

                continue;
            }

            $results[] = $this->deliver(
                $integration,
                $order,
                $event,
                $eventId,
                $delivery,
            );
        }

        return $results;
    }

    private function claim(TrackingEventDelivery $delivery): bool
    {
        return DB::transaction(function () use ($delivery): bool {
            $locked = TrackingEventDelivery::query()
                ->lockForUpdate()
                ->findOrFail($delivery->getKey());

            if ($locked->status === 'sent') {
                return false;
            }

            $staleBefore = now()->subSeconds(
                (int) config('tracking.http.processing_stale_after_seconds', 120),
            );

            if ($locked->status === 'processing' && $locked->updated_at?->isAfter($staleBefore)) {
                return false;
            }

            $locked->forceFill([
                'status' => 'processing',
                'attempts' => ((int) $locked->attempts) + 1,
                'last_http_status' => null,
                'last_error' => null,
            ])->save();

            return true;
        });
    }

    private function deliver(
        TrackingIntegration $integration,
        Order $order,
        string $event,
        string $eventId,
        TrackingEventDelivery $delivery,
    ): array {
        try {
            $result = match ($integration->provider) {
                'utmify' => $this->utmify->send($integration, $order, $event, $eventId),
                'meta' => $this->conversionApi->sendMeta($integration, $order, $eventId),
                'tiktok' => $this->conversionApi->sendTikTok($integration, $order, $eventId),
                default => [
                    'success' => false,
                    'http_status' => null,
                    'error' => 'Unsupported server tracking provider.',
                ],
            };
        } catch (Throwable $exception) {
            $result = [
                'success' => false,
                'http_status' => null,
                'error' => class_basename($exception).': '.$exception->getMessage(),
            ];
        }

        $success = (bool) ($result['success'] ?? false);
        $httpStatus = isset($result['http_status'])
            ? (int) $result['http_status']
            : null;
        $error = $success
            ? null
            : $this->redact(
                (string) ($result['error'] ?? 'Tracking provider rejected the event.'),
                $integration,
                $order,
            );

        $delivery->forceFill([
            'status' => $success ? 'sent' : 'failed',
            'last_http_status' => $httpStatus,
            'last_error' => $error,
            'sent_at' => $success ? now() : null,
        ])->save();

        return [
            'integration_id' => (int) $integration->getKey(),
            'provider' => $integration->provider,
            'status' => $success ? 'sent' : 'failed',
            'http_status' => $httpStatus,
            'skipped' => false,
        ];
    }

    private function supportsEvent(TrackingIntegration $integration, string $event): bool
    {
        if (in_array($integration->provider, ['meta', 'tiktok'], true)) {
            return $event === 'purchase' && $integration->eventEnabled('purchase');
        }

        if ($integration->provider !== 'utmify') {
            return false;
        }

        $settings = $integration->settings ?? [];

        return match ($event) {
            'purchase' => $integration->eventEnabled('purchase'),
            'pix_generated', 'order_created' => $integration->eventEnabled('pix_generated'),
            'payment_refused', 'payment_failed' => (bool) ($settings['send_refused'] ?? true),
            'refund', 'refunded' => (bool) ($settings['send_refunded'] ?? true),
            'chargeback', 'chargedback' => (bool) ($settings['send_chargeback'] ?? true),
            default => false,
        };
    }

    private function redact(
        string $message,
        TrackingIntegration $integration,
        Order $order,
    ): string {
        $order->loadMissing(['user', 'shippingAddress', 'device']);
        $token = $integration->access_token;

        if (is_string($token) && $token !== '') {
            $message = str_replace($token, '[REDACTED_TOKEN]', $message);
        }

        $customer = $order->customerPayload();
        $context = $this->tracking->trackingContext($order);
        $attribution = $context['attribution'] ?? [];
        $attribution = is_array($attribution) ? $attribution : [];
        $address = $order->shippingAddress;
        $sensitiveValues = array_merge([
            $customer['name'] ?? null,
            $customer['email'] ?? null,
            $customer['phone'] ?? null,
            $customer['cpf_cnpj'] ?? null,
            $order->device?->ip_address,
            $order->device?->user_agent,
            $address?->zipcode,
            $address?->street,
            $address?->number,
            $address?->complement,
            $address?->neighborhood,
            $address?->city,
        ], array_values($attribution));

        foreach ($sensitiveValues as $sensitive) {
            if (is_scalar($sensitive) && mb_strlen((string) $sensitive) >= 3) {
                $message = str_ireplace((string) $sensitive, '[REDACTED_PII]', $message);
            }
        }

        $message = preg_replace(
            [
                '/\bBearer\s+[A-Za-z0-9._~+\/=-]+/i',
                '/\bx-api-token\s*[:=]\s*[^\s,;]+/i',
                '/\bAccess-Token\s*[:=]\s*[^\s,;]+/i',
                '/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i',
                '/(?<!\d)\+?\d[\d\s().-]{7,}\d(?!\d)/',
                '/\b(?:\d{1,3}\.){3}\d{1,3}\b/',
            ],
            [
                'Bearer [REDACTED_TOKEN]',
                'x-api-token: [REDACTED_TOKEN]',
                'Access-Token: [REDACTED_TOKEN]',
                '[REDACTED_EMAIL]',
                '[REDACTED_NUMBER]',
                '[REDACTED_IP]',
            ],
            $message,
        ) ?? 'Tracking delivery failed.';

        return Str::limit(
            $message,
            (int) config('tracking.http.max_error_length', 2000),
            '',
        );
    }

    private function normalizeEvent(string $event): string
    {
        return Str::of($event)->snake()->lower()->toString();
    }
}
