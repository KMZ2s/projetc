<?php

namespace App\Filament\Resources\Products\Tables;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns;
use Filament\Tables\Filters;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Columns\TextColumn::make('name')
                    ->label('Nome')
                    ->searchable(),
                Columns\TextColumn::make('category.name')
                    ->label('Categoria'),
                Columns\TextColumn::make('price')
                    ->label('Preço')
                    ->money('BRL'),
                Columns\IconColumn::make('featured')
                    ->label('Destaque')
                    ->boolean(),
                Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->colors([
                        'success' => 'active',
                        'danger' => 'inactive',
                        'warning' => 'draft',
                    ]),
            ])
            ->filters([
                Filters\SelectFilter::make('category')
                    ->relationship('category', 'name'),
                Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Ativo',
                        'inactive' => 'Inativo',
                        'draft' => 'Rascunho',
                    ]),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
            ]);
    }
}