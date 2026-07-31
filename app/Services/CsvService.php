<?php

namespace App\Services;

use App\Models\Category;
use App\Models\CsvImport;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use App\Models\Variant;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\Csv\Reader;
use League\Csv\Writer;

class CsvService
{
    // =========================================================================
    // Colunas esperadas por tipo (padrão Shopify)
    // =========================================================================

    const PRODUCT_COLUMNS = [
    	'Handle', 'Title', 'Body (HTML)', 'Type', 'Tags', 'Published',
    	'Option1 Name', 'Option1 Value', 'Option2 Name', 'Option2 Value',
    	'Variant SKU', 'Variant Grams', 'Variant Inventory Qty',
    	'Variant Price', 'Variant Compare At Price',
    	'Variant Barcode', 'Image Src', 'Image Position', 'Image Alt Text',
    	'Variant Image',
    	'SEO Title', 'SEO Description', 'Status', 'Category',
    ];

    const CUSTOMER_COLUMNS = [
        'First Name', 'Last Name', 'Email', 'Phone',
        'Accepts Email Marketing', 'Tags', 'Note',
    ];

    const STOCK_COLUMNS = [
        'Handle', 'Variant SKU', 'Variant Inventory Qty',
    ];

    // =========================================================================
    // Preview
    // =========================================================================

    public function preview(string $path): array
    {
        $csv = Reader::createFromPath(Storage::path($path), 'r');
        $csv->setHeaderOffset(0);

        $headers = $csv->getHeader();
        $rows    = [];

        foreach ($csv->getRecords() as $record) {
            $rows[] = $record;
            if (count($rows) >= 5) break;
        }

        return compact('headers', 'rows');
    }

    // =========================================================================
    // IMPORTAÇÃO — Produtos
    // =========================================================================

    public function importProducts(CsvImport $import, ?string $zipPath = null): void
    {
        $csv = Reader::createFromPath(Storage::path($import->filename), 'r');
        $csv->setHeaderOffset(0);

        $records = iterator_to_array($csv->getRecords());
        $errors  = [];
        $current = null;

        $import->update([
            'status'     => 'processing',
            'total_rows' => count($records),
            'started_at' => now(),
        ]);

        $imageMap = $zipPath ? $this->extractZip($zipPath) : [];

        foreach ($records as $row) {
            try {
                $handle = trim($row['Handle'] ?? '');

                if (empty($handle)) {
                    $errors[] = array_merge($row, ['_error' => 'Handle vazio']);
                    $import->increment('error_rows');
                    continue;
                }

                $isFirstRow = !empty(trim($row['Title'] ?? ''));

                if ($isFirstRow) {
                    $current = Product::updateOrCreate(
                        ['slug' => $handle],
                        $this->mapProductFields($row)
                    );
                } elseif (!$current || $current->slug !== $handle) {
                    $current = Product::where('slug', $handle)->first();
                }

                if (!$current) {
                    $errors[] = array_merge($row, ['_error' => 'Produto não encontrado para este Handle']);
                    $import->increment('error_rows');
                    continue;
                }

                $variant = null;
                $sku = trim($row['Variant SKU'] ?? '');

                if ($sku) {
                    $variant = Variant::updateOrCreate(
                        ['sku' => $sku],
                        [
                            'product_id'       => $current->id,
                            'price'            => $this->parseDecimal($row['Variant Price'] ?? 0),
                            'compare_at_price' => $this->parseDecimal($row['Variant Compare At Price'] ?? null) ?: null,
                            'stock_quantity'   => (int) ($row['Variant Inventory Qty'] ?? 0),
                            'barcode'          => $row['Variant Barcode'] ?? null,
                            'weight'           => $this->parseDecimal($row['Variant Grams'] ?? null) ?: null,
                            'options'          => $this->mapOptions($row),
                            'position'         => Variant::where('product_id', $current->id)->count(),
                        ]
                    );
                }

                $imageSrc = trim($row['Image Src'] ?? '');
                if ($imageSrc) {
                    $this->handleProductImage($current, $imageSrc, $row, $imageMap);
                }

                $variantImage = trim($row['Variant Image'] ?? '');
                if ($variant && $variantImage) {
                    $this->handleVariantImage($variant, $variantImage, $row, $imageMap);
                }

                $import->increment('processed_rows');

            } catch (\Throwable $e) {
                $errors[] = array_merge($row, ['_error' => $e->getMessage()]);
                $import->increment('error_rows');
            }
        }

        $import->update([
            'status'      => 'done',
            'error_file'  => $this->writeErrorCsv($errors, 'products'),
            'finished_at' => now(),
        ]);
    }

