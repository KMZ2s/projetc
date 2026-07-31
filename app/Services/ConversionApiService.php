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

class ConversionApiService
{
    public function __construct(
        private readonly TrackingManager $tracking,
    ) {}

    public function sendMeta(
        TrackingIntegration $integration,
        Order $order,
        string $eventId,
    ): array {
        $token = $this->requiredToken($integration, 'Meta');
        $pixelId = $this->requiredPublicId($integration, 'Meta');
        $version = (string) config('tracking.meta.graph_version', 'v25.0');

        if (preg_match('/^v\d+\.\d+$/', $version) !== 1) {
            throw new InvalidArgumentException('Invalid fixed Meta Graph API version.');
        }

        $endpoint = sprintf(
            '%s/%s/%s/events',
            rtrim((string) config('tracking.meta.base_url'), '/'),
            $version,
            rawurlencode($pixelId),
        );

        try {
            $response = $this->request()
                ->withToken($token)
                ->post($endpoint, $this->metaPayload($integration, $order, $eventId));
        } catch (ConnectionException $exception) {
            return $this->connectionFailure('Meta', $exception);
        }

        $body = $response->json();
        $accepted = $response->successful()
            && (! is_array($body) || ! isset($body['error']));

        return [
            'success' => $accepted,
            'http_status' => $response->status(),
            'response' => is_array($body) ? $body : null,
            'error' => $accepted ? null : $this->responseError('Meta', $body),
        ];
    }

    public function sendTikTok(
        TrackingIntegration $integration,
        Order $order,
        string $eventId,
    ): array {
        $token = $this->requiredToken($integration, 'TikTok');
        $this->requiredPublicId($integration, 'TikTok');

        try {
            $response = $this->request()
                ->withHeaders(['Access-Token' => $token])
                ->post(
                    (string) config('tracking.tiktok.endpoint'),
                    $this->tiktokPayload($integration, $order, $eventId),
                );
        } catch (ConnectionException $exception) {
            return $this->connectionFailure('TikTok', $exception);
        }

        $body = $response->json();
        $accepted = $response->successful()
            && (! is_array($body) || ! array_key_exists('code', $body) || (int) $body['code'] === 0);

        return [
            'success' => $accepted,
            'http_status' => $response->status(),
            'response' => is_array($body) ? $body : null,
            'error' => $accepted ? null : $this->responseError('TikTok', $body),
        ];
    }

    public function metaPayload(
        TrackingIntegration $integration,
        Order $order,
        string $eventId,
    ): array {
        $order->loadMissing(['items', 'shippingAddress', 'user', 'device']);
        $attribution = $this->attribution($order);
        $event = [
            'event_name' => 'Purchase',
            'event_time' => $this->eventTimestamp($order),
            'event_id' => $eventId,
            'action_source' => 'website',
            'event_source_url' => $this->sourceUrl($order, $attribution),
            'user_data' => $this->metaUserData($order, $attribution),
            'custom_data' => [
                'currency' => strtoupper((string) ($order->currency ?: 'BRL')),
                'value' => round((float) $order->total, 2),
                'order_id' => (string) $order->order_number,
                'content_type' => 'product',
                'content_ids' => $order->items
                    ->map(fn ($item): string => (string) ($item->product_id ?? "order-item-{$item->id}"))
                    ->values()
                    ->all(),
                'contents' => $order->items
                    ->map(fn ($item): array => [
                        'id' => (string) ($item->product_id ?? "order-item-{$item->id}"),
                        'quantity' => max(1, (int) $item->quantity),
                        'item_price' => round((float) $item->unit_price, 2),
                    ])
                    ->values()
                    ->all(),
            ],
        ];

        $payload = ['data' => [$event]];
        $testEventCode = $this->nullableString(
            ($integration->settings ?? [])['test_event_code'] ?? null,
        );

        if ($testEventCode !== null) {
            $payload['test_event_code'] = $testEventCode;
        }

        return $payload;
    }

