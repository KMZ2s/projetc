<?php

use App\Models\Address;
use App\Models\CustomerDevice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\TrackingEventDelivery;
use App\Models\TrackingIntegration;
use App\Models\User;
use App\Services\ConversionApiService;
use App\Services\OrderTrackingDispatcher;
use App\Services\TrackingManager;
use App\Services\UtmifyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $user = User::factory()->create([
        'name' => 'Nicolas da Silva',
        'email' => 'nicolas@example.com',
        'phone' => '(11) 99999-8888',
    ]);
    $address = Address::factory()->for($user)->create([
        'zipcode' => '01310-100',
        'city' => 'Sao Paulo',
        'state' => 'SP',
        'country' => 'BR',
    ]);
    $product = Product::factory()->create([
        'name' => 'Chocolate Especial',
        'price' => 190.80,
    ]);

    $this->order = Order::factory()->for($user)->create([
        'order_number' => 'ORDER-TRACKING-1',
        'customer_name' => 'Nicolas da Silva',
        'customer_email' => 'nicolas@example.com',
        'customer_phone' => '(11) 99999-8888',
        'customer_document' => '123.456.789-09',
        'status' => 'paid',
        'payment_status' => 'paid',
        'payment_method' => 'pix',
        'subtotal' => 190.80,
        'discount_total' => 0,
        'shipping_total' => 0,
        'tax_total' => 0,
        'total' => 190.80,
        'currency' => 'BRL',
        'shipping_address_id' => $address->id,
        'billing_address_id' => $address->id,
        'tracking_context' => [
            'attribution' => [
                'utm_source' => 'meta',
                'utm_campaign' => 'lancamento',
                'ttclid' => 'tt-click-id',
                '_ttp' => 'ttp-cookie',
                '_fbp' => 'fbp-cookie',
                '_fbc' => 'fbc-cookie',
            ],
        ],
    ]);

    OrderItem::create([
        'order_id' => $this->order->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'unit_price' => 190.80,
        'total_price' => 190.80,
        'discount' => 0,
        'product_name' => 'Chocolate Especial',
    ]);

    CustomerDevice::create([
        'user_id' => $user->id,
        'order_id' => $this->order->id,
        'ip_address' => '203.0.113.10',
        'user_agent' => 'Tracking Test Browser/1.0',
    ]);
});

it('builds the official UTMify order schema with device IP fallback', function () {
    $integration = TrackingIntegration::create([
        'name' => 'UTMify',
        'provider' => 'utmify',
        'access_token' => 'utmify-secret',
        'settings' => ['platform_name' => 'Emporio Cacau Store'],
    ]);

    $payload = app(UtmifyService::class)->payload(
        $integration,
        $this->order,
        'purchase',
    );

    expect($payload)
        ->orderId->toBe('ORDER-TRACKING-1')
        ->platform->toBe('EmporioCacauStore')
        ->paymentMethod->toBe('pix')
        ->status->toBe('paid')
        ->and($payload['customer']['ip'])->toBe('203.0.113.10')
        ->and($payload['trackingParameters']['utm_source'])->toBe('meta')
        ->and($payload['commission']['totalPriceInCents'])->toBe(19080)
        ->and($payload['products'][0])->toMatchArray([
            'name' => 'Chocolate Especial',
            'quantity' => 1,
            'priceInCents' => 19080,
        ]);
});

it('builds Meta and TikTok purchase payloads with hashed PII and device fallback', function () {
    $meta = new TrackingIntegration([
        'provider' => 'meta',
        'public_id' => '123456789',
    ]);
    $tiktok = new TrackingIntegration([
        'provider' => 'tiktok',
        'public_id' => 'TTPIXEL123',
    ]);
    $service = app(ConversionApiService::class);
    $eventId = 'stable-event-id';

    $metaPayload = $service->metaPayload($meta, $this->order, $eventId);
    $tiktokPayload = $service->tiktokPayload($tiktok, $this->order, $eventId);

    expect($metaPayload['data'][0]['event_name'])->toBe('Purchase')
        ->and($metaPayload['data'][0]['event_id'])->toBe($eventId)
        ->and($metaPayload['data'][0]['user_data']['em'])->toBe([
            hash('sha256', 'nicolas@example.com'),
        ])
        ->and($metaPayload['data'][0]['user_data']['client_ip_address'])->toBe('203.0.113.10')
        ->and($metaPayload['data'][0]['user_data']['client_user_agent'])->toBe('Tracking Test Browser/1.0')
        ->and($tiktokPayload['event_source'])->toBe('web')
        ->and($tiktokPayload['data'][0]['event'])->toBe('Purchase')
        ->and($tiktokPayload['data'][0]['user']['ip'])->toBe('203.0.113.10')
        ->and($tiktokPayload['data'][0]['user']['user_agent'])->toBe('Tracking Test Browser/1.0')
        ->and($tiktokPayload['data'][0]['user']['ttclid'])->toBe('tt-click-id');
});

