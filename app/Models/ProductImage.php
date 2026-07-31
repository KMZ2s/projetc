<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductImage extends Model
{
    use HasFactory;

    public const SOURCE_UPLOAD = 'upload';
    public const SOURCE_EXTERNAL_URL = 'external_url';

    protected $fillable = [
        'product_id',
        'variant_id',
        'src',
        'source_type',
        'alt',
        'position',
    ];

    protected $casts = [
        'position' => 'integer',
    ];

    protected $appends = [
        'url',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variant()
    {
        return $this->belongsTo(Variant::class);
    }

    public function getUrlAttribute(): ?string
    {
        return $this->publicUrl();
    }

    public function publicUrl(): ?string
    {
        $src = static::normalizeSrc($this->src);

        if (!$src) {
            return null;
        }

        if ($this->isExternal()) {
            return $src;
        }

        return Storage::disk('public')->url($src);
    }

    public function isExternal(): bool
    {
        $src = static::normalizeSrc($this->src);

        return $this->source_type === self::SOURCE_EXTERNAL_URL
            || static::isExternalUrl($src);
    }

    public function isUpload(): bool
    {
        return !$this->isExternal();
    }

    public static function detectSourceType(?string $src): string
    {
        return static::isExternalUrl($src)
            ? self::SOURCE_EXTERNAL_URL
            : self::SOURCE_UPLOAD;
    }

    public static function normalizeSrc(?string $src): ?string
    {
        if ($src === null) {
            return null;
        }

        $src = trim($src);

        return $src !== '' ? $src : null;
    }

    public static function isExternalUrl(?string $src): bool
    {
        $src = static::normalizeSrc($src);

        if (!$src) {
            return false;
        }

        if (!filter_var($src, FILTER_VALIDATE_URL)) {
            return false;
        }

        return Str::startsWith(Str::lower($src), ['http://', 'https://']);
    }
}