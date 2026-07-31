<?php

namespace App\Filament\Resources\Users\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Schemas\Schema;

class DevicesRelationManager extends RelationManager
{
    protected static string $relationship = 'devices';

    protected static ?string $title = 'Dispositivos';

    public function form(Schema $schema): Schema
    {
        // Só leitura — devices são criados automaticamente no checkout
        return $schema->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order.order_number')
                    ->label('Pedido')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('browser_language')
                    ->label('Idioma')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('screen_width')
                    ->label('Resolução')
                    ->formatStateUsing(fn ($state, $record) => $state
                        ? "{$state}×{$record->screen_height}"
                        : '—'),
                Tables\Columns\TextColumn::make('user_agent')
                    ->label('User Agent')
                    ->limit(50)
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Data')
                    ->dateTime('d/m/Y H:i'),
            ])
            ->filters([])
            ->headerActions([]) // somente leitura
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }
}