    // =========================================================================
    // IMPORTAÇÃO — Clientes
    // =========================================================================

    public function importCustomers(CsvImport $import): void
    {
        $csv = Reader::createFromPath(Storage::path($import->filename), 'r');
        $csv->setHeaderOffset(0);

        $records = iterator_to_array($csv->getRecords());
        $errors  = [];

        $import->update([
            'status'     => 'processing',
            'total_rows' => count($records),
            'started_at' => now(),
        ]);

        foreach ($records as $row) {
            try {
                $email = trim($row['Email'] ?? '');
                if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = array_merge($row, ['_error' => 'E-mail inválido ou vazio']);
                    $import->increment('error_rows');
                    continue;
                }

                $firstName = trim($row['First Name'] ?? '');
                $lastName  = trim($row['Last Name'] ?? '');
                $fullName  = trim("{$firstName} {$lastName}");

                // Sanitiza telefone — remove tudo que não é número ou +
                $phone = $this->sanitizePhone($row['Phone'] ?? null);

                User::updateOrCreate(
                    ['email' => $email],
                    [
                        'name'              => $fullName ?: $email,
                        'first_name'        => $firstName ?: null,
                        'last_name'         => $lastName ?: null,
                        'phone'             => $phone,
                        'accepts_marketing' => filter_var(
                            $row['Accepts Email Marketing'] ?? false,
                            FILTER_VALIDATE_BOOLEAN
                        ),
                        'notes'    => $row['Note'] ?? null,
                        'status'   => 'active',
                        'is_admin' => false,
                        'password' => bcrypt(Str::random(16)),
                    ]
                );

                $import->increment('processed_rows');

            } catch (\Throwable $e) {
                $errors[] = array_merge($row, ['_error' => $e->getMessage()]);
                $import->increment('error_rows');
            }
        }

