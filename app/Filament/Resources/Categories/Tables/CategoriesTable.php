<?php

namespace App\Filament\Resources\Categories\Tables;

use App\Models\Category;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Illuminate\Database\Eloquent\Builder;

class CategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Listagem em formato de árvore: pais primeiro, filhas
            // imediatamente abaixo do respectivo pai.
            //
            // COALESCE(parent_id, id) agrupa cada pai com suas filhas
            // pelo mesmo valor (ID do pai). Dentro do grupo, a expressão
            // `parent_id IS NULL DESC` força o pai (NULL → 1) a aparecer
            // antes das filhas (não-NULL → 0).
            //
            // Compatível com SQLite (dev) e MySQL (prod). PostgreSQL tem
            // ordenação NULL diferente, mas o projeto não usa.
            //
            // Quando o usuário pesquisa, a indentação visual do nome se
            // mantém — não é o cenário ideal mas é defensável e simples.
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->orderByRaw('COALESCE(parent_id, id) ASC')
                ->orderByRaw('parent_id IS NULL DESC')
                ->orderBy('order')
                ->orderBy('name'))
            ->defaultPaginationPageOption(50)
            ->columns([
                Columns\TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    // Indenta visualmente filhas com glifo └─.
                    // Usa nbsp pra recuo consistente em fontes proporcionais.
                    ->formatStateUsing(fn (string $state, Category $record): string =>
                        $record->parent_id !== null
                            ? "\u{00A0}\u{00A0}\u{00A0}└─\u{00A0}{$state}"
                            : $state
                    ),
                Columns\TextColumn::make('parent.name')
                    ->label('Pai')
                    ->placeholder('—'),
                Columns\TextColumn::make('order')
                    ->label('Ordem'),
                Columns\IconColumn::make('show_in_menu')
                    ->label('No menu')
                    ->boolean(),
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
