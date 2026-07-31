<?php

namespace App\Filament\Pages;

use App\Models\PaymentGateway;
use App\Services\BlackcatPayService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class GatewaySettings extends Page
{
    protected string $view = 'filament.pages.gateway-settings';

    protected static ?string $slug = 'gateways';

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-credit-card';
    }

    public static function getNavigationLabel(): string
    {
        return 'Gateways';
    }

    public static function getNavigationGroup(): ?string
    {
        return null;
    }

    public static function getNavigationSort(): ?int
    {
        return 5;
    }

    private const AVAILABLE_GATEWAYS = [
        [
            'slug'        => 'blackcatpay',
            'label'       => 'BlackcatPay',
            'description' => 'PIX, Cartão de crédito e débito com autenticação 3DS.',
            'webhook_url' => '/checkout/callback',
            'fields'      => [
                [
                    'key'         => 'api_key',
                    'label'       => 'API Key',
                    'type'        => 'password',
                    'placeholder' => 'Sua chave da BlackcatPay',
                ],
                [
                    'key'         => 'api_base_url',
                    'label'       => 'URL da API',
                    'type'        => 'text',
                    'placeholder' => BlackcatPayService::DEFAULT_BASE_URL,
                    'hint'        => 'deixe em branco para usar o padrão',
                ],
            ],
        ],
    ];

    public array $gateways    = [];
    public array $definitions = [];

    public function mount(): void
    {
        $resolved = array_map(function ($definition) {
            $definition['webhook_url'] = url($definition['webhook_url']);
            return $definition;
        }, self::AVAILABLE_GATEWAYS);

        $this->definitions = $resolved;

        foreach (self::AVAILABLE_GATEWAYS as $definition) {
            $slug   = $definition['slug'];
            $record = PaymentGateway::firstOrCreate(
                ['slug' => $slug],
                ['name' => $definition['label'], 'is_active' => false]
            );

            $settings = $record->additional_settings ?? [];

            $this->gateways[$slug] = [
                'api_key'      => $record->api_key ?? '',
                'api_base_url' => $settings['api_base_url'] ?? '',
                'is_active'    => (bool) $record->is_active,
            ];
        }
    }

    public function save(string $slug): void
    {
        $data   = $this->gateways[$slug] ?? [];
        $record = PaymentGateway::where('slug', $slug)->firstOrFail();

        $settings = $record->additional_settings ?? [];

        // api_base_url é opcional — só persiste se o admin preencheu algo,
        // senão remove pra cair no DEFAULT_BASE_URL do service.
        $baseUrl = trim($data['api_base_url'] ?? '');
        if ($baseUrl !== '') {
            $settings['api_base_url'] = $baseUrl;
        } else {
            unset($settings['api_base_url']);
        }

        $record->update([
            'api_key'             => $data['api_key'] ?? null,
            'additional_settings' => $settings,
        ]);

        match ($slug) {
            'blackcatpay' => BlackcatPayService::clearCache(),
            default       => null,
        };

        Notification::make()->title('Credenciais salvas!')->success()->send();
    }

    public function toggle(string $slug): void
    {
        $data   = $this->gateways[$slug] ?? [];
        $record = PaymentGateway::where('slug', $slug)->firstOrFail();

        if (!$record->is_active && empty($data['api_key'])) {
            Notification::make()
                ->title('Configure a API Key antes de ativar.')
                ->warning()
                ->send();
            return;
        }

        $record->update(['is_active' => !$record->is_active]);
        $this->gateways[$slug]['is_active'] = $record->is_active;

        match ($slug) {
            'blackcatpay' => BlackcatPayService::clearCache(),
            default       => null,
        };

        $msg = $record->is_active
            ? "Gateway '{$record->name}' ativado!"
            : "Gateway '{$record->name}' desativado.";

        Notification::make()->title($msg)->success()->send();
    }

    /**
     * Testa a conexão com o gateway.
     *
     * IMPORTANTE: usa os dados SALVOS no banco, não o estado do form.
     * Se o admin editou e não salvou, o teste reflete a config antiga.
     */
    public function testConnection(string $slug): void
    {
        $record = PaymentGateway::where('slug', $slug)->first();

        if (!$record || empty($record->api_key)) {
            Notification::make()
                ->title('Salve a API Key antes de testar.')
                ->warning()
                ->send();
            return;
        }

        // Garante leitura fresca do banco (não do cache antigo)
        match ($slug) {
            'blackcatpay' => BlackcatPayService::clearCache(),
            default       => null,
        };

        try {
            $service = match ($slug) {
                'blackcatpay' => app(BlackcatPayService::class),
                default       => null,
            };

            if (!$service) {
                Notification::make()->title('Gateway desconhecido.')->danger()->send();
                return;
            }

            $result = $service->testConnection();

            if ($result['success']) {
                $seller = $result['seller'] ?? [];
                $name   = $seller['name'] ?? 'desconhecido';
                $cnpj   = $seller['cnpj'] ?? '';

                Notification::make()
                    ->title('Conexão bem-sucedida ✓')
                    ->body("Conectado como: {$name}" . ($cnpj ? " (CNPJ: {$cnpj})" : ''))
                    ->success()
                    ->duration(8000)
                    ->send();
            } else {
                Notification::make()
                    ->title('Falha na conexão')
                    ->body($result['message'] ?? 'Erro desconhecido.')
                    ->danger()
                    ->duration(8000)
                    ->send();
            }
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Erro ao testar conexão')
                ->body($e->getMessage())
                ->danger()
                ->duration(8000)
                ->send();
        }
    }
}