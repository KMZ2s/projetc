<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\ProductImage;
use Illuminate\Support\Facades\Storage;

class Variant extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'sku',
        'barcode',
        'price',
        'compare_at_price',
        'cost_price',
        'weight',
        'stock_quantity',
        'image',
        'options',
        'position',
    ];

    protected $casts = [
        'price'            => 'decimal:2',
        'compare_at_price' => 'decimal:2',
        'cost_price'       => 'decimal:2',
        'weight'           => 'decimal:2',
        'stock_quantity'   => 'integer',
        'options'          => 'array',
        'position'         => 'integer',
    ];

    // -------------------------------------------------------------------------
    // Relacionamentos
    // -------------------------------------------------------------------------

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Imagens específicas desta variação. Quando vazio, o front cai em
     * fallback nas imagens do produto pai.
     */
    public function images()
    {
        return $this->hasMany(ProductImage::class)->orderBy('position');
    }

    // -------------------------------------------------------------------------
    // Helpers de imagem (mesmo contrato do Product, escopado por variação)
    // -------------------------------------------------------------------------

    public function getImagesForUpload(): array
    {
        return $this->images()
            ->where('source_type', ProductImage::SOURCE_UPLOAD)
            ->orderBy('position')
            ->pluck('src')
            ->mapWithKeys(fn ($src) => [$src => $src])
            ->toArray();
    }

    /**
     * Sincroniza as imagens da variação.
     *
     * Aceita tanto caminhos locais quanto URLs externas.
     */
    public function syncImages(array $sources): void
    {
        $sources = collect($sources)
            ->map(fn ($src) => ProductImage::normalizeSrc($src))
            ->filter()
            ->values()
            ->all();

        $existingImages = $this->images()->get();

        $toDelete = $existingImages
            ->reject(fn (ProductImage $image) => in_array($image->src, $sources, true));

        foreach ($toDelete as $image) {
            $src = $image->src;
            $shouldDeleteFile = $image->isUpload();

            $image->delete();

            if ($shouldDeleteFile && Storage::disk('public')->exists($src)) {
                Storage::disk('public')->delete($src);
            }
        }

        foreach ($sources as $position => $src) {
            $this->images()->updateOrCreate(
                ['src' => $src],
                [
                    'source_type' => ProductImage::detectSourceType($src),
                    'position' => $position,
                    'product_id' => $this->product_id,
                ]
            );
        }
    }

    public function getExternalImagesForForm(): array
    {
        return $this->images()
            ->where('source_type', ProductImage::SOURCE_EXTERNAL_URL)
            ->orderBy('position')
            ->get(['src', 'alt'])
            ->map(fn (ProductImage $image) => [
                'src' => $image->src,
                'alt' => $image->alt,
            ])
            ->toArray();
    }

    public function syncUploadedImages(array $paths): void
    {
        $this->syncImagesForSourceType(
            sources: $paths,
            sourceType: ProductImage::SOURCE_UPLOAD
        );
    }

    public function syncExternalImages(array $rows): void
    {
        $rows = collect($rows)
            ->map(fn ($row) => [
                'src' => ProductImage::normalizeSrc($row['src'] ?? null),
                'alt' => trim($row['alt'] ?? '') ?: null,
            ])
            ->filter(fn ($row) => ProductImage::isExternalUrl($row['src']))
            ->values()
            ->all();

        $sources = collect($rows)->pluck('src')->all();

        $existingImages = $this->images()
            ->where('source_type', ProductImage::SOURCE_EXTERNAL_URL)
            ->get();

        $toDelete = $existingImages
            ->reject(fn (ProductImage $image) => in_array($image->src, $sources, true));

        foreach ($toDelete as $image) {
            $image->delete();
        }

        $uploadCount = $this->images()
            ->where('source_type', ProductImage::SOURCE_UPLOAD)
            ->count();

        foreach ($rows as $index => $row) {
            $this->images()->updateOrCreate(
                ['src' => $row['src']],
                [
                    'product_id' => $this->product_id,
                    'source_type' => ProductImage::SOURCE_EXTERNAL_URL,
                    'alt' => $row['alt'],
                    'position' => $uploadCount + $index,
                ]
            );
        }
    }

    private function syncImagesForSourceType(array $sources, string $sourceType): void
    {
        $sources = collect($sources)
            ->map(fn ($src) => ProductImage::normalizeSrc($src))
            ->filter()
            ->values()
            ->all();

        $existingImages = $this->images()
            ->where('source_type', $sourceType)
            ->get();

        $toDelete = $existingImages
            ->reject(fn (ProductImage $image) => in_array($image->src, $sources, true));

        foreach ($toDelete as $image) {
            $src = $image->src;
            $shouldDeleteFile = $image->isUpload();

            $image->delete();

            if ($shouldDeleteFile && Storage::disk('public')->exists($src)) {
                Storage::disk('public')->delete($src);
            }
        }

        foreach ($sources as $position => $src) {
            $this->images()->updateOrCreate(
                ['src' => $src],
                [
                    'product_id' => $this->product_id,
                    'source_type' => $sourceType,
                    'position' => $position,
                ]
            );
        }
    }
}
