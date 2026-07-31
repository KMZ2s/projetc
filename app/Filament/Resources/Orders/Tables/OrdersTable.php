<?php

namespace App\Filament\Resources\Orders\Tables;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns;
use Filament\Tables\Filters;
use Filament\Actions\EditAction;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Columns\TextColumn::make('order_number')
                    ->label('Número')
                    ->searchable(),
                Columns\TextColumn::make('customer_name')
                    ->label('Cliente')
                    ->state(fn ($record) => $record->customer_name ?? $record->user?->display_name ?? '—'),
                Columns\TextColumn::make('total')
                    ->label('Total')
                    ->money('BRL'),
                Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                Columns\TextColumn::make('payment_status')
                    ->label('Pagamento')
                    ->badge(),
                Columns\TextColumn::make('placed_at')
                    ->label('Data')
                    ->dateTime(),
            ])
            ->filters([
                Filters\SelectFilter::make('status'),
                Filters\SelectFilter::make('payment_status'),
            ])
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([]);
    }
}
