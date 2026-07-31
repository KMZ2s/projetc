<?php

namespace App\Filament\Resources\Categories\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Components\Select::make('parent_id')
                    ->label('Categoria pai')
                    ->relationship('parent', 'name')
                    ->searchable()
                    ->preload()
                    ->nullable()
                    ->helperText('Deixe vazio para categoria-raiz (aparece no topo do menu).'),
                Components\TextInput::make('name')
                    ->label('Nome')
                    ->required(),
                Components\TextInput::make('slug')
                    ->label('Slug')
                    ->required()
                    ->unique(ignoreRecord: true),
                Components\Textarea::make('description')
                    ->label('Descrição'),
                Components\FileUpload::make('image')
                    ->label('Imagem')
                    ->image()
                    ->disk('public')
                    ->directory('categories')
                    ->visibility('public'),
                Components\TextInput::make('order')
                    ->label('Ordem')
                    ->numeric()
                    ->default(0),
                Components\Toggle::make('show_in_menu')
                    ->label('Exibir no menu principal')
                    ->helperText('Desligue para ocultar do header sem desativar a categoria inteira. Só afeta categorias-raiz.')
                    ->default(true),
                Components\Select::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Ativo',
                        'inactive' => 'Inativo',
                    ])
                    ->default('active')
                    ->required(),
            ]);
    }
}