<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Columns\TextColumn::make('name')
                    ->label('Nome')
                    ->searchable(),
                Columns\TextColumn::make('email')
                    ->label('E-mail')
                    ->searchable(),
                Columns\TextColumn::make('phone')
                    ->label('Telefone'),
                Columns\TextColumn::make('cpf_cnpj')
                    ->label('CPF/CNPJ'),
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