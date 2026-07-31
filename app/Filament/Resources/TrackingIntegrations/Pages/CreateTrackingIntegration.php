<?php

namespace App\Filament\Resources\TrackingIntegrations\Pages;

use App\Filament\Resources\TrackingIntegrations\TrackingIntegrationResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTrackingIntegration extends CreateRecord
{
    protected static string $resource = TrackingIntegrationResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return TrackingIntegrationResource::prepareData($data);
    }

    public function getTitle(): string
    {
        return 'Nova integração de rastreamento';
    }
}
