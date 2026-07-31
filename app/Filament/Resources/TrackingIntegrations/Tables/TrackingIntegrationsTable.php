<?php

namespace App\Filament\Resources\TrackingIntegrations\Tables;

use App\Models\TrackingIntegration;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns;
use Filament\Tables\Filters;
use Filament\Tables\Table;

class TrackingIntegrationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('position')
            ->emptyStateHeading('Nenhuma integração cadastrada')
            ->emptyStateDescription('Cadastre Meta, TikTok, Google ou UTMify para começar a rastrear o funil.')
            ->columns([
                Columns\TextColumn::make('name')
                    ->label('Integração')
                    ->searchable()
                    ->sortable(),

                Columns\TextColumn::make('provider')
                    ->label('Provedor')
                    ->formatStateUsing(fn (string $state): string => TrackingIntegration::PROVIDERS[$state] ?? $state)
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'meta' => 'info',
                        'tiktok' => 'gray',
                        'ga4', 'google_ads' => 'warning',
                        'utmify' => 'success',
                        default => 'gray',
                    })
                    ->sortable(),

                Columns\TextColumn::make('public_id')
                    ->label('ID público')
                    ->placeholder('API / sem ID')
                    ->copyable()
                    ->searchable(),

                Columns\TextColumn::make('channels')
                    ->label('Canais')
                    ->state(fn (TrackingIntegration $record): string => match (true) {
                        $record->browser_enabled && $record->server_enabled => 'Navegador + API',
                        $record->server_enabled => 'API',
                        $record->browser_enabled => 'Navegador',
                        default => 'Nenhum',
                    })
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Nenhum' ? 'danger' : 'gray'),

                Columns\TextColumn::make('scope')
                    ->label('Escopo')
                    ->state(fn (TrackingIntegration $record): string => match ($record->scope_mode) {
                        'include' => 'Incluir '.count($record->product_ids ?? []).' produto(s)',
                        'exclude' => 'Excluir '.count($record->product_ids ?? []).' produto(s)',
                        default => 'Toda a loja',
                    })
                    ->badge()
                    ->color(fn (TrackingIntegration $record): string => match ($record->scope_mode) {
                        'include' => 'info',
                        'exclude' => 'warning',
                        default => 'gray',
                    }),

                Columns\TextColumn::make('enabled_events')
                    ->label('Eventos')
                    ->state(fn (TrackingIntegration $record): int => count(array_filter($record->enabledEvents())))
                    ->suffix(' ativos'),

                Columns\IconColumn::make('browser_enabled')
                    ->label('Browser')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                Columns\IconColumn::make('server_enabled')
                    ->label('API')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                Columns\IconColumn::make('is_active')
                    ->label('Ativa')
                    ->boolean(),

                Columns\TextColumn::make('position')
                    ->label('Ordem')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Columns\TextColumn::make('updated_at')
                    ->label('Atualizada')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filters\SelectFilter::make('provider')
                    ->label('Provedor')
                    ->options(TrackingIntegration::PROVIDERS),

                Filters\SelectFilter::make('scope_mode')
                    ->label('Escopo')
                    ->options([
                        'all' => 'Toda a loja',
                        'include' => 'Incluir produtos',
                        'exclude' => 'Excluir produtos',
                    ]),

                Filters\TernaryFilter::make('is_active')
                    ->label('Ativa'),

                Filters\TernaryFilter::make('browser_enabled')
                    ->label('Navegador'),

                Filters\TernaryFilter::make('server_enabled')
                    ->label('Servidor / API'),
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
