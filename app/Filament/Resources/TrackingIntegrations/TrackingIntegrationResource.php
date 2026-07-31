<?php

namespace App\Filament\Resources\TrackingIntegrations;

use App\Filament\Resources\TrackingIntegrations\Pages\CreateTrackingIntegration;
use App\Filament\Resources\TrackingIntegrations\Pages\EditTrackingIntegration;
use App\Filament\Resources\TrackingIntegrations\Pages\ListTrackingIntegrations;
use App\Filament\Resources\TrackingIntegrations\Schemas\TrackingIntegrationForm;
use App\Filament\Resources\TrackingIntegrations\Tables\TrackingIntegrationsTable;
use App\Models\Product;
use App\Models\TrackingIntegration;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

class TrackingIntegrationResource extends Resource
{
    protected static ?string $model = TrackingIntegration::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-presentation-chart-line';

    protected static ?string $navigationLabel = 'Pixels e rastreamento';

    protected static ?string $modelLabel = 'integração';

    protected static ?string $pluralModelLabel = 'integrações de rastreamento';

    protected static ?string $recordTitleAttribute = 'name';

    public const EVENT_OPTIONS = [
        'page_view' => 'Visualização de página',
        'view_content' => 'Visualização de produto',
        'add_to_cart' => 'Adicionar ao carrinho',
        'initiate_checkout' => 'Iniciar checkout',
        'add_payment_info' => 'Adicionar dados de pagamento',
        'purchase' => 'Compra aprovada',
        'pix_generated' => 'PIX gerado',
    ];

    public static function getNavigationGroup(): ?string
    {
        return 'Integrações';
    }

    public static function getNavigationSort(): ?int
    {
        return 10;
    }

