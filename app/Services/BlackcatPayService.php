<?php

namespace App\Services;

use App\Models\CheckoutSetting;
use App\Models\Order;
use App\Models\PaymentGateway;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BlackcatPayService
{
    public const DEFAULT_BASE_URL = 'https://api.blackcathub.com/api';

    private const CACHE_KEY = 'blackcatpay_gateway_config';
    private const CACHE_TTL = 3600;

    /**
     * Timeouts HTTP. Conservadores para checkout — gateway lento mata UX.
     * Valores empíricos: BlackcatPay normalmente responde em <2s.
     */
    private const REQUEST_TIMEOUT = 20;
    private const CONNECT_TIMEOUT = 5;

    /**
     * Chaves UTM que a BlackcatPay aceita nativamente no payload da venda
     * e propaga para os webhooks. Qualquer chave fora desta lista é
     * descartada antes do envio.
     */
    private const ALLOWED_UTM_KEYS = [
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_content',
        'utm_term',
    ];

    private string $apiKey;
    private string $baseUrl;
    private bool   $isActive;

    public function __construct()
    {
        $config = Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            // Não filtramos por is_active aqui: quem decide se o gateway pode
            // ser usado é a camada superior via isAvailable(). O filtro antigo
            // escondia bugs de uso e quebrava o "Testar conexão" antes da
            // ativação.
            //
            // A leitura de api_key passa pelo accessor do model, que decripta
            // automaticamente. O valor cacheado fica em texto puro — trade-off
            // performance vs storage exposure documentado em PaymentGateway.
            $gateway = PaymentGateway::where('slug', 'blackcatpay')->first();

            $settings = $gateway?->additional_settings ?? [];

            return [
                'api_key'      => $gateway?->api_key ?? '',
                'api_base_url' => $settings['api_base_url'] ?? self::DEFAULT_BASE_URL,
                'is_active'    => (bool) ($gateway?->is_active ?? false),
            ];
        });

        $this->apiKey   = $config['api_key'];
        $this->baseUrl  = rtrim($config['api_base_url'] ?: self::DEFAULT_BASE_URL, '/');
        $this->isActive = $config['is_active'];
    }

    public static function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    // =========================================================================
    // Disponibilidade (consumido pelo CheckoutController)
    // =========================================================================

    /**
     * Indica se o gateway pode receber chamadas de pagamento agora.
     * Combinação de: registro existe + ativo + API Key + URL válida.
     *
     * Use isto antes de chamar createPixPayment / createCardPayment para
     * dar feedback amigável ao cliente em vez de explodir o request.
     */
    public function isAvailable(): bool
    {
        return $this->isActive
            && !empty($this->apiKey)
            && filter_var($this->baseUrl, FILTER_VALIDATE_URL) !== false;
    }

    // =========================================================================
    // PIX
    // =========================================================================

    public function createPixPayment(Order $order, array $customer, array $utm = []): array
    {
        $this->assertConfigured();
        $this->assertHasShippingAddress($order);

        $payload = [
            'amount'        => $this->toCents((float) $order->total),
            'currency'      => 'BRL',
            'paymentMethod' => 'pix',
            'items'         => $this->buildItems($order, tangible: true),
            'customer'      => $this->buildCustomer($customer),
            'pix'           => [
                'expiresInDays' => CheckoutSetting::current()->pixExpiresInDays(),
            ],
            'shipping'      => $this->buildShipping($order),
            'postbackUrl'   => $this->callbackUrl(),
            'externalRef'   => $order->order_number,
        ];

        $payload = array_merge($payload, $this->buildUtmPayload($utm));

        return $this->post('/sales/create-sale', $payload);
    }

    // =========================================================================
    // Cartão (crédito ou débito com 3DS)
    // =========================================================================

    public function createCardPayment(
        Order $order,
        array $customer,
        array $card,
        array $device,
        string $method = 'credit_card',
        array $utm = []
    ): array {
        $this->assertConfigured();
        $this->assertHasShippingAddress($order);

        $payload = [
            'amount'        => $this->toCents((float) $order->total),
            'currency'      => 'BRL',
            'paymentMethod' => $method,
            'items'         => $this->buildItems($order, tangible: true),
            'customer'      => $this->buildCustomer($customer),
            'card'          => [
                'number'       => preg_replace('/\D/', '', $card['number']),
                'holderName'   => strtoupper(trim($card['holder_name'])),
                'expiryMonth'  => str_pad($card['expiry_month'], 2, '0', STR_PAD_LEFT),
                'expiryYear'   => $card['expiry_year'],
                'cvv'          => $card['cvv'],
                'installments' => (int) ($card['installments'] ?? 1),
            ],
            'device'        => $device,
            'shipping'      => $this->buildShipping($order),
            'postbackUrl'   => $this->callbackUrl(),
            'externalRef'   => $order->order_number,
        ];

        $payload = array_merge($payload, $this->buildUtmPayload($utm));

        return $this->post('/sales/create-sale', $payload);
    }

    public function complete3ds(string $transactionId, string $tokenChallenge, array $card): array
    {
        $this->assertConfigured();

        return $this->post('/transactions/complete-3ds', [
            'transactionId'   => $transactionId,
            'token_challenge' => $tokenChallenge,
            'card'            => [
                'cvv'             => $card['cvv'],
                'expirationMonth' => (int) $card['expiry_month'],
                'expirationYear'  => (int) $card['expiry_year'],
                'holderName'      => strtoupper(trim($card['holder_name'] ?? '')),
                'installments'    => (int) ($card['installments'] ?? 1),
            ],
        ]);
    }

    public function getStatus(string $transactionId): array
    {
        $this->assertConfigured();

        return $this->get("/sales/{$transactionId}/status");
    }

    /**
     * Testa a conexão consultando dados do vendedor.
     * Usado pelo botão "Testar conexão" no admin.
     *
     * Retorna sempre array estruturado (não lança), pra UI ficar simples.
     *
     * @return array{success: bool, seller?: array, message?: string}
     */
    public function testConnection(): array
    {
        if (empty($this->apiKey)) {
            return ['success' => false, 'message' => 'API Key não configurada.'];
        }

        if (!filter_var($this->baseUrl, FILTER_VALIDATE_URL)) {
            return ['success' => false, 'message' => "URL da API inválida: '{$this->baseUrl}'"];
        }

        $response = $this->get('/sales/seller');

        if (($response['success'] ?? false) === true && isset($response['data'])) {
            return ['success' => true, 'seller' => $response['data']];
        }

        return [
            'success' => false,
            'message' => $response['message'] ?? 'Falha ao consultar dados do vendedor.',
        ];
    }

    // =========================================================================
    // Validações (fail-loud antes de bater no gateway)
    // =========================================================================

    private function assertConfigured(): void
    {
        if (empty($this->apiKey)) {
            throw new \RuntimeException(
                'BlackcatPay não configurado. Acesse /admin/gateways e cadastre a API Key.'
            );
        }

        if (!filter_var($this->baseUrl, FILTER_VALIDATE_URL)) {
            throw new \RuntimeException(
                "BlackcatPay com URL base inválida: '{$this->baseUrl}'. Verifique /admin/gateways."
            );
        }
    }

    private function assertHasShippingAddress(Order $order): void
    {
        if (!$order->shippingAddress) {
            throw new \RuntimeException(
                "Pedido {$order->order_number} não tem endereço de entrega. " .
                'BlackcatPay exige endereço quando há itens físicos no pedido.'
            );
        }
    }

    // =========================================================================
    // Builders internos
    // =========================================================================

    private function callbackUrl(): string
    {
        return url('/checkout/callback');
    }

    private function buildCustomer(array $data): array
    {
        $document = preg_replace('/\D/', '', $data['cpf_cnpj'] ?? '');
        $docType  = strlen($document) === 14 ? 'cnpj' : 'cpf';

        return [
            'name'     => $data['name'],
            'email'    => $data['email'],
            'phone'    => preg_replace('/\D/', '', $data['phone'] ?? ''),
            'document' => [
                'number' => $document,
                'type'   => $docType,
            ],
        ];
    }

    private function buildItems(Order $order, bool $tangible = false): array
    {
        return $order->items->map(fn ($item) => [
            'title'     => $item->product_name,
            'unitPrice' => $this->toCents((float) $item->unit_price),
            'quantity'  => (int) $item->quantity,
            'tangible'  => $tangible,
        ])->toArray();
    }

    private function buildShipping(Order $order): array
    {
        $address = $order->shippingAddress;

        return [
            'name'         => $order->customer_name ?? $order->user?->display_name ?? 'Cliente',
            'street'       => $address->street,
            'number'       => $address->number,
            'complement'   => $address->complement ?? '',
            'neighborhood' => $address->neighborhood,
            'city'         => $address->city,
            'state'        => strtoupper($address->state),
            'zipCode'      => preg_replace('/\D/', '', $address->zipcode),
        ];
    }

    /**
     * Filtra e devolve apenas as chaves UTM aceitas pela API.
     * Valores vazios também são descartados pra não poluir o payload.
     */
    private function buildUtmPayload(array $utm): array
    {
        if (empty($utm)) {
            return [];
        }

        $allowed = array_intersect_key($utm, array_flip(self::ALLOWED_UTM_KEYS));

        return array_filter(
            $allowed,
            fn ($value) => is_string($value) && $value !== ''
        );
    }

    private function toCents(float $amount): int
    {
        return (int) round($amount * 100);
    }

    // =========================================================================
    // HTTP
    // =========================================================================

    private function post(string $endpoint, array $data): array
    {
        try {
            $response = Http::withHeaders([
                'X-API-Key'    => $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ])
                ->connectTimeout(self::CONNECT_TIMEOUT)
                ->timeout(self::REQUEST_TIMEOUT)
                ->post($this->baseUrl . $endpoint, $data);

            $responseData = $response->json();
            Log::info('BlackcatPay POST ' . $endpoint, [
                'status'         => $response->status(),
                'transaction_id' => is_array($responseData)
                    ? ($responseData['data']['transactionId'] ?? $responseData['transactionId'] ?? null)
                    : null,
            ]);

            return $responseData ?? ['success' => false, 'message' => 'Resposta inválida da API'];

        } catch (\Throwable $e) {
            Log::error('BlackcatPay POST error', [
                'endpoint' => $endpoint,
                'error'    => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => 'Erro de comunicação com o gateway de pagamento.'];
        }
    }

    private function get(string $endpoint): array
    {
        try {
            $response = Http::withHeaders([
                'X-API-Key' => $this->apiKey,
                'Accept'    => 'application/json',
            ])
                ->connectTimeout(self::CONNECT_TIMEOUT)
                ->timeout(self::REQUEST_TIMEOUT)
                ->get($this->baseUrl . $endpoint);

            return $response->json() ?? ['success' => false, 'message' => 'Resposta inválida da API'];

        } catch (\Throwable $e) {
            Log::error('BlackcatPay GET error', [
                'endpoint' => $endpoint,
                'error'    => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => 'Erro de comunicação com o gateway de pagamento.'];
        }
    }
}
