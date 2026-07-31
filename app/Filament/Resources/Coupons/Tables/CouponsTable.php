<?php

namespace App\Filament\Resources\Coupons\Tables;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
class CouponsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Columns\TextColumn::make('code')
                    ->label('Código')
                    ->searchable(),
                Columns\TextColumn::make('type')
                    ->label('Tipo'),
                Columns\TextColumn::make('value')
                    ->label('Valor'),
                Columns\TextColumn::make('valid_to')
                    ->label('Válido até')
                    ->date(),
                Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
            ])
            ->filters([])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
            ]);
    }
}