<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Livewire\Attributes\On;

class RevenueStatsWidget extends BaseWidget
{
    public string  $period   = 'month';
    public ?string $dateFrom = null;
    public ?string $dateTo   = null;

    protected static bool $isLazy = false;
    protected int|string|array $columnSpan = 'full';
    protected static ?int $sort = 10;

    #[On('updateDashboardPeriod')]
    public function updatePeriod(array $data): void
    {
        $this->period   = $data['period'];
        $this->dateFrom = $data['dateFrom'];
        $this->dateTo   = $data['dateTo'];
    }

    protected function getStats(): array
    {
        $from = Carbon::parse($this->dateFrom ?? now()->startOfMonth());
        $to   = Carbon::parse($this->dateTo   ?? now())->endOfDay();

        $orders = Order::whereBetween('placed_at', [$from, $to]);

        $revenue      = (clone $orders)->where('payment_status', 'paid')->sum('total');
        $ordersCount  = (clone $orders)->count();
        $avgTicket    = $ordersCount > 0 ? $revenue / $ordersCount : 0;
        $newCustomers = User::where('is_admin', false)
            ->whereBetween('created_at', [$from, $to])
            ->count();

        $fmt = fn ($v) => 'R$ ' . number_format($v, 2, ',', '.');

        return [
            Stat::make('Receita', $fmt($revenue))
                ->description('Pedidos pagos no período')
                ->icon('heroicon-o-currency-dollar')
                ->color('success'),

            Stat::make('Pedidos', number_format($ordersCount))
                ->description('Total de pedidos no período')
                ->icon('heroicon-o-shopping-cart')
                ->color('primary'),

            Stat::make('Ticket médio', $fmt($avgTicket))
                ->description('Receita ÷ pedidos')
                ->icon('heroicon-o-calculator')
                ->color('warning'),

            Stat::make('Novos clientes', number_format($newCustomers))
                ->description('Cadastros no período')
                ->icon('heroicon-o-user-plus')
                ->color('info'),
        ];
    }
}