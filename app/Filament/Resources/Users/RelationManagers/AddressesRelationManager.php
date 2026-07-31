<?php

namespace App\Filament\Resources\Users\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Forms\Components;

class AddressesRelationManager extends RelationManager
{
    protected static string $relationship = 'addresses';

    protected static ?string $recordTitleAttribute = 'street';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Components\Select::make('address_type')
                    ->label('Tipo')
                    ->options([
                        'billing' => 'Cobrança',
                        'shipping' => 'Entrega',
                        'both' => 'Ambos',
                    ])
                    ->required(),
                Components\TextInput::make('zipcode')
                    ->label('CEP')
                    ->required(),
                Components\TextInput::make('street')
                    ->label('Rua')
                    ->required(),
                Components\TextInput::make('number')
                    ->label('Número')
                    ->required(),
                Components\TextInput::make('complement')
                    ->label('Complemento'),
                Components\TextInput::make('neighborhood')
                    ->label('Bairro')
                    ->required(),
                Components\TextInput::make('city')
                    ->label('Cidade')
                    ->required(),
                Components\TextInput::make('state')
                    ->label('UF')
                    ->maxLength(2)
                    ->required(),
                Components\TextInput::make('country')
                    ->label('País')
                    ->default('BR'),
                Components\Toggle::make('is_default')
                    ->label('Padrão'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('street')
                    ->label('Rua'),
                Tables\Columns\TextColumn::make('number')
                    ->label('Número'),
                Tables\Columns\TextColumn::make('city')
                    ->label('Cidade'),
                Tables\Columns\TextColumn::make('address_type')
                    ->label('Tipo'),
                Tables\Columns\IconColumn::make('is_default')
                    ->boolean()
                    ->label('Padrão'),
            ])
            ->filters([])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }
}