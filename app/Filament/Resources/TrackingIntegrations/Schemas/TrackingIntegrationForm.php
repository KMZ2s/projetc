<?php

namespace App\Filament\Resources\TrackingIntegrations\Schemas;

use App\Filament\Resources\TrackingIntegrations\TrackingIntegrationResource;
use App\Models\Product;
use App\Models\TrackingIntegration;
use Filament\Forms\Components;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class TrackingIntegrationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Integração')
                ->description('Cadastre quantos pixels, propriedades e contas precisar.')
                ->schema([
                    Grid::make(2)->schema([
                        Components\TextInput::make('name')
                            ->label('Nome interno')
                            ->placeholder('Ex.: Meta — Campanha principal')
                            ->required()
                            ->maxLength(120),

                        Components\Select::make('provider')
                            ->label('Provedor')
                            ->options(TrackingIntegration::PROVIDERS)
                            ->default('meta')
                            ->required()
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(function (?string $state, Set $set): void {
                                $set('events', TrackingIntegrationResource::defaultSelectedEvents($state));
                                $set('public_id', null);
                                $set('access_token', null);
                                $set('server_enabled', false);
                                $set('settings', $state === 'utmify' ? [
                                    'platform_name' => 'Replicantfy',
                                    'utm_script_enabled' => true,
                                    'optimization_pixel_enabled' => false,
                                    'test_mode' => false,
                                ] : []);
                            }),
                    ]),

                    Grid::make(3)->schema([
                        Components\Toggle::make('is_active')
                            ->label('Integração ativa')
                            ->helperText('Somente integrações ativas disparam eventos.')
                            ->default(false)
                            ->live(),

                        Components\Toggle::make('browser_enabled')
                            ->label('Navegador')
                            ->helperText('Envia eventos pelo pixel instalado nas páginas.')
                            ->default(true)
                            ->live(),

                        Components\Toggle::make('server_enabled')
                            ->label('Servidor / API')
                            ->helperText('Envia eventos server-side usando a credencial abaixo.')
                            ->default(false)
                            ->live()
                            ->visible(fn (Get $get): bool => in_array(
                                $get('provider'),
                                ['meta', 'tiktok', 'utmify'],
                                true
                            )),
                    ]),

                    Components\TextInput::make('position')
                        ->label('Ordem de execução')
                        ->helperText('Menores valores são carregados primeiro.')
                        ->numeric()
                        ->integer()
                        ->minValue(0)
                        ->maxValue(9999)
                        ->default(0),
                ]),

            Section::make('Identificação e credenciais')
                ->description('IDs públicos podem aparecer no HTML. Tokens e segredos ficam criptografados no banco.')
                ->schema([
                    Components\TextInput::make('public_id')
                        ->label(fn (Get $get): string => $get('provider') === 'utmify'
                            ? 'ID do Pixel de Otimização'
                            : 'ID público')
                        ->placeholder(fn (Get $get): string => match ($get('provider')) {
                            'meta' => 'Ex.: 123456789012345',
                            'tiktok' => 'Ex.: CXXXXXXXXXXXXXXXXX',
                            'ga4' => 'Ex.: G-XXXXXXXXXX',
                            'google_ads' => 'Ex.: AW-123456789',
                            'utmify' => 'Cole o ID fornecido pela UTMify',
                            default => '',
                        })
                        ->helperText(fn (Get $get): string => match ($get('provider')) {
                            'meta' => 'ID do Pixel da Meta.',
                            'tiktok' => 'Pixel Code do TikTok.',
                            'ga4' => 'Measurement ID da propriedade GA4.',
                            'google_ads' => 'ID da tag do Google Ads.',
                            'utmify' => 'Opcional. Obrigatório apenas quando o Pixel de Otimização estiver ligado.',
                            default => '',
                        })
                        ->required(fn (Get $get): bool => $get('provider') !== 'utmify'
                            || (
                                (bool) $get('browser_enabled')
                                && (bool) $get('settings.optimization_pixel_enabled')
                            ))
                        ->rules(fn (Get $get): array => TrackingIntegrationResource::publicIdRules($get('provider')))
                        ->maxLength(255),

                    Components\TextInput::make('access_token')
                        ->label(fn (Get $get): string => match ($get('provider')) {
                            'meta' => 'Token da Conversions API',
                            'tiktok' => 'Token da Events API',
                            'ga4' => 'API Secret do Measurement Protocol',
                            'google_ads' => 'Credencial da API do Google Ads',
                            'utmify' => 'API Token da UTMify',
                            default => 'Token / segredo da API',
                        })
                        ->password()
                        ->revealable()
                        ->autocomplete('new-password')
                        ->maxLength(8192)
                        ->helperText('Deixe em branco ao editar para manter a credencial atual. O valor salvo nunca é exibido.')
                        ->afterStateHydrated(fn (Components\TextInput $component) => $component->state(null))
                        ->dehydrated(fn (?string $state): bool => filled($state))
                        ->visible(fn (Get $get): bool => in_array(
                            $get('provider'),
                            ['meta', 'tiktok', 'utmify'],
                            true
                        )),

                    Components\TextInput::make('settings.test_event_code')
                        ->label(fn (Get $get): string => $get('provider') === 'meta'
                            ? 'Código de evento de teste da Meta'
                            : 'Código de evento de teste do TikTok')
                        ->helperText('Opcional. Use apenas durante a validação da integração.')
                        ->maxLength(255)
                        ->visible(fn (Get $get): bool => in_array($get('provider'), ['meta', 'tiktok'], true)),

                    Components\TextInput::make('settings.conversion_label')
                        ->label('Rótulo da conversão de compra')
                        ->placeholder('Ex.: AbCdEfGhIjkLmNoP')
                        ->helperText('É a parte que aparece depois de AW-XXXXXXXXX/ na ação de conversão.')
                        ->maxLength(255)
                        ->regex('/^[A-Za-z0-9_-]+$/')
                        ->required(fn (Get $get): bool => (bool) $get('is_active')
                            && (bool) $get('browser_enabled')
                            && in_array('purchase', (array) $get('events'), true))
                        ->visible(fn (Get $get): bool => $get('provider') === 'google_ads'),

                    Grid::make(2)
                        ->visible(fn (Get $get): bool => $get('provider') === 'utmify')
                        ->schema([
                            Components\TextInput::make('settings.platform_name')
                                ->label('Nome da plataforma')
                                ->default('Replicantfy')
                                ->required()
                                ->maxLength(80),

                            Components\Toggle::make('settings.utm_script_enabled')
                                ->label('Instalar script de UTMs')
                                ->helperText('Propaga UTMs entre as páginas e o checkout.')
                                ->default(true),

                            Components\Toggle::make('settings.optimization_pixel_enabled')
                                ->label('Pixel de Otimização')
                                ->helperText('Ativa o pixel da própria UTMify usando o ID informado acima.')
                                ->default(false)
                                ->live(),

                            Components\Toggle::make('settings.test_mode')
                                ->label('Modo de teste da API')
                                ->helperText('Marca os pedidos enviados à UTMify como testes.')
                                ->default(false),

                            Components\Placeholder::make('utmify_duplicate_warning')
                                ->label('Evite eventos duplicados')
                                ->content(
                                    'Se o mesmo destino já estiver cadastrado como Pixel Meta ou TikTok nativo, '
                                    .'não o repita no Pixel de Otimização da UTMify. Isso pode duplicar compras '
                                    .'e prejudicar a otimização das campanhas.'
                                )
                                ->visible(fn (Get $get): bool => (bool) $get('settings.optimization_pixel_enabled')),
                        ]),
                ]),

            Section::make('Eventos')
                ->description('Escolha quais ações serão enviadas a esta integração.')
                ->schema([
                    Components\CheckboxList::make('events')
                        ->label('')
                        ->options(fn (Get $get): array => TrackingIntegrationResource::eventOptions($get('provider')))
                        ->default(TrackingIntegrationResource::defaultSelectedEvents('meta'))
                        ->columns(2)
                        ->bulkToggleable()
                        ->required()
                        ->minItems(1)
                        ->afterStateHydrated(function (
                            Components\CheckboxList $component,
                            ?TrackingIntegration $record,
                            mixed $state
                        ): void {
                            $storedEvents = $record?->events;

                            if (is_array($storedEvents)) {
                                $component->state(array_keys(array_filter(
                                    $storedEvents,
                                    fn ($enabled) => (bool) $enabled
                                )));
                            } elseif (is_array($state) && ! array_is_list($state)) {
                                $component->state(array_keys(array_filter(
                                    $state,
                                    fn ($enabled) => (bool) $enabled
                                )));
                            }
                        })
                        ->dehydrateStateUsing(function (mixed $state): array {
                            $selected = array_map('strval', (array) $state);
                            $events = [];

                            foreach (TrackingIntegration::DEFAULT_EVENTS as $event => $default) {
                                $events[$event] = in_array($event, $selected, true);
                            }

                            return $events;
                        }),
                ]),

            Section::make('Produtos')
                ->description('Controle se a integração vale para toda a loja ou apenas para produtos específicos.')
                ->schema([
                    Components\Select::make('scope_mode')
                        ->label('Escopo')
                        ->options([
                            'all' => 'Todos os produtos',
                            'include' => 'Somente os produtos selecionados',
                            'exclude' => 'Todos, exceto os produtos selecionados',
                        ])
                        ->default('all')
                        ->required()
                        ->native(false)
                        ->live(),

                    Components\Select::make('product_ids')
                        ->label(fn (Get $get): string => $get('scope_mode') === 'exclude'
                            ? 'Produtos excluídos'
                            : 'Produtos incluídos')
                        ->options(fn (): array => Product::query()
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all())
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->required(fn (Get $get): bool => $get('scope_mode') !== 'all')
                        ->minItems(1)
                        ->visible(fn (Get $get): bool => $get('scope_mode') !== 'all')
                        ->dehydrateStateUsing(fn (mixed $state): array => array_values(array_unique(
                            array_map('intval', (array) $state)
                        ))),
                ]),
        ]);
    }
}
