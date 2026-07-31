<?php

namespace App\Filament\Resources\Coupons\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components;

class CouponForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Components\TextInput::make('code')
                    ->label('Código')
                    ->required()
                    ->unique(ignoreRecord: true),
                Components\Select::make('type')
                    ->label('Tipo')
                    ->options([
                        'percentage' => 'Percentual (%)',
                        'fixed' => 'Valor fixo (R$)',
                    ])
                    ->required(),
                Components\TextInput::make('value')
                    ->label('Valor')
                    ->numeric()
                    ->required(),
                Components\TextInput::make('min_order_value')
                    ->label('Valor mínimo do pedido')
                    ->numeric()
                    ->prefix('R$'),
                Components\TextInput::make('usage_limit')
                    ->label('Limite de usos')
                    ->numeric(),
                Components\TextInput::make('usage_per_customer')
                    ->label('Usos por cliente')
                    ->numeric(),
                Components\DatePicker::make('valid_from')
                    ->label('Válido de'),
                Components\DatePicker::make('valid_to')
                    ->label('Válido até'),
                Components\Select::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Ativo',
                        'inactive' => 'Inativo',
                        'expired' => 'Expirado',
                    ])
                    ->default('active'),
            ]);
    }
}