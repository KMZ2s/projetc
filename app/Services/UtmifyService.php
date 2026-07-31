<?php

namespace App\Services;

use App\Models\Order;
use App\Models\TrackingIntegration;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

class UtmifyService
{
    private const CURRENCIES = [
        'BRL', 'USD', 'EUR', 'GBP', 'ARS', 'CAD', 'COP', 'MXN', 'PYG',
        'CLP', 'PEN', 'PLN', 'UAH', 'CHF', 'THB', 'AUD', 'BOB',
    ];

    public function __construct(
        private readonly TrackingManager $tracking,
    ) {}

    /**
     * Send one complete order state to UTMify's official Orders endpoint.
     */
    public function send(
        TrackingIntegration $integration,
        Order $order,
        string $event,
        string $eventId,
    ): array {
        $token = $integration->access_token;

        if (! is_string($token) || trim($token) === '') {
            throw new InvalidArgumentException('UTMify API token is not configured.');
        }

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->withHeaders(['x-api-token' => $token])
                ->connectTimeout((int) config('tracking.http.connect_timeout_seconds', 3))
                ->timeout((int) config('tracking.http.timeout_seconds', 8))
                ->retry(
                    (int) config('tracking.http.attempts', 3),
                    (int) config('tracking.http.retry_delay_milliseconds', 250),
                    fn (Throwable $exception): bool => $this->shouldRetry($exception),
                    throw: false,
                )
                ->post(
                    (string) config('tracking.utmify.endpoint'),
                    $this->payload($integration, $order, $event),
                );
        } catch (ConnectionException $exception) {
            return [
                'success' => false,
                'http_status' => null,
                'error' => 'UTMify connection failed: '.$exception->getMessage(),
            ];
        }

        $body = $response->json();
        $accepted = $response->successful()
            && (! is_array($body) || ($body['OK'] ?? true) !== false);

