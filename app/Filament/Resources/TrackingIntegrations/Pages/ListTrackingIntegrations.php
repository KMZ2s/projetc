<?php

namespace App\Filament\Resources\TrackingIntegrations\Pages;

use App\Filament\Resources\TrackingIntegrations\TrackingIntegrationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTrackingIntegrations extends ListRecords
{
    protected static string $resource = TrackingIntegrationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nova integração'),
        ];
    }

    public function getTitle(): string
    {
        return 'Pixels e rastreamento';
    }
}
