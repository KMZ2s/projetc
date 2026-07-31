<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-home';
    protected static ?string $navigationLabel = 'Dashboard';
    protected static ?int    $navigationSort  = -1;

    public string  $period   = 'month';
    public ?string $dateFrom = null;
    public ?string $dateTo   = null;

    public function mount(): void
    {
        $this->period   = 'month';
        $this->dateFrom = now()->startOfMonth()->format('Y-m-d');
        $this->dateTo   = now()->format('Y-m-d');

        // Sincroniza widgets com o período inicial
        $this->dispatchToWidgets();
    }

    public function updatedPeriod(string $value): void
    {
        match ($value) {
            'today'  => [$this->dateFrom, $this->dateTo] = [
                now()->format('Y-m-d'),
                now()->format('Y-m-d'),
            ],
            'week'   => [$this->dateFrom, $this->dateTo] = [
                now()->startOfWeek()->format('Y-m-d'),
                now()->format('Y-m-d'),
            ],
            'month'  => [$this->dateFrom, $this->dateTo] = [
                now()->startOfMonth()->format('Y-m-d'),
                now()->format('Y-m-d'),
            ],
            default => null,
        };

        $this->dispatchToWidgets();
    }

    public function updatedDateFrom(): void
    {
        $this->period = 'custom';
        $this->dispatchToWidgets();
    }

    public function updatedDateTo(): void
    {
        $this->period = 'custom';
        $this->dispatchToWidgets();
    }

    /**
     * Despacha evento Livewire para todos os widgets que escutam 'updateDashboardPeriod'.
     * Os widgets recebem o evento e atualizam suas próprias propriedades.
     */
    private function dispatchToWidgets(): void
    {
        $this->dispatch('updateDashboardPeriod', [
            'period'   => $this->period,
            'dateFrom' => $this->dateFrom,
            'dateTo'   => $this->dateTo,
        ]);
    }

    public function getWidgets(): array
    {
        return [
            \App\Filament\Widgets\SalesChartWidget::class,
            \App\Filament\Widgets\RevenueStatsWidget::class,
            \App\Filament\Widgets\TopProductsWidget::class,
            \App\Filament\Widgets\RecentOrdersWidget::class,
        ];
    }

    public function getColumns(): int|array
    {
        return 2;
    }

    protected function getHeaderWidgets(): array
    {
        return [];
    }
}