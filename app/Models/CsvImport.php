<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CsvImport extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'filename',
        'status',
        'total_rows',
        'processed_rows',
        'error_rows',
        'error_file',
        'zip_file',
        'user_id',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'started_at'  => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getProgressAttribute(): int
    {
        if ($this->total_rows === 0) return 0;
        return (int) round(($this->processed_rows / $this->total_rows) * 100);
    }

    public function isRunning(): bool
    {
        return in_array($this->status, ['pending', 'processing']);
    }
}