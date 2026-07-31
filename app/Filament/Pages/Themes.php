<?php

namespace App\Filament\Pages;

use App\Services\ThemeManager;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class Themes extends Page
{
    protected string $view = 'filament.pages.themes';

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-swatch';
    }

    public static function getNavigationLabel(): string
    {
        return 'Temas';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Ferramentas';
    }

    public static function getNavigationSort(): ?int
    {
        return 20;
    }

    // -------------------------------------------------------------------------
    // Estado
    // -------------------------------------------------------------------------

    public string $activeTab    = 'themes';
    public string $editingTheme = '';
    public array  $formData     = [];

    public function mount(): void
    {
        $this->editingTheme = app(ThemeManager::class)->getActive();
        $this->loadFormData();
    }

    // -------------------------------------------------------------------------
    // Header Action — abre modal com FileUpload nativo do Filament
    // -------------------------------------------------------------------------

    
    protected function getHeaderActions(): array
    {
        return [
            Action::make('installTheme')
                ->label('Instalar tema (.zip)')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('warning')
                ->modalHeading('Instalar novo tema')
                ->modalDescription('Envie um arquivo .zip com a estrutura do tema. A pasta raiz do ZIP será o nome do tema.')
                ->modalSubmitActionLabel('Instalar')
                ->form([
                    FileUpload::make('zipFile')
                        ->label('Arquivo ZIP do tema')
                        ->acceptedFileTypes([
                            'application/zip',
                            'application/x-zip-compressed',
                            'application/octet-stream',
                        ])
                        ->disk('local')
                        ->directory('theme-uploads')
                        ->visibility('private')
                        ->maxSize(51200)
                        ->required(),
                ])
                ->action(function (array $data) {
                    $storagePath = \Illuminate\Support\Facades\Storage::disk('local')
                        ->path($data['zipFile']);

                    if (!file_exists($storagePath)) {
                        Notification::make()
                            ->title('Arquivo não encontrado. Tente novamente.')
                            ->danger()
                            ->send();
                        return;
                    }

                    try {
                        $result = app(ThemeManager::class)->installFromZip($storagePath);

                        Notification::make()
                            ->title($result['message'])
                            ->{$result['success'] ? 'success' : 'danger'}()
                            ->send();
                    } finally {
                        @unlink($storagePath);
                    }
                }),
        ];
    }

    // -------------------------------------------------------------------------
    // Listar temas
    // -------------------------------------------------------------------------

    public function getThemesProperty(): array
    {
        return app(ThemeManager::class)->list();
    }

    // -------------------------------------------------------------------------
    // Ativar tema
    // -------------------------------------------------------------------------

    public function activate(string $themeName): void
    {
        app(ThemeManager::class)->setActive($themeName);

        Notification::make()
            ->title("Tema '{$themeName}' ativado com sucesso!")
            ->success()
            ->send();
    }

    // -------------------------------------------------------------------------
    // Remover tema
    // -------------------------------------------------------------------------

    public function delete(string $themeName): void
    {
        $result = app(ThemeManager::class)->delete($themeName);

        Notification::make()
            ->title($result['message'])
            ->{$result['success'] ? 'success' : 'danger'}()
            ->send();
    }

    // -------------------------------------------------------------------------
    // Editor visual
    // -------------------------------------------------------------------------

    public function getSchemaProperty(): array
    {
        $schema = app(ThemeManager::class)->getSchema($this->editingTheme);

    	// Desempacota wrapper {name, version, settings: [grupos]} se presente.
    	// Schemas antigos que já vêm como lista de grupos passam direto.
    	return $schema['settings'] ?? $schema;
    }

    private function loadFormData(): void
    {
        $this->formData = app(ThemeManager::class)->getSettings($this->editingTheme);
    }

    public function updatedEditingTheme(): void
    {
        $this->loadFormData();
    }

    public function saveSettings(): void
    {
        app(ThemeManager::class)->saveSettings($this->formData, $this->editingTheme);

        Notification::make()
            ->title('Configurações salvas!')
            ->success()
            ->send();
    }
}
