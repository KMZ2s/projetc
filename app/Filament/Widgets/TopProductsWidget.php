<?php

namespace App\Filament\Widgets;

use App\Models\OrderItem;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Livewire\Attributes\On;

class TopProductsWidget extends BaseWidget
{
    protected static ?string $heading = 'Produtos mais vendidos';

    protected int|string|array $columnSpan = 'full';
    protected static bool $isLazy = false;
    protected static ?int $sort = 40;

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

    public function table(Table $table): Table
    {
        $from = Carbon::parse($this->dateFrom ?? now()->startOfMonth());
        $to   = Carbon::parse($this->dateTo   ?? now())->endOfDay();

        return $table
            ->query(
                OrderItem::query()
                    ->whereHas('order', fn (Builder $q) => $q
                        ->where('payment_status', 'paid')
                        ->whereBetween('placed_at', [$from, $to])
                    )
                    ->selectRaw('product_name, SUM(quantity) as total_qty, SUM(total_price) as total_revenue')
                    ->groupBy('product_name')
                    ->orderByDesc('total_qty')
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('product_name')
                    ->label('Produto')
                    ->searchable(),
                Tables\Columns\TextColumn::make('total_qty')
                    ->label('Qtd vendida')
                    ->numeric()
                    ->alignEnd()
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_revenue')
                    ->label('Receita')
                    ->formatStateUsing(fn ($state) => 'R$ ' . number_format($state, 2, ',', '.'))
                    ->alignEnd()
                    ->sortable(),
            ])
            ->paginated(false);
    }
}