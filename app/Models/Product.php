<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\ProductImage;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'description',
        'short_description',
        'price',
        'compare_at_price',
        'cost_price',
        'sku',
        'barcode',
        'weight',
        'width',
        'height',
        'depth',
        'status',
        'featured',
        'stock_quantity',
    ];

    protected $casts = [
        'price'            => 'decimal:2',
        'compare_at_price' => 'decimal:2',
        'cost_price'       => 'decimal:2',
        'weight'           => 'decimal:2',
        'width'            => 'decimal:2',
        'height'           => 'decimal:2',
        'depth'            => 'decimal:2',
        'featured'         => 'boolean',
        'stock_quantity'   => 'integer',
    ];

    // -------------------------------------------------------------------------
    // Relacionamentos
    // -------------------------------------------------------------------------

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function variants()
    {
        return $this->hasMany(Variant::class)->orderBy('position');
    }

    /**
     * Imagens gerais do produto, sem vínculo com variação.
     *
     * Importante: imagens específicas de cor/tamanho ficam em Variant::images().
     * Manter este relacionamento filtrado evita que a galeria inicial e os cards
     * exibam imagens de todas as variações misturadas.
     */
    public function images()
    {
        return $this->hasMany(ProductImage::class)
            ->whereNull('variant_id')
            ->orderBy('position');
    }

    /**
     * Todas as imagens do produto, incluindo imagens específicas de variações.
     * Use apenas em rotinas internas/auditoria quando precisar do conjunto bruto.
     */
    public function allImages()
    {
        return $this->hasMany(ProductImage::class)->orderBy('position');
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    // -------------------------------------------------------------------------
    // Helpers de imagem
    // -------------------------------------------------------------------------

    /**
     * Retorna SOMENTE as imagens locais do produto (variant_id IS NULL) no formato
     * que o FileUpload do Filament espera.
     *
     * URLs externas serão tratadas em campos separados no ProductForm.
     */
    public function getImagesForUpload(): array
    {
        return $this->images()
            ->whereNull('variant_id')
            ->where('source_type', ProductImage::SOURCE_UPLOAD)
            ->orderBy('position')
            ->pluck('src')
            ->mapWithKeys(fn ($src) => [$src => $src])
            ->toArray();
    }
    /**
     * Sincroniza as imagens DO PRODUTO (sem variant_id) com a tabela
     * product_images.
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

        $existingImages = $this->images()
            ->whereNull('variant_id')
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
                [
                    'src' => $src,
                    'variant_id' => null,
                ],
                [
                    'source_type' => ProductImage::detectSourceType($src),
                    'position' => $position,
                ]
            );
        }
    }

    public function getExternalImagesForForm(): array
    {
        return $this->images()
            ->whereNull('variant_id')
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
            sourceType: ProductImage::SOURCE_UPLOAD,
            variantId: null
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
            ->whereNull('variant_id')
            ->where('source_type', ProductImage::SOURCE_EXTERNAL_URL)
            ->get();

        $toDelete = $existingImages
            ->reject(fn (ProductImage $image) => in_array($image->src, $sources, true));

        foreach ($toDelete as $image) {
            $image->delete();
        }

        $uploadCount = $this->images()
            ->whereNull('variant_id')
            ->where('source_type', ProductImage::SOURCE_UPLOAD)
            ->count();

        foreach ($rows as $index => $row) {
            $this->images()->updateOrCreate(
                [
                    'src' => $row['src'],
                    'variant_id' => null,
                ],
                [
                    'source_type' => ProductImage::SOURCE_EXTERNAL_URL,
                    'alt' => $row['alt'],
                    'position' => $uploadCount + $index,
                ]
            );
        }
    }

    private function syncImagesForSourceType(array $sources, string $sourceType, ?int $variantId = null): void
    {
        $sources = collect($sources)
            ->map(fn ($src) => ProductImage::normalizeSrc($src))
            ->filter()
            ->values()
            ->all();

        $query = $this->images()->where('source_type', $sourceType);

        if ($variantId === null) {
            $query->whereNull('variant_id');
        } else {
            $query->where('variant_id', $variantId);
        }

        $existingImages = $query->get();

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
                [
                    'src' => $src,
                    'variant_id' => $variantId,
                ],
                [
                    'source_type' => $sourceType,
                    'position' => $position,
                ]
            );
        }
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeFeatured($query)
    {
        return $query->where('featured', true);
    }
}