    public function tiktokPayload(
        TrackingIntegration $integration,
        Order $order,
        string $eventId,
    ): array {
        $order->loadMissing(['items', 'shippingAddress', 'user', 'device']);
        $attribution = $this->attribution($order);
        $page = [
            'url' => $this->sourceUrl($order, $attribution),
        ];
        $referrer = $this->nullableString(
            $attribution['referrer']
                ?? $attribution['referrer_url']
                ?? null,
        );

        if ($referrer !== null) {
            $page['referrer'] = $referrer;
        }

        $payload = [
            'event_source' => 'web',
            'event_source_id' => $this->requiredPublicId($integration, 'TikTok'),
            'data' => [[
                'event' => 'Purchase',
                'event_time' => $this->eventTimestamp($order),
                'event_id' => $eventId,
                'user' => $this->tiktokUserData($order, $attribution),
                'page' => $page,
                'properties' => [
                    'currency' => strtoupper((string) ($order->currency ?: 'BRL')),
                    'value' => round((float) $order->total, 2),
                    'content_type' => 'product',
                    'order_id' => (string) $order->order_number,
                    'contents' => $order->items
                        ->map(fn ($item): array => [
                            'content_id' => (string) ($item->product_id ?? "order-item-{$item->id}"),
                            'content_type' => 'product',
                            'content_name' => (string) $item->product_name,
                            'quantity' => max(1, (int) $item->quantity),
                            'price' => round((float) $item->unit_price, 2),
                        ])
                        ->values()
                        ->all(),
                ],
            ]],
        ];

        $testEventCode = $this->nullableString(
            ($integration->settings ?? [])['test_event_code'] ?? null,
        );

        if ($testEventCode !== null) {
            $payload['test_event_code'] = $testEventCode;
        }

        return $payload;
    }

    private function metaUserData(Order $order, array $attribution): array
    {
        $customer = $order->customerPayload();
        [$firstName, $lastName] = $this->splitName($customer['name'] ?? '');
        $address = $order->shippingAddress;

        $data = array_filter([
            'em' => $this->hashArray($this->normalizeEmail($customer['email'] ?? null)),
            'ph' => $this->hashArray($this->normalizePhone($customer['phone'] ?? null)),
            'fn' => $this->hashArray($this->normalizeText($firstName)),
            'ln' => $this->hashArray($this->normalizeText($lastName)),
            'ct' => $this->hashArray($this->normalizeText($address?->city)),
            'st' => $this->hashArray($this->normalizeText($address?->state)),
            'zp' => $this->hashArray($this->normalizePostalCode($address?->zipcode)),
            'country' => $this->hashArray($this->normalizeCountry($address?->country)),
            'external_id' => $this->hashArray($this->externalId($order)),
            'client_ip_address' => $this->nullableString(
                $attribution['client_ip_address']
                    ?? $attribution['ip_address']
                    ?? $attribution['ip']
                    ?? $order->device?->ip_address
                    ?? null,
            ),
            'client_user_agent' => $this->nullableString(
                $attribution['client_user_agent']
                    ?? $attribution['user_agent']
                    ?? $order->device?->user_agent
                    ?? null,
            ),
            'fbp' => $this->nullableString($attribution['_fbp'] ?? $attribution['fbp'] ?? null),
            'fbc' => $this->metaFbc($order, $attribution),
        ], static fn ($value): bool => $value !== null && $value !== []);

        return $data;
    }

    private function tiktokUserData(Order $order, array $attribution): array
    {
        $customer = $order->customerPayload();

        return array_filter([
            'email' => $this->hashArray($this->normalizeEmail($customer['email'] ?? null)),
            'phone' => $this->hashArray($this->normalizePhone($customer['phone'] ?? null)),
            'external_id' => $this->hashArray($this->externalId($order)),
            'ttp' => $this->nullableString($attribution['_ttp'] ?? $attribution['ttp'] ?? null),
            'ttclid' => $this->nullableString($attribution['ttclid'] ?? null),
            'ip' => $this->nullableString(
                $attribution['client_ip_address']
                    ?? $attribution['ip_address']
                    ?? $attribution['ip']
                    ?? $order->device?->ip_address
                    ?? null,
            ),
            'user_agent' => $this->nullableString(
                $attribution['client_user_agent']
                    ?? $attribution['user_agent']
                    ?? $order->device?->user_agent
                    ?? null,
            ),
        ], static fn ($value): bool => $value !== null && $value !== []);
    }

    private function attribution(Order $order): array
    {
        $context = $this->tracking->trackingContext($order);
        $snapshot = $context['attribution'] ?? [];
        $snapshot = is_array($snapshot) ? $snapshot : [];
        $legacy = is_array($order->utm_data) ? $order->utm_data : [];

        return array_replace($legacy, $snapshot);
    }

    private function sourceUrl(Order $order, array $attribution): string
    {
        $source = $this->nullableString(
            $attribution['event_source_url']
                ?? $attribution['source_url']
                ?? $attribution['page_url']
                ?? $attribution['url']
                ?? null,
        );

        if ($source !== null && filter_var($source, FILTER_VALIDATE_URL)) {
            return $source;
        }

        return rtrim((string) config('app.url'), '/')
            .'/checkout/confirmacao/'
            .rawurlencode((string) $order->order_number);
    }

