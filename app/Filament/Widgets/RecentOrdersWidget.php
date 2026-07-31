<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Livewire\Attributes\On;

class RecentOrdersWidget extends BaseWidget
{
    protected static ?string $heading = 'Pedidos recentes';

    protected int|string|array $columnSpan = 1;

    protected static bool $isLazy = false;

    protected static ?int $sort = 30;

    public string  $period   = 'month';
    public ?string $dateFrom = null;
    public ?string $dateTo   = null;

    /**
     * Sync com o seletor de período do Dashboard.
     * Mesma assinatura usada nos outros widgets (RevenueStats, SalesChart, etc.).
     */
    #[On('updateDashboardPeriod')]
    public function updatePeriod(array $data): void
    {
        $this->period   = $data['period'];
        $this->dateFrom = $data['dateFrom'];
        $this->dateTo   = $data['dateTo'];
    }

    public function table(Table $table): Table
    {
        $from = Carbon::parse($this->dateFrom ?? now()->startOfMonth());
        $to   = Carbon::parse($this->dateTo   ?? now())->endOfDay();

        return $table
            ->query(
                fn (): Builder => Order::query()
                    ->with('user')
                    ->where('placed_at', '>=', $from)
                    ->where('placed_at', '<=', $to)
            )
            ->defaultSort('placed_at', 'desc')
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->label('Pedido')
                    ->prefix('#')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('customer_name')
                    ->label('Cliente')
                    ->state(fn (Order $record) => $record->customer_name ?? $record->user?->display_name)
                    ->placeholder('—')
                    ->limit(20)
                    ->searchable(),

                Tables\Columns\TextColumn::make('payment_status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'paid'     => 'success',
                        'pending'  => 'warning',
                        'failed'   => 'danger',
                        'refunded' => 'gray',
                        default    => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'paid'     => 'Pago',
                        'pending'  => 'Pendente',
                        'failed'   => 'Recusado',
                        'refunded' => 'Estornado',
                        default    => ucfirst((string) $state),
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->formatStateUsing(fn ($state) => 'R$ ' . number_format((float) $state, 2, ',', '.'))
                    ->alignEnd()
                    ->sortable(),
            ])
            ->emptyStateHeading('Nenhum pedido no período')
            ->emptyStateDescription('Quando entrar um pedido, ele aparece aqui.')
            ->emptyStateIcon('heroicon-o-shopping-bag');
    }
}