        return [
            'success' => $accepted,
            'http_status' => $response->status(),
            'response' => is_array($body) ? $body : null,
            'error' => $accepted ? null : $this->responseError($body),
        ];
    }

    public function payload(
        TrackingIntegration $integration,
        Order $order,
        string $event,
    ): array {
        $order->loadMissing(['items', 'shippingAddress', 'user', 'device']);

        $status = $this->statusForEvent($event, $order);
        $attribution = $this->attribution($order);
        $customer = $order->customerPayload();
        $totalInCents = $this->moneyToCents($order->total);
        $gatewayFeeInCents = min(
            $totalInCents,
            $this->gatewayFeeInCents($order),
        );
        $currency = strtoupper((string) ($order->currency ?: 'BRL'));
        $customerPayload = [
            'name' => $this->nullableString($customer['name'] ?? null) ?? 'Cliente',
            'email' => $this->nullableString($customer['email'] ?? null) ?? '',
            'phone' => $this->digitsOrNull($customer['phone'] ?? null),
            'document' => $this->digitsOrNull($customer['cpf_cnpj'] ?? null),
        ];
        $country = $this->countryCode($order->shippingAddress?->country);
        $ipAddress = $this->nullableString(
            $attribution['client_ip_address']
                ?? $attribution['ip_address']
                ?? $attribution['ip']
                ?? $order->device?->ip_address
                ?? null,
        );

        if ($country !== null) {
            $customerPayload['country'] = $country;
        }

        if ($ipAddress !== null) {
            $customerPayload['ip'] = $ipAddress;
        }

        $payload = [
            'orderId' => (string) $order->order_number,
            'platform' => $this->platformName($integration),
            'paymentMethod' => $this->paymentMethod($order->payment_method),
            'status' => $status,
            'createdAt' => $this->utcDate($order->placed_at ?? $order->created_at ?? now()),
            'approvedDate' => in_array($status, ['paid', 'refunded', 'chargedback'], true)
                ? $this->utcDate($this->paidAt($order))
                : null,
            'refundedAt' => in_array($status, ['refunded', 'chargedback'], true)
                ? $this->utcDate($this->refundedAt($order))
                : null,
            'customer' => $customerPayload,
            'products' => $order->items
                ->map(fn ($item): array => [
                    'id' => (string) ($item->product_id ?? "order-item-{$item->id}"),
                    'name' => (string) $item->product_name,
                    'planId' => $item->variant_id !== null
                        ? (string) $item->variant_id
                        : null,
                    'planName' => $this->nullableString($item->variant_sku),
                    'quantity' => max(1, (int) $item->quantity),
                    'priceInCents' => $this->moneyToCents($item->unit_price),
                ])
                ->values()
                ->all(),
            'trackingParameters' => [
                'src' => $this->nullableString($attribution['src'] ?? null),
                'sck' => $this->nullableString($attribution['sck'] ?? null),
                'utm_source' => $this->nullableString($attribution['utm_source'] ?? null),
                'utm_campaign' => $this->nullableString($attribution['utm_campaign'] ?? null),
                'utm_medium' => $this->nullableString($attribution['utm_medium'] ?? null),
                'utm_content' => $this->nullableString($attribution['utm_content'] ?? null),
                'utm_term' => $this->nullableString($attribution['utm_term'] ?? null),
            ],
            'commission' => [
                'totalPriceInCents' => $totalInCents,
                'gatewayFeeInCents' => $gatewayFeeInCents,
                'userCommissionInCents' => max(0, $totalInCents - $gatewayFeeInCents),
            ],
        ];

        if (in_array($currency, self::CURRENCIES, true)) {
            $payload['commission']['currency'] = $currency;
        }

        if ((bool) (($integration->settings ?? [])['test_mode'] ?? false)) {
            $payload['isTest'] = true;
        }

        return $payload;
    }

    private function statusForEvent(string $event, Order $order): string
    {
        return match (Str::snake($event)) {
            'purchase' => 'paid',
            'payment_refused', 'payment_failed' => 'refused',
            'refund', 'refunded' => 'refunded',
            'chargeback', 'chargedback' => 'chargedback',
            'pix_generated', 'order_created' => 'waiting_payment',
            default => match ((string) $order->payment_status) {
                'paid' => 'paid',
                'failed', 'refused' => 'refused',
                'refunded' => 'refunded',
                'chargeback', 'chargedback' => 'chargedback',
                default => 'waiting_payment',
            },
        };
    }

    private function paymentMethod(?string $method): string
    {
        return match (strtolower((string) $method)) {
            'credit_card', 'card', 'debit_card', 'stripe', 'mercadopago',
            'blackcat', 'pagarme', 'credit' => 'credit_card',
            'boleto', 'bank_slip' => 'boleto',
            'pix' => 'pix',
            'paypal' => 'paypal',
            'free', 'free_price' => 'free_price',
            default => throw new InvalidArgumentException('Unsupported UTMify payment method.'),
        };
    }

    private function attribution(Order $order): array
    {
        $context = $this->tracking->trackingContext($order);
        $attribution = $context['attribution'] ?? [];
        $attribution = is_array($attribution) ? $attribution : [];
        $legacy = $order->utm_data;
        $legacy = is_array($legacy) ? $legacy : [];

        return array_replace($legacy, $attribution);
    }

    private function platformName(TrackingIntegration $integration): string
    {
        $configured = (string) (($integration->settings ?? [])['platform_name']
            ?? ($integration->settings ?? [])['platform']
            ?? config('tracking.utmify.default_platform', 'EmporioCacau'));
        $normalized = Str::studly(
            preg_replace('/[^A-Za-z0-9]+/', ' ', Str::ascii($configured, 'pt')) ?: '',
        );

        return $normalized !== '' ? Str::limit($normalized, 80, '') : 'EmporioCacau';
    }

    private function paidAt(Order $order): mixed
    {
        $payment = $order->payment_data ?? [];

        return $payment['paid_at']
            ?? $payment['approved_at']
            ?? $order->trackingDeliveries()
                ->where('event_name', 'purchase')
                ->whereNotNull('sent_at')
                ->oldest('sent_at')
                ->value('sent_at')
            ?? $order->updated_at
            ?? $order->placed_at
            ?? $order->created_at
            ?? now();
    }

    private function refundedAt(Order $order): mixed
    {
        $payment = $order->payment_data ?? [];

        return $payment['refunded_at']
            ?? $payment['chargeback_at']
            ?? $order->updated_at
            ?? now();
    }

    private function gatewayFeeInCents(Order $order): int
    {
        $payment = $order->payment_data ?? [];

        foreach (['gateway_fee_in_cents', 'gatewayFeeInCents', 'fee_in_cents'] as $key) {
            if (isset($payment[$key]) && is_numeric($payment[$key])) {
                return max(0, (int) round((float) $payment[$key]));
            }
        }

        foreach (['gateway_fee', 'gatewayFee', 'fee'] as $key) {
            if (isset($payment[$key]) && is_numeric($payment[$key])) {
                return $this->moneyToCents($payment[$key]);
            }
        }

        return 0;
    }

    private function moneyToCents(mixed $value): int
    {
        return max(0, (int) round(((float) $value) * 100));
    }

    private function utcDate(mixed $value): string
    {
        return CarbonImmutable::parse($value)
            ->utc()
            ->format('Y-m-d H:i:s');
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value !== '' ? Str::limit($value, 255, '') : null;
    }

    private function digitsOrNull(mixed $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);

        return $digits !== '' ? $digits : null;
    }

    private function countryCode(mixed $country): ?string
    {
        $country = Str::upper(trim((string) $country));

        if (in_array($country, ['BR', 'BRAZIL', 'BRASIL'], true)) {
            return 'BR';
        }

        return preg_match('/^[A-Z]{2}$/', $country) === 1 ? $country : null;
    }

    private function shouldRetry(Throwable $exception): bool
    {
        if ($exception instanceof ConnectionException) {
            return true;
        }

        if ($exception instanceof RequestException) {
            $status = $exception->response->status();

            return $status === 429 || $status >= 500;
        }

        return false;
    }

    private function responseError(mixed $body): string
    {
        if (is_array($body)) {
            $error = $body['error'] ?? null;
            $message = $body['message']
                ?? (is_array($error) ? ($error['message'] ?? null) : $error)
                ?? $body['result']
                ?? null;

            if (is_scalar($message)) {
                return Str::limit((string) $message, 1000, '');
            }
        }

        return 'UTMify rejected the event.';
    }
}
