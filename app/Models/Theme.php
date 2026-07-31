<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Theme extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'directory',
        'version',
        'author',
        'is_active',
        'settings_data',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'settings_data' => 'array',
    ];
}