<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\TrackingIntegration;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class TrackingManager
{
    /**
     * Settings that are safe and useful in browser-side bootstrap data.
     *
     * Access tokens and diagnostics/test credentials are deliberately absent.
     */
    private const PUBLIC_SETTING_KEYS = [
        'conversion_label',
        'google_ads_conversion_label',
        'optimization_pixel_enabled',
        'utm_script_enabled',
    ];

    public function publicConfig(): array
    {
        return $this->formatPublicConfig(
            TrackingIntegration::query()
                ->active()
                ->where('browser_enabled', true)
                ->orderBy('position')
                ->orderBy('id')
                ->get()
        );
    }

    public function publicConfigForProductIds(array $ids): array
    {
        return $this->formatPublicConfig(
            $this->browserIntegrationsForProductIds($this->normalizeIds($ids))
        );
    }

    public function browserContextForProduct(Product $product): array
    {
        $integrations = $this->browserIntegrationsForProductIds([(int) $product->getKey()])
            ->filter(fn (TrackingIntegration $integration) => $integration->eventEnabled('view_content'));

        return array_merge(
            $this->formatPublicConfig($integrations, 'view_content'),
            [
                'context_type' => 'product',
                'event' => 'view_content',
                'currency' => 'BRL',
                'value' => (float) $product->price,
                'contents' => [[
                    'id' => (string) $product->getKey(),
                    'name' => (string) $product->name,
                    'sku' => $product->sku ? (string) $product->sku : null,
                    'quantity' => 1,
                    'price' => (float) $product->price,
                ]],
            ],
        );
    }

    public function browserContextForCart(array $cart, string $event = 'page_view'): array
    {
        $items = $this->cartItems($cart);
        $productIds = $this->normalizeIds(array_column($items, 'product_id'));
        $event = $this->normalizeEventName($event);

        $integrations = $this->browserIntegrationsForProductIds($productIds)
            ->filter(fn (TrackingIntegration $integration) => $integration->eventEnabled($event));

        $contents = array_values(array_map(static fn (array $item): array => [
            'id' => (string) ($item['product_id'] ?? $item['id'] ?? ''),
            'name' => (string) ($item['name'] ?? ''),
            'sku' => isset($item['sku']) ? (string) $item['sku'] : null,
            'quantity' => max(1, (int) ($item['quantity'] ?? 1)),
            'price' => (float) ($item['price'] ?? 0),
        ], $items));

        $value = isset($cart['total'])
            ? (float) $cart['total']
            : array_reduce(
                $contents,
                static fn (float $carry, array $item): float => $carry + ($item['price'] * $item['quantity']),
                0.0,
            );

        return array_merge(
            $this->formatPublicConfig($integrations, $event),
            [
                'context_type' => 'cart',
                'event' => $event,
                'currency' => 'BRL',
                'value' => round($value, 2),
                'contents' => $contents,
            ],
        );
    }

    public function browserContextForOrder(Order $order, string $event): array
    {
        $event = $this->normalizeEventName($event);
        $order->loadMissing('items');

        $integrations = $this->browserIntegrationsForOrder($order)
            ->filter(fn (TrackingIntegration $integration) => $integration->eventEnabled($event));

        $contents = $order->items->map(static fn ($item): array => [
            'id' => (string) ($item->product_id ?? "order-item-{$item->id}"),
            'name' => (string) $item->product_name,
            'sku' => $item->variant_sku ? (string) $item->variant_sku : null,
            'quantity' => max(1, (int) $item->quantity),
            'price' => (float) $item->unit_price,
        ])->values()->all();

        return array_merge(
            $this->formatPublicConfig($integrations, $event, $order),
            [
                'context_type' => 'order',
                'event' => $event,
                'event_id' => $this->eventId($order, $event),
                'order_id' => (string) $order->order_number,
                'currency' => strtoupper((string) ($order->currency ?: 'BRL')),
                'value' => (float) $order->total,
                'contents' => $contents,
            ],
        );
    }

    public function snapshotForProductIds(array $ids): array
    {
        $productIds = $this->normalizeIds($ids);

        $integrations = TrackingIntegration::query()
            ->active()
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->filter(fn (TrackingIntegration $integration) => $integration->appliesToProductIds($productIds))
            ->values()
            ->all();
        $idsFor = static fn (array $items): array => array_values(array_map(
            static fn (TrackingIntegration $integration): int => (int) $integration->getKey(),
            $items,
        ));

        return [
            'integration_ids' => $idsFor($integrations),
            'browser_integration_ids' => $idsFor(array_values(array_filter(
                $integrations,
                static fn (TrackingIntegration $integration): bool => $integration->browser_enabled,
            ))),
            'server_integration_ids' => $idsFor(array_values(array_filter(
                $integrations,
                static fn (TrackingIntegration $integration): bool => $integration->server_enabled,
            ))),
            'product_ids' => $productIds,
        ];
    }

    public function utmifyScriptEnabled(): bool
    {
        return TrackingIntegration::query()
            ->active()
            ->where('provider', 'utmify')
            ->get()
            ->contains(function (TrackingIntegration $integration): bool {
                $settings = $integration->settings ?? [];
                $explicitlyEnabled = $settings['utm_script_enabled'] ?? true;

                return (bool) $explicitlyEnabled
                    && $integration->browser_enabled;
            });
    }

    /**
     * Resolve server integrations from the immutable order snapshot.
     *
     * When integration_ids exists, even an empty list is authoritative. Only
     * legacy orders without that key fall back to currently active records.
     */
    public function serverIntegrationsForOrder(Order $order): Collection
    {
        $context = $this->trackingContext($order);
        $snapshotKey = array_key_exists('server_integration_ids', $context)
            ? 'server_integration_ids'
            : (array_key_exists('integration_ids', $context) ? 'integration_ids' : null);
        $snapshotIds = $snapshotKey !== null
            ? $this->normalizeIds($context[$snapshotKey] ?? [])
            : [];

        if ($snapshotKey !== null && $snapshotIds === []) {
            return collect();
        }

        $query = TrackingIntegration::query()
            ->active()
            ->where('server_enabled', true)
            ->whereIn('provider', ['meta', 'tiktok', 'utmify'])
            ->orderBy('position')
            ->orderBy('id');

        if ($snapshotKey !== null) {
            return $query
                ->whereIn('id', $snapshotIds)
                ->get();
        }

        $productIds = $this->productIdsForOrder($order);

        return $query->get()
            ->filter(fn (TrackingIntegration $integration) => $integration->appliesToProductIds($productIds))
            ->values();
    }

    public function eventId(Order $order, string $event): string
    {
        $event = $this->normalizeEventName($event);

        return hash('sha256', implode(':', [
            'emporio-tracking-v1',
            (string) $order->getKey(),
            (string) $order->order_number,
            $event,
        ]));
    }

    public function providerEventName(string $provider, string $event): string
    {
        $event = $this->normalizeEventName($event);

        return match ($provider) {
            'meta' => [
                'page_view' => 'PageView',
                'view_content' => 'ViewContent',
                'add_to_cart' => 'AddToCart',
                'initiate_checkout' => 'InitiateCheckout',
                'add_payment_info' => 'AddPaymentInfo',
                'purchase' => 'Purchase',
            ][$event] ?? Str::studly($event),
            'tiktok' => [
                'page_view' => 'PageView',
                'view_content' => 'ViewContent',
                'add_to_cart' => 'AddToCart',
                'initiate_checkout' => 'InitiateCheckout',
                'add_payment_info' => 'AddPaymentInfo',
                'purchase' => 'Purchase',
            ][$event] ?? Str::studly($event),
            default => $event,
        };
    }

    public function trackingContext(Order $order): array
    {
        $context = $order->getAttribute('tracking_context');

        if (is_array($context)) {
            return $context;
        }

        if (is_string($context) && $context !== '') {
            $decoded = json_decode($context, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    public function productIdsForOrder(Order $order): array
    {
        $context = $this->trackingContext($order);

        if (array_key_exists('product_ids', $context)) {
            return $this->normalizeIds($context['product_ids'] ?? []);
        }

        $order->loadMissing('items');

        return $this->normalizeIds($order->items->pluck('product_id')->all());
    }

    private function browserIntegrationsForProductIds(array $productIds): Collection
    {
        return TrackingIntegration::query()
            ->active()
            ->where('browser_enabled', true)
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->filter(fn (TrackingIntegration $integration) => $integration->appliesToProductIds($productIds))
            ->values();
    }

    private function browserIntegrationsForOrder(Order $order): Collection
    {
        $context = $this->trackingContext($order);
        $snapshotKey = array_key_exists('browser_integration_ids', $context)
            ? 'browser_integration_ids'
            : (array_key_exists('integration_ids', $context) ? 'integration_ids' : null);

        if ($snapshotKey !== null) {
            $ids = $this->normalizeIds($context[$snapshotKey] ?? []);

            if ($ids === []) {
                return collect();
            }

            return TrackingIntegration::query()
                ->active()
                ->where('browser_enabled', true)
                ->whereIn('id', $ids)
                ->orderBy('position')
                ->orderBy('id')
                ->get();
        }

        return $this->browserIntegrationsForProductIds($this->productIdsForOrder($order));
    }

    private function formatPublicConfig(
        Collection|EloquentCollection $integrations,
        ?string $event = null,
        ?Order $order = null,
    ): array {
        return [
            'integrations' => $integrations
                ->map(function (TrackingIntegration $integration) use ($event, $order): array {
                    $config = [
                        'id' => (int) $integration->getKey(),
                        'name' => (string) $integration->name,
                        'provider' => (string) $integration->provider,
                        'public_id' => $integration->public_id
                            ? (string) $integration->public_id
                            : null,
                        'browser_enabled' => (bool) $integration->browser_enabled,
                        'scope_mode' => (string) $integration->scope_mode,
                        'product_ids' => $this->normalizeIds($integration->product_ids ?? []),
                        'events' => $integration->enabledEvents(),
                        'settings' => array_intersect_key(
                            $integration->settings ?? [],
                            array_flip(self::PUBLIC_SETTING_KEYS),
                        ),
                    ];

                    if ($event !== null) {
                        $config['provider_event'] = $this->providerEventName(
                            $integration->provider,
                            $event,
                        );
                    }

                    if ($event !== null && $order !== null) {
                        $config['event_id'] = $this->eventId($order, $event);
                    }

                    return $config;
                })
                ->values()
                ->all(),
            'utmify_script_enabled' => $this->utmifyScriptEnabled(),
        ];
    }

    private function cartItems(array $cart): array
    {
        $items = $cart['items'] ?? $cart;

        if (! is_array($items)) {
            return [];
        }

        return array_values(array_filter($items, 'is_array'));
    }

    private function normalizeIds(array $ids): array
    {
        return array_values(array_unique(array_filter(
            array_map('intval', $ids),
            static fn (int $id): bool => $id > 0,
        )));
    }

    private function normalizeEventName(string $event): string
    {
        return Str::of($event)->snake()->lower()->toString();
    }
}
