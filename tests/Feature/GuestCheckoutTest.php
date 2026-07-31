<?php

use App\Models\Address;
use App\Models\CustomerDevice;
use App\Models\Order;
use App\Models\Product;
use App\Services\BlackcatPayService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function checkoutCart(Product $product, int $quantity = 2): array
{
    return [
        'items' => [
            "p{$product->id}" => [
                'key' => "p{$product->id}",
                'product_id' => $product->id,
                'variant_id' => null,
                'name' => $product->name,
                'slug' => $product->slug,
                'sku' => $product->sku,
                'price' => (float) $product->price,
                'image' => null,
                'quantity' => $quantity,
            ],
        ],
        'coupon' => null,
    ];
}

function validGuestCheckoutPayload(): array
{
    return [
        'name' => 'Maria de Almeida Cruz',
        'email' => 'MARIA.TESTE@example.com',
        'cpf_cnpj' => '529.982.247-25',
        'phone' => '(11) 98765-4321',
        'zip' => '01001-000',
        'street' => 'Praça da Sé',
        'number' => '100',
        'complement' => null,
        'neighborhood' => 'Sé',
        'city' => 'São Paulo',
        'state' => 'sp',
        'shipping_method' => 'full_free',
        'payment_method' => 'pix',
        'device_language' => 'pt-BR',
        'device_javascript_enabled' => true,
    ];
}

it('shows the three-step checkout to a guest even when the gateway is offline', function () {
    $product = Product::factory()->create([
        'status' => 'active',
        'price' => 23.50,
    ]);

    $this->withSession(['cart' => checkoutCart($product)])
        ->get(route('checkout'))
        ->assertOk()
        ->assertSee('Identificação')
        ->assertSee('Ir para Entrega')
        ->assertSee('Full Grátis')
        ->assertSee('Escolha uma forma de pagamento')
        ->assertSee('Finalizar Compra');
});

it('creates a guest PIX order with session-only access and no account', function () {
    $product = Product::factory()->create([
        'status' => 'active',
        'price' => 23.50,
    ]);

    $gateway = Mockery::mock(BlackcatPayService::class);
    $gateway->shouldReceive('isAvailable')->once()->andReturnTrue();
    $gateway->shouldReceive('createPixPayment')->once()->andReturn([
        'success' => true,
        'data' => [
            'transactionId' => 'pix_test_123',
            'status' => 'PENDING',
            'paymentData' => [
                'qrCode' => '000201010212pix-test',
                'qrCodeBase64' => 'data:image/png;base64,dGVzdA==',
                'copyPaste' => '000201010212pix-test',
                'expiresAt' => now()->addMinutes(10)->toIso8601String(),
            ],
        ],
    ]);
    app()->instance(BlackcatPayService::class, $gateway);

    $response = $this->withSession(['cart' => checkoutCart($product)])
        ->post(route('checkout.process'), validGuestCheckoutPayload());

    $order = Order::query()->sole();

    $response->assertRedirect(route('checkout.pix', $order));
    expect($order->user_id)->toBeNull()
        ->and($order->customer_name)->toBe('Maria de Almeida Cruz')
        ->and($order->customer_email)->toBe('maria.teste@example.com')
        ->and($order->customer_phone)->toBe('11987654321')
        ->and($order->customer_document)->toBe('52998224725')
        ->and((float) $order->shipping_total)->toBe(0.0)
        ->and((float) $order->total)->toBe(47.0)
        ->and($order->shipping_method)->toBe('Full Grátis');

    expect(Address::query()->sole()->user_id)->toBeNull()
        ->and(CustomerDevice::query()->sole()->user_id)->toBeNull();

    $this->get(route('checkout.pix', $order))
        ->assertOk()
        ->assertSee('Quase lá')
        ->assertSee('Verificar pagamento');

    $this->app['session']->flush();
    $this->get(route('checkout.pix', $order))->assertForbidden();
});

it('keeps admin data exports behind authentication', function () {
    $this->get('/data-exports/download/123e4567-e89b-12d3-a456-426614174000')
        ->assertRedirect('/login');
});
