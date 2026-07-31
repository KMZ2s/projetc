<?php

namespace App\Filament\Resources\TrackingIntegrations\Pages;

use App\Filament\Resources\TrackingIntegrations\TrackingIntegrationResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTrackingIntegration extends EditRecord
{
    protected static string $resource = TrackingIntegrationResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return TrackingIntegrationResource::prepareData($data, $this->record);
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    public function getTitle(): string
    {
        return 'Editar integração de rastreamento';
    }
}
