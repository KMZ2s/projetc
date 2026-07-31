<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckoutRequest;
use App\Models\Address;
use App\Models\CheckoutSetting;
use App\Models\CouponUsage;
use App\Models\CustomerDevice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\BlackcatPayService;
use App\Services\CartService;
use App\Services\TrackingManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CheckoutController extends Controller
{
    private const SHIPPING_OPTIONS = [
        'full_free' => [
            'label' => 'Full Grátis',
            'description' => 'Entrega em 9-11 dias úteis',
            'price' => 0.0,
        ],
        'jadlog' => [
            'label' => 'Jadlog',
            'description' => 'Entrega em 6-8 dias úteis',
            'price' => 22.85,
        ],
        'sedex' => [
            'label' => 'Sedex',
            'description' => 'Entrega em 3 dias úteis',
            'price' => 25.50,
        ],
    ];

    public function __construct(
        protected CartService $cart,
        protected BlackcatPayService $blackcat,
        protected TrackingManager $tracking,
    ) {}

    // =========================================================================
    // GET /checkout
    // =========================================================================

    public function index()
    {
        $summary = $this->cart->summary();

        if (empty($summary['items'])) {
            return redirect()->route('cart.index');
        }

        $user = Auth::user();
        $defaultAddress = $user?->defaultAddress();
        $settings = CheckoutSetting::current();

        $failedOrder = null;
        if (session('show_downsell') && $settings->downsell_enabled) {
            $allowedOrderIds = array_map('intval', session('checkout_order_ids', []));
            $failedOrder = Order::where('id', session('failed_order_id'))
                ->where(function ($query) use ($allowedOrderIds) {
                    if (Auth::check()) {
                        $query->where('user_id', Auth::id());
                    }

                    if ($allowedOrderIds !== []) {
                        $query->orWhereIn('id', $allowedOrderIds);
                    }
                })
                ->where('payment_status', 'failed')
                ->first();
        }

        return view('checkout.index', [
            'cart' => $summary,
            'user' => $user,
            'defaultAddress' => $defaultAddress,
            'checkoutSettings' => $settings,
            'failedOrder' => $failedOrder,
            'shippingOptions' => self::SHIPPING_OPTIONS,
            'trackingContext' => $this->tracking->browserContextForCart(
                $summary,
                'initiate_checkout'
            ),
        ]);
    }

    // =========================================================================
    // POST /checkout
    // =========================================================================

    public function process(CheckoutRequest $request)
    {
        $summary = $this->cart->summary();

        if (empty($summary['items'])) {
            return redirect()->route('cart.index')->with('error', 'Seu carrinho está vazio.');
        }

        // Defesa em profundidade: bloqueia POST direto caso o admin tenha
        // desativado o gateway entre o GET e o submit do form.
        if ($redirect = $this->guardActiveGateway('checkout')) {
            return $redirect;
        }

        $user = Auth::user();

        if ($user) {
            $this->updateUserProfileIfMissing($user, $request);
        }

        $summary = $this->applyCheckoutPricing($summary, $request);

        try {
            [$order, $device] = $this->createOrderInTransaction($user, $request, $summary);
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Erro ao registrar pedido. Tente novamente.');
        }

        $order->load(['items', 'shippingAddress', 'user']);
        $this->rememberOrder($order);

        return $request->isCardPayment()
            ? $this->processCard($order, $request, $device)
            : $this->processPix($order, $request);
    }

    // =========================================================================
    // POST /checkout/declined/{order:order_number}/pix — downsell retry
    // =========================================================================

    public function retryAsPix(Order $order)
    {
        $this->authorizeOrder($order);

        $settings = CheckoutSetting::current();

        if (! $settings->downsell_enabled || ! $settings->pix_enabled) {
            return redirect()->route('checkout')
                ->with('error', 'Downsell não está disponível no momento.');
        }

        // Mesmo a Order já estando criada, ainda depende de chamada ao
        // gateway pra gerar o QR Code. Se desativaram entre tentativas,
        // melhor parar aqui.
        if ($redirect = $this->guardActiveGateway('checkout')) {
            return $redirect;
        }

        if ($order->payment_status !== 'failed') {
            return redirect()->route('checkout');
        }

        if (! in_array($order->payment_method, ['credit_card', 'debit_card'], true)) {
            return redirect()->route('checkout');
        }

        $user = $order->user;

        $newSubtotal = (float) $order->subtotal;
        $discountPct = $settings->downsell_pix_discount_percent;
        $newDiscount = round($newSubtotal * ($discountPct / 100), 2);
        $newTotal = $newSubtotal - $newDiscount;

        try {
            $newOrder = DB::transaction(function () use ($order, $user, $newSubtotal, $newDiscount, $newTotal) {
                $newOrder = Order::create([
                    'user_id' => $user?->id,
                    'customer_name' => $order->customer_name,
                    'customer_email' => $order->customer_email,
                    'customer_phone' => $order->customer_phone,
                    'customer_document' => $order->customer_document,
                    'order_number' => $this->generateOrderNumber(),
                    'status' => 'pending',
                    'payment_status' => 'pending',
                    'fulfillment_status' => 'pending',
                    'subtotal' => $newSubtotal,
                    'discount_total' => $newDiscount,
                    'shipping_total' => 0,
                    'tax_total' => 0,
                    'total' => $newTotal,
                    'currency' => 'BRL',
                    'payment_method' => 'pix',
                    'shipping_address_id' => $order->shipping_address_id,
                    'billing_address_id' => $order->billing_address_id,
                    'customer_note' => $order->customer_note,
                    // Atribuição do downsell herda da Order original: o lead
                    // foi originado pela mesma campanha, mesmo que a sessão
                    // atual tenha UTMs diferentes. Mantém a história limpa
                    // pro relatório de marketing.
                    'utm_data' => $order->utm_data,
                    'tracking_context' => $order->tracking_context,
                    'placed_at' => now(),
                ]);

                foreach ($order->items as $item) {
                    OrderItem::create([
                        'order_id' => $newOrder->id,
                        'product_id' => $item->product_id,
                        'variant_id' => $item->variant_id,
                        'product_name' => $item->product_name,
                        'variant_sku' => $item->variant_sku,
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'total_price' => $item->total_price,
                        'discount' => $item->discount,
                    ]);
                }

                return $newOrder;
            });
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('checkout')
                ->with('error', 'Erro ao gerar PIX. Tente novamente.');
        }

        $newOrder->load(['items', 'shippingAddress', 'user']);
        $this->rememberOrder($newOrder);

        $response = $this->blackcat->createPixPayment(
            $newOrder,
            $newOrder->customerPayload(),
            $this->capturedUtm()
        );

        if (! ($response['success'] ?? false)) {
            $this->markOrderFailed($newOrder, $response['message'] ?? 'Erro desconhecido');

            return redirect()->route('checkout')
                ->with('error', 'Erro ao gerar PIX: '.($response['message'] ?? 'Tente novamente.'));
        }

        $data = $response['data'];

        $newOrder->update([
            'blackcat_transaction_id' => $data['transactionId'],
            'payment_data' => [
                'status' => $data['status'] ?? 'PENDING',
                'transaction_id' => $data['transactionId'],
                'invoice_url' => $data['invoiceUrl'] ?? null,
                'qr_code' => $data['paymentData']['qrCode'] ?? null,
                'qr_code_base64' => $data['paymentData']['qrCodeBase64'] ?? null,
                'copy_paste' => $data['paymentData']['copyPaste'] ?? null,
                'expires_at' => $data['paymentData']['expiresAt'] ?? null,
                'from_downsell' => true,
                'original_order_id' => $order->id,
            ],
        ]);

        session()->forget(['show_downsell', 'failed_order_id']);

        return redirect()->route('checkout.pix', $newOrder);
    }

    // =========================================================================
    // Pagamento — PIX
    // =========================================================================

    protected function processPix(Order $order, CheckoutRequest $request)
    {
        $response = $this->blackcat->createPixPayment(
            $order,
            $request->customerPayload(),
            $this->capturedUtm()
        );

        if (! ($response['success'] ?? false)) {
            $this->markOrderFailed($order, $response['message'] ?? 'Erro desconhecido');

            return back()
                ->withInput()
                ->with('error', 'Erro ao gerar PIX: '.($response['message'] ?? 'Tente novamente.'));
        }

        $data = $response['data'];

        $order->update([
            'blackcat_transaction_id' => $data['transactionId'],
            'payment_data' => [
                'status' => $data['status'] ?? 'PENDING',
                'transaction_id' => $data['transactionId'],
                'invoice_url' => $data['invoiceUrl'] ?? null,
                'qr_code' => $data['paymentData']['qrCode'] ?? null,
                'qr_code_base64' => $data['paymentData']['qrCodeBase64'] ?? null,
                'copy_paste' => $data['paymentData']['copyPaste'] ?? null,
                'expires_at' => $data['paymentData']['expiresAt'] ?? null,
            ],
        ]);

        $this->cart->clear();

        return redirect()->route('checkout.pix', $order);
    }

    // =========================================================================
    // GET /checkout/pix/{order:order_number}
    // =========================================================================

    public function pix(Order $order)
    {
        $this->authorizeOrder($order);

        if ($order->isPaid()) {
            return redirect()->route('checkout.confirmation', $order);
        }

        if ($order->payment_method !== 'pix' || empty($order->payment_data['qr_code'])) {
            return redirect()->route('checkout.confirmation', $order);
        }

        return view('checkout.pix', [
            'order' => $order,
            'checkoutSettings' => CheckoutSetting::current(),
            'trackingContext' => $this->tracking->browserContextForOrder($order, 'pix_generated'),
        ]);
    }

    // =========================================================================
    // Pagamento — Cartão (com downsell flow)
    // =========================================================================

    protected function processCard(Order $order, CheckoutRequest $request, CustomerDevice $device)
    {
        $cardData = $request->cardData();

        $response = $this->blackcat->createCardPayment(
            $order,
            $request->customerPayload(),
            $cardData,
            $device->toBlackcatPayload(),
            $request->input('payment_method'),
            $this->capturedUtm()
        );

        if (! ($response['success'] ?? false)) {
            $this->markOrderFailed($order, $response['message'] ?? 'Erro desconhecido');

            return $this->cardFailureRedirect($order, $response['message'] ?? 'Tente novamente.');
        }

        $data = $response['data'];
        $status = $data['status'] ?? 'FAILED';

        if ($status === 'PAID') {
            $paymentInfo = $data['paymentData'] ?? [];

            $order->update([
                'blackcat_transaction_id' => $data['transactionId'],
                'payment_status' => 'paid',
                'status' => 'processing',
                'payment_data' => [
                    'status' => 'PAID',
                    'paid_at' => now()->toIso8601String(),
                    'transaction_id' => $data['transactionId'],
                    'authorization_code' => $paymentInfo['authorizationCode'] ?? null,
                    'nsu' => $paymentInfo['nsu'] ?? null,
                    'tid' => $paymentInfo['tid'] ?? null,
                    'installments' => $paymentInfo['installments'] ?? $cardData['installments'],
                    'card_brand' => $paymentInfo['cardBrand'] ?? null,
                    'last_digits' => $paymentInfo['lastDigits'] ?? null,
                    'holder_name' => $cardData['holder_name'],
                ],
            ]);

            $this->cart->clear();

            return redirect()->route('checkout.confirmation', $order);
        }

        if ($status === 'PENDING_3DS') {
            $threeDs = $data['threeDS'] ?? [];

            $order->update([
                'blackcat_transaction_id' => $data['transactionId'],
                'payment_data' => [
                    'status' => 'PENDING_3DS',
                    'transaction_id' => $data['transactionId'],
                    'three_ds' => [
                        'token' => $threeDs['token'] ?? null,
                        'acs_url' => $threeDs['start']['acsUrl'] ?? null,
                        'creq' => $threeDs['start']['acsPayload']['creq'] ?? null,
                    ],
                    'card_meta' => [
                        'installments' => $cardData['installments'],
                        'last_digits' => substr((string) $cardData['number'], -4),
                    ],
                ],
            ]);

            $this->cart->clear();

            return redirect()->route('checkout.3ds', $order);
        }

        $reason = $data['refusedReason']['description'] ?? 'Pagamento recusado pelo banco.';
        $this->markOrderFailed($order, $reason);

        return $this->cardFailureRedirect($order, $reason);
    }

    protected function cardFailureRedirect(Order $order, string $reason)
    {
        $settings = CheckoutSetting::current();

        if ($settings->downsell_enabled) {
            return redirect()->route('checkout')
                ->with('show_downsell', true)
                ->with('failed_order_id', $order->id)
                ->with('error', 'Cartão recusado: '.$reason);
        }

        return back()->with('error', 'Cartão recusado: '.$reason);
    }

    // =========================================================================
    // GET /checkout/confirmacao/{order:order_number}
    // =========================================================================

    public function confirmation(Order $order)
    {
        $this->authorizeOrder($order);
        $order->load(['items', 'shippingAddress', 'user']);

        return view('checkout.confirmation', [
            'order' => $order,
            'checkoutSettings' => CheckoutSetting::current(),
            'trackingContext' => $this->tracking->browserContextForOrder(
                $order,
                $order->isPaid() ? 'purchase' : 'page_view'
            ),
        ]);
    }

    // =========================================================================
    // GET /checkout/3ds/{order:order_number}
    // =========================================================================

    public function pending3ds(Order $order)
    {
        $this->authorizeOrder($order);

        if (! $order->isPending3ds()) {
            return redirect()->route('checkout.confirmation', $order);
        }

        $threeDs = $order->payment_data['three_ds'] ?? [];

        // Sanity check: ACS URL e creq são obrigatórios pra abrir a popup
        if (empty($threeDs['acs_url']) || empty($threeDs['creq'])) {
            return redirect()->route('checkout.confirmation', $order)
                ->with('error', 'Dados de autenticação 3DS indisponíveis. Tente novamente.');
        }

        return view('checkout.pending-3ds', [
            'order' => $order,
            'threeDs' => $threeDs,
            'trackingContext' => $this->tracking->browserContextForOrder($order, 'page_view'),
        ]);
    }

    // =========================================================================
    // GET /checkout/status/{order:order_number} — polling AJAX
    // =========================================================================

    public function status(Order $order)
    {
        $this->authorizeOrder($order);

        return response()->json([
            'payment_status' => $order->payment_status,
            'status' => $order->status,
            'is_paid' => $order->payment_status === 'paid',
            'is_failed' => $order->payment_status === 'failed',
            'redirect_url' => $order->payment_status === 'paid'
                ? route('checkout.confirmation', $order)
                : null,
        ]);
    }

    // =========================================================================
    // Helpers privados
    // =========================================================================

    /**
     * Bloqueia o fluxo se o gateway não estiver disponível.
     * Delegado ao BlackcatPayService::isAvailable() — quando virar
     * multi-gateway, vira chamada a um GatewayManager ou similar sem
     * tocar neste helper.
     *
     * Retorna RedirectResponse pra ser usado com early return; null se OK.
     */
    protected function guardActiveGateway(string $route): ?RedirectResponse
    {
        if ($this->blackcat->isAvailable()) {
            return null;
        }

        return redirect()->route($route)->with(
            'error',
            'Pagamentos estão temporariamente indisponíveis. Tente novamente em instantes.'
        );
    }

    /**
     * Recupera UTMs capturadas pelo middleware CaptureUtmParameters.
     * Usado em todas as chamadas ao gateway pra propagar atribuição
     * de marketing.
     */
    protected function capturedUtm(): array
    {
        $utm = session('utm', []);

        $utm = is_array($utm) ? $utm : [];

        foreach (['_fbp', '_fbc', '_ttp'] as $cookie) {
            $value = request()->cookie($cookie);

            if (is_string($value) && $value !== '') {
                $utm[$cookie] = mb_substr($value, 0, 255);
            }
        }

        return $utm;
    }

    protected function authorizeOrder(Order $order): void
    {
        $belongsToAuthenticatedUser = Auth::check()
            && (int) $order->user_id === (int) Auth::id();
        $belongsToGuestSession = in_array(
            (int) $order->id,
            array_map('intval', session('checkout_order_ids', [])),
            true
        );

        abort_unless($belongsToAuthenticatedUser || $belongsToGuestSession, 403);
    }

    protected function updateUserProfileIfMissing(User $user, CheckoutRequest $request): void
    {
        $update = [];

        if (empty($user->phone) && $request->input('phone')) {
            $update['phone'] = $request->input('phone');
        }

        if (empty($user->cpf_cnpj) && $request->input('cpf_cnpj')) {
            $update['cpf_cnpj'] = $request->input('cpf_cnpj');
        }

        if (! empty($update)) {
            $user->update($update);
            $user->refresh();
        }
    }

    protected function createOrderInTransaction(?User $user, CheckoutRequest $request, array $summary): array
    {
        // Capturado fora da closure pra evitar resolver a session dentro
        // da transação (a sessão já foi resolvida no início do request,
        // mas explicitar mantém o código auditável).
        $utm = $this->capturedUtm();
        $productIds = array_values(array_unique(array_map(
            'intval',
            array_column($summary['items'], 'product_id')
        )));
        $trackingContext = array_merge(
            $this->tracking->snapshotForProductIds($productIds),
            [
                'attribution' => $utm,
            ],
        );

        return DB::transaction(function () use ($user, $request, $summary, $utm, $trackingContext) {
            $address = Address::create(array_merge(
                $request->addressData(),
                ['user_id' => $user?->id]
            ));

            $order = Order::create([
                'user_id' => $user?->id,
                'customer_name' => $request->input('name'),
                'customer_email' => $request->input('email'),
                'customer_phone' => $request->input('phone'),
                'customer_document' => $request->input('cpf_cnpj'),
                'order_number' => $this->generateOrderNumber(),
                'status' => 'pending',
                'payment_status' => 'pending',
                'fulfillment_status' => 'pending',
                'subtotal' => $summary['subtotal'],
                'discount_total' => $summary['discount'],
                'shipping_total' => $summary['shipping_total'],
                'tax_total' => 0,
                'total' => $summary['total'],
                'coupon_id' => $summary['coupon']['id'] ?? null,
                'currency' => 'BRL',
                'payment_method' => $request->input('payment_method'),
                'shipping_method' => $summary['shipping_label'],
                'shipping_address_id' => $address->id,
                'billing_address_id' => $address->id,
                'customer_note' => $request->input('customer_note'),
                // null em vez de [] quando não há UTM na sessão. Mantém
                // a coluna limpa pra `WHERE utm_data IS NOT NULL` nos
                // relatórios e pra exportação CSV não vir com `[]` solto.
                'utm_data' => $utm ?: null,
                'tracking_context' => $trackingContext,
                'placed_at' => now(),
            ]);

            foreach ($summary['items'] as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'variant_id' => $item['variant_id'],
                    'product_name' => $item['name'],
                    'variant_sku' => $item['sku'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['price'],
                    'total_price' => $item['price'] * $item['quantity'],
                    'discount' => 0,
                ]);
            }

            if (! empty($summary['coupon'])) {
                CouponUsage::create([
                    'coupon_id' => $summary['coupon']['id'],
                    'user_id' => $user?->id,
                    'order_id' => $order->id,
                ]);
            }

            $device = CustomerDevice::create(array_merge(
                $request->deviceData(),
                ['user_id' => $user?->id, 'order_id' => $order->id]
            ));

            return [$order, $device];
        });
    }

    protected function markOrderFailed(Order $order, string $reason): void
    {
        $order->update([
            'payment_status' => 'failed',
            'status' => 'cancelled',
            'payment_data' => array_merge($order->payment_data ?? [], [
                'status' => 'FAILED',
                'reason' => $reason,
            ]),
        ]);
    }

    protected function applyCheckoutPricing(array $summary, CheckoutRequest $request): array
    {
        $shipping = self::SHIPPING_OPTIONS[$request->input('shipping_method')]
            ?? self::SHIPPING_OPTIONS['sedex'];
        $shippingTotal = (float) $shipping['price'];
        $pixDiscount = 0.0;

        if ($request->input('payment_method') === 'pix') {
            $settings = CheckoutSetting::current();
            $pixDiscount = round(
                (float) $summary['total'] * ((int) $settings->pix_discount_percent / 100),
                2
            );
        }

        $summary['shipping_total'] = $shippingTotal;
        $summary['shipping_label'] = $shipping['label'];
        $summary['pix_discount'] = $pixDiscount;
        $summary['discount'] = (float) $summary['discount'] + $pixDiscount;
        $summary['total'] = max(0, (float) $summary['total'] - $pixDiscount + $shippingTotal);

        return $summary;
    }

    protected function rememberOrder(Order $order): void
    {
        $ids = array_values(array_unique(array_merge(
            array_map('intval', session('checkout_order_ids', [])),
            [(int) $order->id]
        )));

        session(['checkout_order_ids' => array_slice($ids, -20)]);
    }

    protected function generateOrderNumber(): string
    {
        do {
            $number = 'ORD-'.strtoupper(Str::random(8));
        } while (Order::where('order_number', $number)->exists());

        return $number;
    }
}
