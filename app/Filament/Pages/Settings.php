<?php

namespace App\Filament\Pages;

use App\Models\Store;
use BackedEnum;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class Settings extends Page
{
    protected string $view = 'filament.pages.settings';

    // -------------------------------------------------------------------------
    // Configurações de Navegação
    // -------------------------------------------------------------------------

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog';

    protected static ?string $navigationLabel = 'Configurações';

    public static function getNavigationGroup(): ?string
    {
        return 'Ferramentas';
    }

    // -------------------------------------------------------------------------
    // Sugestões de mensagem de manutenção
    // -------------------------------------------------------------------------

    /**
     * Presets para o operador escolher rapidamente. O selector é
     * dehydrated(false) — não vai pro banco. Só preenche a textarea
     * `maintenance_message`, que o operador pode editar à vontade.
     */
    private const MAINTENANCE_PRESETS = [
        'catalog'   => 'Atualizando o catálogo. Voltamos em alguns minutos.',
        'novelties' => 'Estamos preparando novidades. Volte em breve!',
        'scheduled' => 'Manutenção programada em andamento. Obrigado pela paciência.',
        'tech'      => 'Ajustes técnicos rápidos. Já já estamos de volta.',
        'inventory' => 'Atualizando preços e estoque. Voltamos logo!',
    ];

    // -------------------------------------------------------------------------
    // Estado
    // -------------------------------------------------------------------------

    public ?array $data = [];

    public function mount(): void
    {
        // Store::current() garante o singleton do Replicantfy:
        // a tabela `stores` deve sempre ter exatamente 1 registro.
        $this->form->fill(Store::current()->toArray());
    }

    // -------------------------------------------------------------------------
    // Form
    // -------------------------------------------------------------------------

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                // ───────────────────────── Loja ─────────────────────────
                Forms\Components\TextInput::make('name')
                    ->label('Nome da loja')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('email')
                    ->label('E-mail')
                    ->email()
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('phone')
                    ->label('Telefone')
                    ->maxLength(50),
                Forms\Components\Textarea::make('address')
                    ->label('Endereço')
                    ->rows(3),
                Forms\Components\TextInput::make('currency')
                    ->label('Moeda')
                    ->default('BRL')
                    ->maxLength(3),
                Forms\Components\TextInput::make('currency_symbol')
                    ->label('Símbolo da moeda')
                    ->default('R$')
                    ->maxLength(5),
                Forms\Components\TextInput::make('tax_rate')
                    ->label('Taxa de imposto (%)')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->default(0),
                Forms\Components\FileUpload::make('logo')
                    ->label('Logo')
                    ->image()
                    ->disk('public')
                    ->visibility('public')
                    ->directory('store'),
                Forms\Components\FileUpload::make('favicon')
                    ->label('Favicon')
                    ->image()
                    ->disk('public')
                    ->visibility('public')
                    ->directory('store'),
                Forms\Components\TextInput::make('meta_title')
                    ->label('Título SEO')
                    ->maxLength(255),
                Forms\Components\Textarea::make('meta_description')
                    ->label('Descrição SEO')
                    ->rows(3)
                    ->maxLength(500),

                // ──────────────────── Modo de manutenção ────────────────────
                Section::make('Modo de manutenção')
                    ->description('Bloqueia o acesso público à loja temporariamente. Você (admin logado) continua vendo a loja normal.')
                    ->collapsible()
                    ->collapsed(fn (Get $get): bool => ! $get('maintenance_mode'))
                    ->schema([
                        Forms\Components\Toggle::make('maintenance_mode')
                            ->label('Ativar modo de manutenção')
                            ->helperText('Visitantes anônimos veem a página 503 com a mensagem abaixo. Webhooks de pagamento (BlackcatPay) e o admin continuam funcionando.')
                            ->live(),

                        Forms\Components\Select::make('maintenance_preset')
                            ->label('Sugestões de mensagem')
                            ->helperText('Selecione uma opção pra preencher a mensagem abaixo. Você pode editar livremente depois.')
                            ->placeholder('Escolha uma sugestão')
                            ->options([
                                'catalog'   => 'Atualizando o catálogo',
                                'novelties' => 'Preparando novidades',
                                'scheduled' => 'Manutenção programada',
                                'tech'      => 'Ajustes técnicos rápidos',
                                'inventory' => 'Atualizando preços/estoque',
                            ])
                            ->live()
                            ->dehydrated(false)
                            ->afterStateUpdated(function ($state, Set $set): void {
                                if ($state && isset(self::MAINTENANCE_PRESETS[$state])) {
                                    $set('maintenance_message', self::MAINTENANCE_PRESETS[$state]);
                                }
                            }),

                        Forms\Components\Textarea::make('maintenance_message')
                            ->label('Mensagem exibida ao cliente')
                            ->placeholder('Ex.: Voltamos em alguns minutos. Obrigado pela paciência!')
                            ->rows(4)
                            ->maxLength(500)
                            ->helperText('Aparece na página de manutenção. Em branco = mensagem padrão.'),
                    ]),
            ])
            ->statePath('data');
    }

    // -------------------------------------------------------------------------
    // Save
    // -------------------------------------------------------------------------

    public function save(): void
    {
        try {
            $store = Store::current();
            $store->fill($this->form->getState());
            $store->save();
            // Cache de manutenção é invalidado automaticamente em
            // Store::booted() quando os campos mudam.
        } catch (\Throwable $e) {
            report($e);

            Notification::make()
                ->title('Erro ao salvar configurações')
                ->body('Não foi possível salvar. Tente novamente em instantes.')
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title('Configurações salvas com sucesso!')
            ->success()
            ->send();
    }
}