    public static function form(Schema $schema): Schema
    {
        return TrackingIntegrationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TrackingIntegrationsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTrackingIntegrations::route('/'),
            'create' => CreateTrackingIntegration::route('/create'),
            'edit' => EditTrackingIntegration::route('/{record}/edit'),
        ];
    }

    /**
     * Eventos que fazem sentido para cada destino.
     *
     * O formato persistido continua sendo um mapa booleano completo. Esta
     * lista controla somente as opções apresentadas no formulário.
     */
    public static function eventOptions(?string $provider): array
    {
        return match ($provider) {
            'utmify' => array_intersect_key(self::EVENT_OPTIONS, array_flip([
                'pix_generated',
                'purchase',
            ])),
            default => array_diff_key(self::EVENT_OPTIONS, ['pix_generated' => true]),
        };
    }

    public static function defaultSelectedEvents(?string $provider): array
    {
        return $provider === 'utmify'
            ? ['pix_generated', 'purchase']
            : ['page_view', 'view_content', 'add_to_cart', 'initiate_checkout', 'add_payment_info', 'purchase'];
    }

    public static function publicIdRules(?string $provider): array
    {
        return match ($provider) {
            'meta' => ['regex:/^[0-9]{5,30}$/'],
            'tiktok' => ['regex:/^[A-Z0-9]{10,40}$/i'],
            'ga4' => ['regex:/^G-[A-Z0-9]+$/i'],
            'google_ads' => ['regex:/^AW-[0-9]+$/i'],
            'utmify' => ['regex:/^[A-Za-z0-9_-]{5,160}$/'],
            default => [],
        };
    }

    /**
     * Normaliza e valida regras que dependem do registro atual.
     *
     * Essas regras ficam aqui, além das validações visuais do formulário,
     * para impedir ativação inválida por payload Livewire adulterado.
     */
    public static function prepareData(array $data, ?TrackingIntegration $record = null): array
    {
        $provider = trim((string) ($data['provider'] ?? ''));
        $serverCapable = in_array($provider, ['meta', 'tiktok', 'utmify'], true);
        $providerChanged = $record !== null && $record->provider !== $provider;
        $newToken = trim((string) ($data['access_token'] ?? ''));
        $hasStoredToken = $record !== null
            && ! $providerChanged
            && filled($record->getRawOriginal('access_token'));
        $hasToken = $newToken !== '' || $hasStoredToken;

        $data['name'] = trim((string) ($data['name'] ?? ''));
        $data['provider'] = $provider;
        $data['is_active'] = (bool) ($data['is_active'] ?? false);
        $data['browser_enabled'] = (bool) ($data['browser_enabled'] ?? false);
        $data['server_enabled'] = $serverCapable && (bool) ($data['server_enabled'] ?? false);
        $data['position'] = max(0, min(9999, (int) ($data['position'] ?? 0)));

        $publicId = trim((string) ($data['public_id'] ?? ''));
        $data['public_id'] = in_array($provider, ['tiktok', 'ga4', 'google_ads'], true)
            ? strtoupper($publicId)
            : ($publicId !== '' ? $publicId : null);

        $data['events'] = self::normalizeEvents($data['events'] ?? [], $provider);
        $data['scope_mode'] = in_array(($data['scope_mode'] ?? null), ['all', 'include', 'exclude'], true)
            ? $data['scope_mode']
            : 'all';
        $data['product_ids'] = $data['scope_mode'] === 'all'
            ? null
            : array_values(array_unique(array_map('intval', (array) ($data['product_ids'] ?? []))));
        $data['settings'] = self::normalizeSettings((array) ($data['settings'] ?? []), $provider);

        if (! $serverCapable) {
            // GA4 e Google Ads são integrações exclusivamente browser nesta
            // versão; não persista uma credencial que nenhum driver utilizará.
            $data['access_token'] = null;
        } elseif ($newToken !== '') {
            $data['access_token'] = $newToken;
        } elseif ($providerChanged) {
            // Uma credencial de outro provedor nunca deve ser reaproveitada.
            $data['access_token'] = null;
        } else {
            // Campo vazio em edição preserva a credencial criptografada atual.
            unset($data['access_token']);
        }

        $errors = [];

        if (! array_key_exists($provider, TrackingIntegration::PROVIDERS)) {
            $errors['provider'] = 'Selecione um provedor válido.';
        }

        if ($data['name'] === '') {
            $errors['name'] = 'Informe um nome para a integração.';
        }

        $utmifyOptimizationEnabled = $provider === 'utmify'
            && $data['browser_enabled']
            && (bool) ($data['settings']['optimization_pixel_enabled'] ?? false);

        if (($provider !== 'utmify' || $utmifyOptimizationEnabled) && empty($data['public_id'])) {
            $errors['public_id'] = 'Informe o ID público dessa integração.';
        }

        if (! empty($data['public_id'])) {
            if (! self::hasValidPublicIdFormat($provider, $data['public_id'])) {
                $errors['public_id'] = 'O ID informado não possui um formato válido para este provedor.';
            }

            $duplicate = TrackingIntegration::query()
                ->where('provider', $provider)
                ->where('public_id', $data['public_id'])
                ->when($record, fn ($query) => $query->whereKeyNot($record->getKey()))
                ->exists();

            if ($duplicate) {
                $errors['public_id'] = 'Este ID já está cadastrado para o mesmo provedor.';
            }
        }

        if ($data['is_active'] && ! $data['browser_enabled'] && ! $data['server_enabled']) {
            $errors['browser_enabled'] = 'Ative pelo menos um canal: navegador ou servidor.';
        }

        if ($data['is_active'] && $data['server_enabled'] && ! $hasToken) {
            $errors['access_token'] = 'Informe a credencial para ativar o envio pelo servidor.';
        }

        if ($data['is_active'] && ! in_array(true, $data['events'], true)) {
            $errors['events'] = 'Selecione pelo menos um evento para ativar a integração.';
        }

        if ($data['scope_mode'] !== 'all') {
            $productIds = $data['product_ids'] ?? [];

            if ($productIds === []) {
                $errors['product_ids'] = 'Selecione ao menos um produto para este escopo.';
            } elseif (Product::query()->whereKey($productIds)->count() !== count($productIds)) {
                $errors['product_ids'] = 'A seleção contém um produto inexistente.';
            }
        }

        if (
            $provider === 'google_ads'
            && $data['is_active']
            && $data['browser_enabled']
            && ($data['events']['purchase'] ?? false)
            && empty($data['settings']['conversion_label'])
        ) {
            $errors['settings.conversion_label'] = 'Informe o rótulo da conversão de compra.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $data;
    }

    private static function normalizeEvents(mixed $state, string $provider): array
    {
        $selected = [];

        if (is_array($state) && array_is_list($state)) {
            $selected = array_map('strval', $state);
        } elseif (is_array($state)) {
            $selected = array_keys(array_filter($state, fn ($enabled) => (bool) $enabled));
        }

        $allowed = array_keys(self::eventOptions($provider));
        $selected = array_intersect($selected, $allowed);

        $events = [];
        foreach (TrackingIntegration::DEFAULT_EVENTS as $event => $default) {
            $events[$event] = in_array($event, $selected, true);
        }

        return $events;
    }

    private static function hasValidPublicIdFormat(string $provider, string $publicId): bool
    {
        return (bool) preg_match(match ($provider) {
            'meta' => '/^[0-9]{5,30}$/',
            'tiktok' => '/^[A-Z0-9]{10,40}$/i',
            'ga4' => '/^G-[A-Z0-9]+$/i',
            'google_ads' => '/^AW-[0-9]+$/i',
            'utmify' => '/^[A-Za-z0-9_-]{5,160}$/',
            default => '/(?!)^/',
        }, $publicId);
    }

    private static function normalizeSettings(array $settings, string $provider): array
    {
        return match ($provider) {
            'meta', 'tiktok' => array_filter([
                'test_event_code' => trim((string) ($settings['test_event_code'] ?? '')),
            ], fn ($value) => $value !== ''),
            'google_ads' => array_filter([
                'conversion_label' => trim((string) ($settings['conversion_label'] ?? '')),
            ], fn ($value) => $value !== ''),
            'utmify' => [
                'platform_name' => trim((string) (
                    $settings['platform_name']
                    ?? $settings['platform']
                    ?? ''
                )) ?: 'Replicantfy',
                'utm_script_enabled' => (bool) (
                    $settings['utm_script_enabled']
                    ?? $settings['install_utm_script']
                    ?? true
                ),
                // Alias temporário para consumidores que ainda usam o nome antigo.
                'install_utm_script' => (bool) (
                    $settings['utm_script_enabled']
                    ?? $settings['install_utm_script']
                    ?? true
                ),
                'optimization_pixel_enabled' => (bool) ($settings['optimization_pixel_enabled'] ?? false),
                'test_mode' => (bool) ($settings['test_mode'] ?? false),
            ],
            default => [],
        };
    }
}
