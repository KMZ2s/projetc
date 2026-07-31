<?php

namespace App\Filament\Pages;

use App\Models\CheckoutSetting;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class CheckoutSettings extends Page
{
    protected string $view = 'filament.pages.checkout-settings';

    protected static ?string $slug = 'checkout-settings';

    protected static ?string $title = 'Configurações do Checkout';

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-shopping-cart';
    }

    public static function getNavigationLabel(): string
    {
        return 'Checkout';
    }

    public static function getNavigationGroup(): ?string
    {
        return null;
    }

    public static function getNavigationSort(): ?int
    {
        return 6;
    }

    /**
     * Estado do form. Mapeado 1:1 com colunas do CheckoutSetting.
     */
    public array $data = [];

    public function mount(): void
    {
        $this->data = CheckoutSetting::current()->toArray();
    }

    public function save(): void
    {
        $payload = [
            // Métodos de pagamento
            'credit_card_enabled' => (bool) ($this->data['credit_card_enabled'] ?? false),
            'pix_enabled'         => (bool) ($this->data['pix_enabled'] ?? false),

            // Cartão (capping defensivo: 1-24 parcelas)
            'installments_max'             => $this->clampInt($this->data['installments_max'] ?? 12, 1, 24),
            'installments_no_interest_max' => $this->clampInt($this->data['installments_no_interest_max'] ?? 12, 1, 24),

            // PIX (descontos 0-100%, expiração 5min-24h)
            'pix_discount_percent' => $this->clampInt($this->data['pix_discount_percent'] ?? 0, 0, 100),
            'pix_expires_minutes'  => $this->clampInt($this->data['pix_expires_minutes'] ?? 30, 5, 1440),

            // Urgência
            'urgency_timer_enabled' => (bool) ($this->data['urgency_timer_enabled'] ?? false),
            'urgency_timer_minutes' => $this->clampInt($this->data['urgency_timer_minutes'] ?? 8, 1, 60),
            'urgency_message'       => trim((string) ($this->data['urgency_message'] ?? '')),

            // Downsell
            'downsell_enabled'              => (bool) ($this->data['downsell_enabled'] ?? false),
            'downsell_pix_discount_percent' => $this->clampInt($this->data['downsell_pix_discount_percent'] ?? 10, 0, 100),
            'downsell_title'                => trim((string) ($this->data['downsell_title'] ?? '')),
            'downsell_subtitle'             => trim((string) ($this->data['downsell_subtitle'] ?? '')),
        ];

        // Garante que mensagens não fiquem vazias (mantém defaults úteis)
        if ($payload['urgency_message'] === '') {
            $payload['urgency_message'] = 'Despachamos seu pedido ainda hoje!';
        }
        if ($payload['downsell_title'] === '') {
            $payload['downsell_title'] = 'Seu cartão de crédito foi recusado.';
        }

        CheckoutSetting::current()->update($payload);

        // Refresh do estado local pra UI refletir os valores normalizados
        $this->data = CheckoutSetting::current()->toArray();

        Notification::make()
            ->title('Configurações do checkout salvas!')
            ->success()
            ->send();
    }

    private function clampInt(mixed $value, int $min, int $max): int
    {
        return max($min, min($max, (int) $value));
    }
}