it('uses the captured click time when synthesizing the Meta fbc value', function () {
    $this->order->update([
        'tracking_context' => [
            'attribution' => [
                'fbclid' => 'meta-click-id',
                'fbclid_at' => 1_722_000_000_123,
            ],
        ],
    ]);

    $meta = new TrackingIntegration([
        'provider' => 'meta',
        'public_id' => '123456789',
    ]);

    $payload = app(ConversionApiService::class)->metaPayload(
        $meta,
        $this->order->fresh(),
        'stable-event-id',
    );

    expect($payload['data'][0]['user_data']['fbc'])
        ->toBe('fb.1.1722000000123.meta-click-id');
});

it('delivers a server event only once using the stable delivery key', function () {
    Http::fake([
        'https://graph.facebook.com/v25.0/*/events' => Http::response([
            'events_received' => 1,
        ]),
    ]);

    $integration = TrackingIntegration::create([
        'name' => 'Meta CAPI',
        'provider' => 'meta',
        'public_id' => '123456789',
        'access_token' => 'meta-secret',
        'is_active' => true,
        'server_enabled' => true,
        'events' => ['purchase' => true],
    ]);
    $this->order->update([
        'tracking_context' => [
            'integration_ids' => [$integration->id],
            'product_ids' => [$this->order->items()->value('product_id')],
            'attribution' => [],
        ],
    ]);

    $dispatcher = app(OrderTrackingDispatcher::class);
    $dispatcher->dispatch($this->order, 'purchase');
    $dispatcher->dispatch($this->order, 'purchase');

    Http::assertSentCount(1);

    $delivery = TrackingEventDelivery::sole();

    expect($delivery->status)->toBe('sent')
        ->and($delivery->attempts)->toBe(1)
        ->and($delivery->event_name)->toBe('purchase');
});

it('treats an explicitly empty integration snapshot as authoritative', function () {
    Http::fake();

    TrackingIntegration::create([
        'name' => 'Meta CAPI',
        'provider' => 'meta',
        'public_id' => '123456789',
        'access_token' => 'meta-secret',
        'is_active' => true,
        'server_enabled' => true,
    ]);
    $this->order->update([
        'tracking_context' => [
            'integration_ids' => [],
            'product_ids' => [],
            'attribution' => [],
        ],
    ]);

    app(OrderTrackingDispatcher::class)->dispatch($this->order, 'purchase');

    Http::assertNothingSent();
    expect(TrackingEventDelivery::query()->count())->toBe(0);
});

it('dispatches a paid transition through the order observer', function () {
    Http::fake([
        'https://api.utmify.com.br/api-credentials/orders' => Http::response(['OK' => true]),
    ]);

    $integration = TrackingIntegration::create([
        'name' => 'UTMify',
        'provider' => 'utmify',
        'access_token' => 'utmify-secret',
        'is_active' => true,
        'server_enabled' => true,
        'events' => ['purchase' => true],
    ]);
    $this->order->update([
        'payment_status' => 'pending',
        'status' => 'pending',
        'tracking_context' => [
            'integration_ids' => [$integration->id],
            'product_ids' => [$this->order->items()->value('product_id')],
            'attribution' => [],
        ],
    ]);

    $this->order->update([
        'payment_status' => 'paid',
        'status' => 'paid',
    ]);

    Http::assertSentCount(1);
    expect(TrackingEventDelivery::query()->sole()->event_name)->toBe('purchase');
});

it('keeps an approval date when reporting a UTMify refund', function () {
    $integration = new TrackingIntegration([
        'name' => 'UTMify',
        'provider' => 'utmify',
    ]);
    $this->order->forceFill([
        'payment_data' => [
            'paid_at' => '2026-07-01T10:00:00-03:00',
            'refunded_at' => '2026-07-10T15:00:00-03:00',
        ],
    ])->saveQuietly();

    $payload = app(UtmifyService::class)->payload(
        $integration,
        $this->order,
        'refund',
    );

    expect($payload['status'])->toBe('refunded')
        ->and($payload['approvedDate'])->toBe('2026-07-01 13:00:00')
        ->and($payload['refundedAt'])->toBe('2026-07-10 18:00:00');
});

