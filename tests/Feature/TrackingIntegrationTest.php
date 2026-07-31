<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\TrackingEventDelivery;
use App\Models\TrackingIntegration;
use App\Services\OrderTrackingDispatcher;
use App\Services\TrackingManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

beforeEach(function () {
    Route::middleware('web')->get('/_tracking-test', fn () => response('ok'));
});

it('encrypts integration tokens at rest and never serializes them', function () {
    $integration = TrackingIntegration::create([
        'name' => 'Meta principal',
        'provider' => 'meta',
        'public_id' => '1234567890',
        'access_token' => 'token-super-secreto',
        'server_enabled' => true,
    ]);

    $rawToken = DB::table('tracking_integrations')
        ->where('id', $integration->id)
        ->value('access_token');

    expect($rawToken)->not->toBe('token-super-secreto')
        ->and($integration->fresh()->access_token)->toBe('token-super-secreto')
        ->and($integration->fresh()->toArray())->not->toHaveKey('access_token');
});

it('applies include and exclude product scopes', function () {
    $first = Product::factory()->create();
    $second = Product::factory()->create();

    $included = new TrackingIntegration([
        'scope_mode' => 'include',
        'product_ids' => [$first->id],
    ]);
    $excluded = new TrackingIntegration([
        'scope_mode' => 'exclude',
        'product_ids' => [$first->id],
    ]);

    expect($included->appliesToProductIds([$first->id]))->toBeTrue()
        ->and($included->appliesToProductIds([$second->id]))->toBeFalse()
        ->and($excluded->appliesToProductIds([$first->id]))->toBeFalse()
        ->and($excluded->appliesToProductIds([$second->id]))->toBeTrue();
});

it('captures utmify subids and advertising click identifiers', function () {
    $this->get('/_tracking-test?'.http_build_query([
        'utm_source' => 'meta',
        'utm_campaign' => 'lancamento',
        'src' => 'criativo-1',
        'sck' => 'checkout-a',
        'fbclid' => 'fb-click',
        'gclid' => 'google-click',
        'ttclid' => 'tiktok-click',
    ]))
        ->assertOk()
        ->assertSessionHas('utm.utm_source', 'meta')
        ->assertSessionHas('utm.utm_campaign', 'lancamento')
        ->assertSessionHas('utm.src', 'criativo-1')
        ->assertSessionHas('utm.sck', 'checkout-a')
        ->assertSessionHas('utm.fbclid', 'fb-click')
        ->assertSessionHas(
            'utm.fbclid_at',
            fn (mixed $value): bool => is_int($value) && $value > 0,
        )
        ->assertSessionHas('utm.gclid', 'google-click')
        ->assertSessionHas('utm.ttclid', 'tiktok-click');
});

it('exposes only active browser configuration and never exposes a token', function () {
    $product = Product::factory()->create();

    $active = TrackingIntegration::create([
        'name' => 'Meta catálogo',
        'provider' => 'meta',
        'public_id' => '123456789012345',
        'access_token' => 'token-que-nao-pode-ir-ao-html',
        'is_active' => true,
        'browser_enabled' => true,
        'server_enabled' => true,
        'scope_mode' => 'include',
        'product_ids' => [$product->id],
    ]);
    TrackingIntegration::create([
        'name' => 'TikTok inativo',
        'provider' => 'tiktok',
        'public_id' => 'C1234567890',
        'is_active' => false,
        'browser_enabled' => true,
    ]);

    $config = app(TrackingManager::class)->publicConfig();

    expect($config['integrations'])->toHaveCount(1)
        ->and($config['integrations'][0]['id'])->toBe($active->id)
        ->and($config['integrations'][0]['scope_mode'])->toBe('include')
        ->and($config['integrations'][0]['product_ids'])->toBe([$product->id])
        ->and(json_encode($config))->not->toContain('token-que-nao-pode-ir-ao-html');

    $html = view('checkout.layout', [
        'trackingContext' => ['event' => 'page_view'],
    ])->render();
    preg_match(
        "/REPLICANTFY_TRACKING_CONFIG = decodeJsonBase64\\('([^']+)'\\)/",
        $html,
        $matches,
    );
    $decodedBootstrap = base64_decode($matches[1] ?? '', true) ?: '';

    expect($decodedBootstrap)->toContain('123456789012345')
        ->not->toContain('token-que-nao-pode-ir-ao-html')
        ->and($html)->not->toContain('token-que-nao-pode-ir-ao-html');
});

it('sends a UTMify order once with the official header and waiting payment status', function () {
    Http::fake([
        'https://api.utmify.com.br/api-credentials/orders' => Http::response(['OK' => true]),
    ]);

    $integration = TrackingIntegration::create([
        'name' => 'UTMify API',
        'provider' => 'utmify',
        'access_token' => 'utmify-token',
        'is_active' => true,
        'browser_enabled' => false,
        'server_enabled' => true,
        'events' => array_replace(TrackingIntegration::DEFAULT_EVENTS, [
            'pix_generated' => true,
        ]),
        'settings' => ['platform_name' => 'Emporio Cacau'],
    ]);
    $product = Product::factory()->create(['price' => 95.40]);
    $order = Order::factory()->create([
        'order_number' => 'PIX-TRACKING-001',
        'customer_name' => 'Cliente Teste',
        'customer_email' => 'cliente@example.com',
        'customer_phone' => '11999999999',
        'customer_document' => '52998224725',
        'payment_method' => 'pix',
        'payment_status' => 'pending',
        'status' => 'pending',
        'total' => 190.80,
        'currency' => 'BRL',
        'utm_data' => [
            'utm_source' => 'meta',
            'utm_campaign' => 'teste',
            'src' => 'criativo-a',
            'sck' => 'checkout-a',
        ],
        'tracking_context' => [
            'integration_ids' => [$integration->id],
            'product_ids' => [$product->id],
        ],
    ]);
    OrderItem::factory()->for($order)->create([
        'product_id' => $product->id,
        'product_name' => $product->name,
        'quantity' => 2,
        'unit_price' => 95.40,
        'total_price' => 190.80,
    ]);

    $dispatcher = app(OrderTrackingDispatcher::class);
    $dispatcher->dispatch($order, 'pix_generated');
    $dispatcher->dispatch($order, 'pix_generated');

    Http::assertSentCount(1);
    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.utmify.com.br/api-credentials/orders'
            && $request->hasHeader('x-api-token', 'utmify-token')
            && $request['orderId'] === 'PIX-TRACKING-001'
            && $request['status'] === 'waiting_payment'
            && $request['paymentMethod'] === 'pix'
            && $request['trackingParameters']['src'] === 'criativo-a';
    });

    expect(TrackingEventDelivery::query()->count())->toBe(1)
        ->and(TrackingEventDelivery::query()->sole()->status)->toBe('sent');
});
