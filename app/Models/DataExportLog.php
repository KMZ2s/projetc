<?php

namespace App\Models;

use App\Services\DataExportService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro de cada exportação realizada via /admin/data.
 *
 * Não é auditoria completa (essa fica reservada pra Fase 9). É só o
 * tracking mínimo pra mostrar "última exportação" na Page e ter um
 * histórico básico se o operador quiser revisar depois.
 */
class DataExportLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'dataset',
        'format',
        'filters',
        'exported_by_user_id',
        'exported_at',
    ];

    protected $casts = [
        'filters'     => 'array',
        'exported_at' => 'datetime',
    ];

    public function exportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'exported_by_user_id');
    }

    /**
     * Label legível do dataset (busca no DataExportService::DATASETS).
     * Fallback no value cru se o dataset for desconhecido — pode
     * acontecer se um log antigo aponta pra dataset removido.
     */
    public function datasetLabel(): string
    {
        return DataExportService::DATASETS[$this->dataset] ?? $this->dataset;
    }

    public function formatLabel(): string
    {
        return DataExportService::FORMATS[$this->format] ?? strtoupper($this->format);
    }
}