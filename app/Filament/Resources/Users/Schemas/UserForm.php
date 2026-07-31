<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Components\TextInput::make('name')
                    ->label('Nome completo')
                    ->required(),
                Components\TextInput::make('email')
                    ->label('E-mail')
                    ->email()
                    ->required(),
                Components\TextInput::make('password')
                    ->label('Senha')
                    ->password()
                    ->hiddenOn('edit'), // não mostrar na edição
                Components\TextInput::make('phone')
                    ->label('Telefone'),
                Components\TextInput::make('cpf_cnpj')
                    ->label('CPF/CNPJ'),
                Components\Toggle::make('accepts_marketing')
                    ->label('Aceita marketing'),
                Components\Select::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Ativo',
                        'inactive' => 'Inativo',
                    ])
                    ->default('active'),
            ]);
    }
}