<?php

namespace App\Filament\Pages;

use App\Jobs\ProcessCsvImport;
use App\Models\CsvImport;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\CsvService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\WithFileUploads;

class CsvImportExport extends Page
{
    use WithFileUploads;

    protected string $view = 'filament.pages.csv-import-export';

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-arrow-up-tray';
    }

    public static function getNavigationLabel(): string
    {
        return 'CSV Import/Export';
    }

    public static function getNavigationSort(): ?int
    {
        return 10;
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Ferramentas';
    }

    // -------------------------------------------------------------------------
    // Estado
    // -------------------------------------------------------------------------

    public string $importType = 'products';
    public $csvFile           = null;
    public $zipFile           = null;
    public array $preview     = [];
    public bool $showPreview  = false;

    public function updatedImportType(): void
    {
        $this->reset(['csvFile', 'zipFile', 'preview', 'showPreview']);
    }

    // -------------------------------------------------------------------------
    // Preview
    // -------------------------------------------------------------------------

    public function loadPreview(): void
    {
        if (!$this->csvFile) {
            Notification::make()->title('Selecione um arquivo CSV primeiro.')->warning()->send();
            return;
        }

        try {
            $csv = \League\Csv\Reader::createFromPath($this->csvFile->getRealPath(), 'r');
            $csv->setHeaderOffset(0);

            $headers = $csv->getHeader();
            $rows    = [];
            foreach ($csv->getRecords() as $record) {
                $rows[] = $record;
                if (count($rows) >= 5) break;
            }

            $this->preview     = compact('headers', 'rows');
            $this->showPreview = true;
        } catch (\Throwable $e) {
            Notification::make()->title('Erro ao ler o CSV: ' . $e->getMessage())->danger()->send();
        }
    }

    // -------------------------------------------------------------------------
    // Importar
    // -------------------------------------------------------------------------

    public function import(): void
    {
        if (!$this->csvFile) {
            Notification::make()->title('Selecione um arquivo CSV.')->warning()->send();
            return;
        }

        $csvPath = $this->csvFile->store('imports', 'local');
        $zipPath = $this->zipFile ? $this->zipFile->store('imports', 'local') : null;

        $import = CsvImport::create([
            'type'     => $this->importType,
            'filename' => $csvPath,
            'zip_file' => $zipPath,
            'status'   => 'pending',
            'user_id'  => Auth::id(),
        ]);

        ProcessCsvImport::dispatch($import);

        $this->reset(['csvFile', 'zipFile', 'showPreview', 'preview']);

        Notification::make()
            ->title('Importação iniciada! Acompanhe o progresso abaixo.')
            ->success()
            ->send();
    }

    // -------------------------------------------------------------------------
    // Exportar
    // -------------------------------------------------------------------------

    public function export(string $type): mixed
    {
        $service = app(CsvService::class);

        $path = match ($type) {
            'products'  => $service->exportProducts(),
            'customers' => $service->exportCustomers(),
            'orders'    => $service->exportOrders(),
            default     => throw new \InvalidArgumentException("Tipo inválido: $type"),
        };

        return Storage::download($path, basename($path));
    }

    // -------------------------------------------------------------------------
    // Template
    // -------------------------------------------------------------------------

    public function downloadTemplate(string $type): mixed
    {
        $columns  = app(CsvService::class)->getTemplateColumns($type);
        $filename = "template_{$type}.csv";
        $content  = implode(',', $columns) . "\n";

        return response()->streamDownload(fn () => print($content), $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    // -------------------------------------------------------------------------
    // Histórico
    // -------------------------------------------------------------------------

    public function getImportsProperty()
    {
        return CsvImport::latest()->limit(20)->get();
    }

    public function getProductsCountProperty(): int
    {
        return Product::count();
    }

    public function getCustomersCountProperty(): int
    {
        return User::where('is_admin', false)->count();
    }

    public function getOrdersCountProperty(): int
    {
        return Order::count();
    }
}