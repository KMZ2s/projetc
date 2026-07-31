<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Salvar alterações')
                ->color('primary')
                ->icon('heroicon-o-check')
                ->action('save'),

            Action::make('cancel')
                ->label('Cancelar')
                ->color('gray')
                ->url(fn () => static::getResource()::getUrl('index')),

            DeleteAction::make(),
        ];
    }

    /**
     * Esconde os botões "Save changes" e "Cancel" do rodapé do form —
     * estão duplicados no header agora.
     */
    protected function getFormActions(): array
    {
        return [];
    }
}
