<?php

namespace App\Jobs;

use App\Models\CsvImport;
use App\Services\CsvService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessCsvImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    public int $tries   = 1;

    public function __construct(
        public CsvImport $import
    ) {}

    public function handle(CsvService $csv): void
    {
        try {
            match ($this->import->type) {
                'products'  => $csv->importProducts($this->import, $this->import->zip_file),
                'customers' => $csv->importCustomers($this->import),
                'stock'     => $csv->importStock($this->import),
            };
        } catch (\Throwable $e) {
            $this->import->update([
                'status'      => 'failed',
                'finished_at' => now(),
            ]);
            throw $e;
        }
    }
}