    private function eventTimestamp(Order $order): int
    {
        $payment = $order->payment_data ?? [];
        $value = $payment['paid_at']
            ?? $payment['approved_at']
            ?? $order->updated_at
            ?? now();

        return min(CarbonImmutable::parse($value)->utc()->timestamp, now()->timestamp);
    }

    private function externalId(Order $order): string
    {
        if ($order->user_id !== null) {
            return 'user:'.(string) $order->user_id;
        }

        $email = $this->normalizeEmail($order->customerPayload()['email'] ?? null);

        return $email !== null
            ? 'guest:'.$email
            : 'order:'.(string) $order->order_number;
    }

    private function splitName(mixed $name): array
    {
        $parts = preg_split('/\s+/u', trim((string) $name), 2);

        return [
            $parts[0] ?? null,
            $parts[1] ?? null,
        ];
    }

    private function hashArray(?string $value): ?array
    {
        return $value !== null ? [hash('sha256', $value)] : null;
    }

    private function normalizeEmail(mixed $value): ?string
    {
        $email = mb_strtolower(trim((string) $value));

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    private function normalizePhone(mixed $value): ?string
    {
        $phone = preg_replace('/\D+/', '', (string) $value);

        if ($phone === '') {
            return null;
        }

        if (in_array(strlen($phone), [10, 11], true)) {
            $phone = '55'.$phone;
        }

        return $phone;
    }

    private function normalizeText(mixed $value): ?string
    {
        $value = Str::ascii(mb_strtolower(trim((string) $value)), 'pt');
        $value = preg_replace('/[^a-z]/', '', $value) ?? '';

        return $value !== '' ? $value : null;
    }

    private function metaFbc(Order $order, array $attribution): ?string
    {
        $fbc = $this->nullableString(
            $attribution['_fbc']
                ?? $attribution['fbc']
                ?? null,
        );

        if ($fbc !== null) {
            return $fbc;
        }

        $fbclid = $this->nullableString($attribution['fbclid'] ?? null);

        if ($fbclid === null || preg_match('/^[^\s]{1,500}$/', $fbclid) !== 1) {
            return null;
        }

        $capturedAt = $attribution['fbclid_at'] ?? null;
        $createdAt = is_numeric($capturedAt) && (int) $capturedAt > 0
            ? (int) $capturedAt
            : CarbonImmutable::parse(
                $order->placed_at
                    ?? $order->created_at
                    ?? now(),
            )->timestamp * 1000;

        return "fb.1.{$createdAt}.{$fbclid}";
    }

    private function normalizePostalCode(mixed $value): ?string
    {
        $postalCode = mb_strtolower(preg_replace('/[\s-]+/', '', (string) $value) ?? '');

        return $postalCode !== '' ? $postalCode : null;
    }

    private function normalizeCountry(mixed $value): ?string
    {
        $country = mb_strtolower(trim((string) $value));

        return match ($country) {
            'br', 'brasil', 'brazil' => 'br',
            default => preg_match('/^[a-z]{2}$/', $country) === 1 ? $country : null,
        };
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value !== '' ? mb_substr($value, 0, 1000) : null;
    }

    private function requiredToken(TrackingIntegration $integration, string $provider): string
    {
        $token = $integration->access_token;

        if (! is_string($token) || trim($token) === '') {
            throw new InvalidArgumentException("{$provider} access token is not configured.");
        }

        return trim($token);
    }

    private function requiredPublicId(TrackingIntegration $integration, string $provider): string
    {
        $id = trim((string) $integration->public_id);

        if ($id === '' || preg_match('/^[A-Za-z0-9_-]{3,128}$/', $id) !== 1) {
            throw new InvalidArgumentException("{$provider} pixel ID is invalid.");
        }

        return $id;
    }

    private function request()
    {
        return Http::acceptJson()
            ->asJson()
            ->connectTimeout((int) config('tracking.http.connect_timeout_seconds', 3))
            ->timeout((int) config('tracking.http.timeout_seconds', 8))
            ->retry(
                (int) config('tracking.http.attempts', 3),
                (int) config('tracking.http.retry_delay_milliseconds', 250),
                fn (Throwable $exception): bool => $this->shouldRetry($exception),
                throw: false,
            );
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

    private function connectionFailure(string $provider, ConnectionException $exception): array
    {
        return [
            'success' => false,
            'http_status' => null,
            'error' => "{$provider} connection failed: ".$exception->getMessage(),
        ];
    }

    private function responseError(string $provider, mixed $body): string
    {
        if (is_array($body)) {
            $error = $body['error'] ?? null;
            $message = $body['message']
                ?? (is_array($error)
                    ? ($error['message'] ?? $error['description'] ?? null)
                    : $error)
                ?? null;

            if (is_scalar($message)) {
                return (string) $message;
            }
        }

        return "{$provider} rejected the event.";
    }
}