it('uses the order update time as the UTMify approval fallback', function () {
    $integration = new TrackingIntegration([
        'name' => 'UTMify',
        'provider' => 'utmify',
    ]);
    $this->order->timestamps = false;
    $this->order->forceFill([
        'placed_at' => '2026-07-01T10:00:00+00:00',
        'updated_at' => '2026-07-02T14:30:00+00:00',
        'payment_data' => [],
    ])->saveQuietly();
    $this->order->timestamps = true;

    $payload = app(UtmifyService::class)->payload(
        $integration,
        $this->order->fresh(),
        'purchase',
    );

    expect($payload['approvedDate'])->toBe('2026-07-02 14:30:00');
});

it('does not install the UTMify browser script for a server-only integration', function () {
    TrackingIntegration::create([
        'name' => 'UTMify API only',
        'provider' => 'utmify',
        'access_token' => 'utmify-secret',
        'is_active' => true,
        'browser_enabled' => false,
        'server_enabled' => true,
        'settings' => ['utm_script_enabled' => true],
    ]);

    expect(app(TrackingManager::class)->utmifyScriptEnabled())->toBeFalse();
});

it('redacts provider errors before persisting delivery diagnostics', function () {
    Http::fake([
        'https://graph.facebook.com/v25.0/*/events' => Http::response([
            'error' => [
                'message' => 'Rejected nicolas@example.com Nicolas da Silva 203.0.113.10 fbc-cookie',
            ],
        ], 400),
    ]);

    $integration = TrackingIntegration::create([
        'name' => 'Meta CAPI',
        'provider' => 'meta',
        'public_id' => '123456789',
        'access_token' => 'meta-secret',
        'is_active' => true,
        'server_enabled' => true,
    ]);
    $this->order->update([
        'tracking_context' => [
            'integration_ids' => [$integration->id],
            'product_ids' => [$this->order->items()->value('product_id')],
            'attribution' => ['_fbc' => 'fbc-cookie'],
        ],
    ]);

    app(OrderTrackingDispatcher::class)->dispatch($this->order, 'purchase');

    $error = TrackingEventDelivery::query()->sole()->last_error;

    expect($error)->not->toContain('nicolas@example.com')
        ->not->toContain('Nicolas da Silva')
        ->not->toContain('203.0.113.10')
        ->not->toContain('fbc-cookie')
        ->toContain('[REDACTED_');
});

it('freezes browser and server integration channels in the order snapshot', function () {
    Http::fake();

    $integration = TrackingIntegration::create([
        'name' => 'Meta browser only',
        'provider' => 'meta',
        'public_id' => '123456789',
        'access_token' => 'meta-secret',
        'is_active' => true,
        'browser_enabled' => true,
        'server_enabled' => false,
    ]);
    $tracking = app(TrackingManager::class);
    $snapshot = $tracking->snapshotForProductIds([
        $this->order->items()->value('product_id'),
    ]);

    expect($snapshot['integration_ids'])->toBe([$integration->id])
        ->and($snapshot['browser_integration_ids'])->toBe([$integration->id])
        ->and($snapshot['server_integration_ids'])->toBe([]);

    $this->order->update(['tracking_context' => $snapshot]);
    $integration->update(['server_enabled' => true]);

    app(OrderTrackingDispatcher::class)->dispatch($this->order, 'purchase');

    Http::assertNothingSent();
});

it('retries a transient failed delivery through the maintenance command', function () {
    Http::fake([
        'https://graph.facebook.com/v25.0/*/events' => Http::response([
            'events_received' => 1,
        ]),
    ]);

    $integration = TrackingIntegration::create([
        'name' => 'Meta CAPI',
        'provider' => 'meta',
        'public_id' => '123456789',
        'access_token' => 'meta-secret',
        'is_active' => true,
        'browser_enabled' => false,
        'server_enabled' => true,
    ]);
    $this->order->update([
        'tracking_context' => [
            'integration_ids' => [$integration->id],
            'browser_integration_ids' => [],
            'server_integration_ids' => [$integration->id],
            'product_ids' => [$this->order->items()->value('product_id')],
            'attribution' => [],
        ],
    ]);
    TrackingEventDelivery::create([
        'tracking_integration_id' => $integration->id,
        'order_id' => $this->order->id,
        'event_name' => 'purchase',
        'event_id' => app(TrackingManager::class)->eventId($this->order, 'purchase'),
        'status' => 'failed',
        'attempts' => 1,
        'last_http_status' => 500,
    ]);

    Artisan::call('tracking:retry-failed');

    Http::assertSentCount(1);
    expect(TrackingEventDelivery::query()->sole()->fresh())
        ->status->toBe('sent')
        ->attempts->toBe(2);
});
