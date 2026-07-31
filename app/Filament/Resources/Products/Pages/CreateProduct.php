<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label('Criar produto')
                ->color('primary')
                ->icon('heroicon-o-check')
                ->action('create'),

            Action::make('cancel')
                ->label('Cancelar')
                ->color('gray')
                ->url(fn () => static::getResource()::getUrl('index')),
        ];
    }

    protected function getFormActions(): array
    {
        return [];
    }
}
