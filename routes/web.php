<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\BestSellersController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\CollectionController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ShippingController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\DataExportController;

// =========================================================================
// Loja pública
// =========================================================================

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/produtos', [ProductController::class, 'index'])->name('products.index');
Route::get('/produto/{slug}', [ProductController::class, 'show'])->name('product.show');
Route::get('/colecao/{slug}', [CollectionController::class, 'show'])->name('collection.show');
Route::get('/mais-vendidos', [BestSellersController::class, 'index'])->name('bestsellers.index');
Route::get('/products/{slug}', [ProductController::class, 'show']);
Route::get('/collections/all', [ProductController::class, 'index']);
Route::get('/collections/{slug}', [CollectionController::class, 'show']);
Route::get('/pages/{slug}', function (string $slug) {
    abort_unless(in_array($slug, ['privacidade', 'termos', 'trocas'], true), 404);

    return app('twig')->render('templates/page.twig', [
        'page' => $slug,
        'settings' => theme_settings(),
    ]);
});

// =========================================================================
// Carrinho
// =========================================================================

Route::get('/carrinho', [CartController::class, 'index'])->name('cart.index');
Route::get('/cart.json', [CartController::class, 'show'])->name('cart.show');

Route::prefix('cart')->name('cart.')->group(function () {
    Route::post('add.json',           [CartController::class, 'add'])->name('add');
    Route::post('update.json',        [CartController::class, 'update'])->name('update');
    Route::delete('items/{key}.json', [CartController::class, 'remove'])->name('remove');
    Route::post('coupon.json',        [CartController::class, 'applyCoupon'])->name('coupon.apply');
    Route::delete('coupon.json',      [CartController::class, 'removeCoupon'])->name('coupon.remove');
});

// =========================================================================
// API pública da loja (CSRF + throttle)
// =========================================================================

Route::prefix('api')->name('api.')->group(function () {
    Route::post('/shipping/calculate', [ShippingController::class, 'calculate'])
        ->middleware('throttle:30,1')
        ->name('shipping.calculate');
});

// =========================================================================
// Webhook BlackcatPay — sem CSRF (excluído no bootstrap/app.php)
// =========================================================================

Route::post('/checkout/callback', [\App\Http\Controllers\WebhookController::class, 'handle'])
    ->middleware('throttle:120,1')
    ->name('checkout.callback');


// =========================================================================
// Checkout público — pedidos guest são autorizados pela sessão
// =========================================================================

Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout');
Route::post('/checkout', [CheckoutController::class, 'process'])->name('checkout.process');
Route::get('/checkout/pix/{order:order_number}', [CheckoutController::class, 'pix'])->name('checkout.pix');
Route::post('/checkout/declined/{order:order_number}/pix', [CheckoutController::class, 'retryAsPix'])
    ->name('checkout.retry-pix');
Route::get('/checkout/confirmacao/{order:order_number}', [CheckoutController::class, 'confirmation'])
    ->name('checkout.confirmation');
Route::get('/checkout/3ds/{order:order_number}', [CheckoutController::class, 'pending3ds'])
    ->name('checkout.3ds');
Route::get('/checkout/status/{order:order_number}', [CheckoutController::class, 'status'])
    ->name('checkout.status')
    ->middleware('throttle:20,1');

Route::middleware(['auth', 'check.admin'])->group(function () {
    Route::get('/data-exports/download/{token}', [DataExportController::class, 'download'])
        ->name('admin.data.download')
        ->where('token', '[a-f0-9-]+');
});

// =========================================================================
// Minha conta
// =========================================================================

Route::middleware(['auth', 'verified'])
    ->prefix('account')
    ->name('account.')
    ->group(function () {
        Route::get('/',    [AccountController::class, 'index'])->name('index');

        Route::get('/pedidos',               [AccountController::class, 'orders'])->name('orders');
        Route::get('/pedidos/{orderNumber}', [AccountController::class, 'orderDetail'])->name('order-detail');

        Route::get('/perfil',  [AccountController::class, 'profile'])->name('profile');
        Route::put('/perfil',  [AccountController::class, 'updateProfile'])->name('profile.update');
        Route::put('/senha',   [AccountController::class, 'updatePassword'])->name('password.update');

        Route::get('/enderecos',         [AccountController::class, 'addresses'])->name('addresses');
        Route::post('/enderecos',        [AccountController::class, 'storeAddress'])->name('addresses.store');
        Route::delete('/enderecos/{id}', [AccountController::class, 'destroyAddress'])->name('addresses.destroy');
    });

Route::middleware(['auth', 'verified'])
    ->get('/dashboard', fn () => redirect()->route('account.index'))
    ->name('dashboard');


require __DIR__ . '/settings.php';
