<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Livewire\Attributes\On;

class SalesChartWidget extends ChartWidget
{
    protected ?string $heading = 'Receita por dia';

    protected int|string|array $columnSpan = 1;
    protected static bool $isLazy = false;
    protected static ?int $sort = 20;

    public string  $period   = 'month';
    public ?string $dateFrom = null;
    public ?string $dateTo   = null;

    #[On('updateDashboardPeriod')]
    public function updatePeriod(array $data): void
    {
        $this->period   = $data['period'];
        $this->dateFrom = $data['dateFrom'];
        $this->dateTo   = $data['dateTo'];
    }

    protected function getData(): array
    {
        $from = Carbon::parse($this->dateFrom ?? now()->startOfMonth());
        $to   = Carbon::parse($this->dateTo   ?? now())->endOfDay();

        $rows = Order::where('payment_status', 'paid')
            ->whereBetween('placed_at', [$from, $to])
            ->selectRaw('DATE(placed_at) as day, SUM(total) as total')
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('total', 'day');

        $labels  = [];
        $values  = [];
        $current = $from->copy();

        while ($current->lte($to)) {
            $day      = $current->format('Y-m-d');
            $labels[] = $current->format('d/m');
            $values[] = round((float) ($rows[$day] ?? 0), 2);
            $current->addDay();
        }

        return [
            'datasets' => [
                [
                    'label'           => 'Receita (R$)',
                    'data'            => $values,
                    'borderColor'     => 'rgb(234, 179, 8)',
                    'backgroundColor' => 'rgba(234, 179, 8, 0.08)',
                    'fill'            => true,
                    'tension'         => 0.4,
                    'pointRadius'     => count($values) <= 31 ? 3 : 0,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => false]],
            'scales'  => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks'       => [
                        'callback' => "function(v){ return 'R$ ' + v.toLocaleString('pt-BR', {minimumFractionDigits:2}) }",
                    ],
                ],
            ],
        ];
    }
}