        $import->update([
            'status'      => 'done',
            'error_file'  => $this->writeErrorCsv($errors, 'customers'),
            'finished_at' => now(),
        ]);
    }

    // =========================================================================
    // IMPORTAÇÃO — Estoque
    // =========================================================================

    public function importStock(CsvImport $import): void
    {
        $csv = Reader::createFromPath(Storage::path($import->filename), 'r');
        $csv->setHeaderOffset(0);

        $records = iterator_to_array($csv->getRecords());
        $errors  = [];

        $import->update([
            'status'     => 'processing',
            'total_rows' => count($records),
            'started_at' => now(),
        ]);

        foreach ($records as $row) {
            try {
                $sku = trim($row['Variant SKU'] ?? '');
                $qty = (int) ($row['Variant Inventory Qty'] ?? 0);

                if (empty($sku)) {
                    $errors[] = array_merge($row, ['_error' => 'SKU vazio']);
                    $import->increment('error_rows');
                    continue;
                }

                $updated = Variant::where('sku', $sku)
                    ->update(['stock_quantity' => $qty]);

                if (!$updated) {
                    // Tenta pelo produto principal
                    $product = Product::where('slug', $row['Handle'] ?? '')->first();
                    if ($product) {
                        $product->update(['stock_quantity' => $qty]);
                    } else {
                        $errors[] = array_merge($row, ['_error' => "SKU '{$sku}' não encontrado"]);
                        $import->increment('error_rows');
                        continue;
                    }
                }

                $import->increment('processed_rows');

            } catch (\Throwable $e) {
                $errors[] = array_merge($row, ['_error' => $e->getMessage()]);
                $import->increment('error_rows');
            }
        }

        $import->update([
            'status'      => 'done',
            'error_file'  => $this->writeErrorCsv($errors, 'stock'),
            'finished_at' => now(),
        ]);
    }

    // =========================================================================
    // EXPORTAÇÃO
    // =========================================================================

    public function exportProducts(): string
    {
        $path   = 'exports/products_' . now()->format('Ymd_His') . '.csv';
        $writer = Writer::createFromPath(Storage::path($path), 'w+');
        $writer->insertOne(self::PRODUCT_COLUMNS);

        Product::with(['category', 'variants.images', 'images'])->chunk(100, function ($products) use ($writer) {
            foreach ($products as $product) {
                $first = true;

                $productImage = $product->images
                    ->whereNull('variant_id')
                    ->sortBy('position')
                    ->first();

                $variantsToExport = $product->variants->count() > 0
                    ? $product->variants
                    : collect([null]);

                foreach ($variantsToExport as $variant) {
                    $options = $variant?->options ?? [];
                    $optKeys = array_keys($options);

                    $variantImage = $variant?->images
                        ?->sortBy('position')
                        ->first();

                    $row = array_fill_keys(self::PRODUCT_COLUMNS, '');

                    if ($first) {
                        $row['Handle']           = $product->slug;
                        $row['Title']            = $product->name;
                        $row['Body (HTML)']      = $product->description ?? '';
                        $row['Published']        = $product->status === 'active' ? 'true' : 'false';
                        $row['Status']           = $product->status;
                        $row['Category']         = $product->category?->name ?? '';
                        $row['Image Src']        = $productImage?->url ?? '';
                        $row['Image Position']   = $productImage ? ($productImage->position + 1) : '';
                        $row['Image Alt Text']   = $productImage?->alt ?? '';
                    }

                    $row['Variant SKU']              = $variant?->sku ?? ($product->sku ?? '');
                    $row['Variant Price']            = number_format($variant?->price ?? $product->price, 2, '.', '');
                    $row['Variant Compare At Price'] = $variant?->compare_at_price
                        ? number_format($variant->compare_at_price, 2, '.', '')
                        : ($product->compare_at_price ? number_format($product->compare_at_price, 2, '.', '') : '');
                    $row['Variant Inventory Qty']    = $variant?->stock_quantity ?? $product->stock_quantity;
                    $row['Variant Barcode']          = $variant?->barcode ?? '';
                    $row['Variant Grams']            = $variant?->weight ? (int) ($variant->weight * 1000) : '';
                    $row['Option1 Name']             = $optKeys[0] ?? '';
                    $row['Option1 Value']            = $options[$optKeys[0] ?? ''] ?? '';
                    $row['Option2 Name']             = $optKeys[1] ?? '';
                    $row['Option2 Value']            = $options[$optKeys[1] ?? ''] ?? '';
                    $row['Variant Image']            = $variantImage?->url ?? '';

                    $writer->insertOne(array_values($row));
                    $first = false;
                }
            }
        });

        return $path;
    }

    public function exportCustomers(): string
    {
        $path   = 'exports/customers_' . now()->format('Ymd_His') . '.csv';
        $writer = Writer::createFromPath(Storage::path($path), 'w+');
        $writer->insertOne(self::CUSTOMER_COLUMNS);

        User::where('is_admin', false)->chunk(100, function ($users) use ($writer) {
            foreach ($users as $user) {
                $writer->insertOne([
                    $user->first_name ?? '',
                    $user->last_name  ?? '',
                    $user->email,
                    $user->phone ?? '',
                    $user->accepts_marketing ? 'yes' : 'no',
                    '',
                    $user->notes ?? '',
                ]);
            }
        });

        return $path;
    }

    public function exportOrders(): string
    {
        $columns = [
            'Name', 'Email', 'Financial Status', 'Fulfillment Status',
            'Currency', 'Subtotal', 'Discount', 'Shipping', 'Taxes', 'Total',
            'Created At', 'Lineitem name', 'Lineitem quantity', 'Lineitem price',
        ];

        $path   = 'exports/orders_' . now()->format('Ymd_His') . '.csv';
        $writer = Writer::createFromPath(Storage::path($path), 'w+');
        $writer->insertOne($columns);

        Order::with(['user', 'items'])->chunk(100, function ($orders) use ($writer) {
            foreach ($orders as $order) {
                $first = true;
                foreach ($order->items as $item) {
                    $writer->insertOne([
                        $first ? $order->order_number : '',
                        $first ? ($order->customer_email ?? $order->user?->email ?? '') : '',
                        $first ? $order->payment_status : '',
                        $first ? $order->fulfillment_status : '',
                        $first ? $order->currency : '',
                        $first ? number_format($order->subtotal, 2, '.', '') : '',
                        $first ? number_format($order->discount_total, 2, '.', '') : '',
                        $first ? number_format($order->shipping_total, 2, '.', '') : '',
                        $first ? number_format($order->tax_total, 2, '.', '') : '',
                        $first ? number_format($order->total, 2, '.', '') : '',
                        $first ? $order->placed_at->format('Y-m-d H:i:s') : '',
                        $item->product_name,
                        $item->quantity,
                        number_format($item->unit_price, 2, '.', ''),
                    ]);
                    $first = false;
                }
            }
        });

        return $path;
    }

    // =========================================================================
    // Templates
    // =========================================================================

    public function getTemplateColumns(string $type): array
    {
        return match ($type) {
            'products'  => self::PRODUCT_COLUMNS,
            'customers' => self::CUSTOMER_COLUMNS,
            'stock'     => self::STOCK_COLUMNS,
            default     => [],
        };
    }

    // =========================================================================
    // Helpers privados
    // =========================================================================

    private function mapProductFields(array $row): array
    {
        $categoryName = trim($row['Category'] ?? '');
        $categoryId   = null;

        if ($categoryName) {
            $category   = Category::firstOrCreate(
                ['name' => $categoryName],
                ['slug' => Str::slug($categoryName), 'status' => 'active']
            );
            $categoryId = $category->id;
        }

        return [
            'name'             => trim($row['Title'] ?? ''),
            'description'      => $row['Body (HTML)'] ?? null,
            'status'           => in_array($row['Status'] ?? 'draft', ['active', 'inactive', 'draft'])
                ? $row['Status']
                : 'draft',
            'category_id'      => $categoryId,
            'price'            => $this->parseDecimal($row['Variant Price'] ?? 0),
            'compare_at_price' => $this->parseDecimal($row['Variant Compare At Price'] ?? null) ?: null,
            'sku'              => trim($row['Variant SKU'] ?? '') ?: null,
            'stock_quantity'   => (int) ($row['Variant Inventory Qty'] ?? 0),
            'featured'         => false,
        ];
    }

    private function mapOptions(array $row): ?array
    {
        $options = [];
        if (!empty($row['Option1 Name']) && !empty($row['Option1 Value'])) {
            $options[$row['Option1 Name']] = $row['Option1 Value'];
        }
        if (!empty($row['Option2 Name']) && !empty($row['Option2 Value'])) {
            $options[$row['Option2 Name']] = $row['Option2 Value'];
        }
        return empty($options) ? null : $options;
    }

    private function handleProductImage(Product $product, string $src, array $row, array $imageMap): void
    {
        $resolvedSrc = $this->resolveImageSource($src, $imageMap);

        if (!$resolvedSrc) {
            return;
        }

        $position = isset($row['Image Position']) && $row['Image Position'] !== ''
            ? max(0, ((int) $row['Image Position']) - 1)
            : $this->nextImagePosition($product);

        ProductImage::updateOrCreate(
            [
                'product_id' => $product->id,
                'variant_id' => null,
                'src'        => $resolvedSrc,
            ],
            [
                'source_type' => ProductImage::detectSourceType($resolvedSrc),
                'alt'         => $row['Image Alt Text'] ?? null,
                'position'    => $position,
            ]
        );
    }

    private function handleVariantImage(Variant $variant, string $src, array $row, array $imageMap): void
    {
        $resolvedSrc = $this->resolveImageSource($src, $imageMap);

        if (!$resolvedSrc) {
            return;
        }

        ProductImage::updateOrCreate(
            [
                'product_id' => $variant->product_id,
                'variant_id' => $variant->id,
                'src'        => $resolvedSrc,
            ],
            [
                'source_type' => ProductImage::detectSourceType($resolvedSrc),
                'alt'         => $row['Image Alt Text'] ?? null,
                'position'    => $this->nextImagePosition($variant->product, $variant->id),
            ]
        );
    }

    private function resolveImageSource(string $src, array $imageMap): ?string
    {
        $src = ProductImage::normalizeSrc($src);

        if (!$src) {
            return null;
        }

        if (isset($imageMap[$src])) {
            return $imageMap[$src];
        }

        $basename = basename(parse_url($src, PHP_URL_PATH) ?: $src);
        if ($basename && isset($imageMap[$basename])) {
            return $imageMap[$basename];
        }

        if (ProductImage::isExternalUrl($src)) {
            return $src;
        }

        if (Storage::disk('public')->exists($src)) {
            return $src;
        }

        return null;
    }

    private function nextImagePosition(Product $product, ?int $variantId = null): int
    {
        $query = $product->images();

        if ($variantId) {
            $query->where('variant_id', $variantId);
        } else {
            $query->whereNull('variant_id');
        }

        return ((int) $query->max('position')) + 1;
    }

    private function extractZip(string $zipPath): array
    {
        $map     = [];
        $zipFull = Storage::path($zipPath);
        $extract = Storage::path('imports/zip_' . Str::uuid());

        $zip = new \ZipArchive();

        if ($zip->open($zipFull) !== true) {
            return $map;
        }

        $zip->extractTo($extract);
        $zip->close();

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($extract, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $fullPath = $file->getPathname();
            $relative = ltrim(str_replace($extract, '', $fullPath), DIRECTORY_SEPARATOR);
            $basename = basename($fullPath);

            $ext = pathinfo($basename, PATHINFO_EXTENSION) ?: 'jpg';
            $dest = 'products/' . Str::uuid() . '.' . $ext;

            Storage::disk('public')->put($dest, file_get_contents($fullPath));

            $map[$relative] = $dest;
            $map[$basename] = $dest;
        }

        return $map;
    }

    private function writeErrorCsv(array $errors, string $type): ?string
    {
        if (empty($errors)) return null;

        $path   = 'exports/errors_' . $type . '_' . now()->format('Ymd_His') . '.csv';
        $writer = Writer::createFromPath(Storage::path($path), 'w+');
        $writer->insertOne(array_keys($errors[0]));
        foreach ($errors as $row) {
            $writer->insertOne(array_values($row));
        }

        return $path;
    }

    private function parseDecimal($value): float
    {
        if ($value === null || $value === '') return 0.0;
        return (float) str_replace(',', '.', $value);
    }

    /**
     * Sanitiza número de telefone — mantém apenas dígitos e o + inicial.
     * Entrada:  "(11) 99999-9999"  →  Saída: "11999999999"
     * Entrada:  "+55 11 99999-9999" → Saída: "+5511999999999"
     */
    private function sanitizePhone(?string $phone): ?string
    {
        if (empty($phone)) return null;

        $phone = trim($phone);
        $hasPlus = str_starts_with($phone, '+');

        $digits = preg_replace('/\D/', '', $phone);

        return $digits ? ($hasPlus ? '+' . $digits : $digits) : null;
    }
}
