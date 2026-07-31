<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\TrackingManager;
use Illuminate\Http\Request;
use Twig\Environment;

class ProductController extends Controller
{
    /**
     * Quantos produtos relacionados retornar. 4 cabe bem na grid PDP
     * em desktop (1 linha) e fica ainda visível no mobile.
     */
    private const RELATED_LIMIT = 4;

    /**
     * GET /produto/{slug}
     *
     * Eager-load das relações que o tema acessa na página de produto:
     * imagens (galeria), variants (seleção), category (breadcrumb).
     *
     * Variáveis enviadas ao template:
     *   - product          : Product
     *   - related_products : Collection<Product> (até 4 — featured-first)
     *   - settings         : array
     */
    public function show($slug, Environment $twig, TrackingManager $tracking)
    {
        $product = Product::where('slug', $slug)
            ->with(['images', 'variants', 'category'])
            ->firstOrFail();

        $relatedProducts = $this->fetchRelated($product);

        return $twig->render('templates/product.twig', [
            'product' => $product,
            'related_products' => $relatedProducts,
            'settings' => theme_settings(),
            'tracking_context' => $tracking->browserContextForProduct($product),
        ]);
    }

    /**
     * GET /produtos
     *
     * Listagem geral. Renderiza o mesmo template usado pra coleção, com
     * `category=null` (template trata isso como "todos os produtos").
     * Aceita ?sort= com os mesmos valores de coleção.
     */
    public function index(Request $request, Environment $twig)
    {
        $sort = CollectionController::resolveSort($request->query('sort'));

        $query = Product::active()
            ->with(['images', 'variants', 'category']);

        if ($search = trim((string) $request->query('q', ''))) {
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        CollectionController::applySort($query, $sort);

        $products = $query->paginate(12)->withQueryString();

        return $twig->render('templates/collection.twig', [
            'category' => null,
            'subcategories' => collect(),
            'is_subcategory' => false,
            'products' => $products,
            'current_sort' => $sort,
            'is_bestsellers' => false,
            'settings' => theme_settings(),
        ]);
    }

    /**
     * Busca produtos relacionados ao atual:
     *  - Mesma categoria
     *  - Excluindo o próprio produto
     *  - Apenas ativos
     *  - Featured-first (flag featured=true vem antes), desempate por
     *    created_at desc
     *  - Limite definido em RELATED_LIMIT
     *
     * Se o produto não tem categoria atribuída, retorna collection vazia
     * — o tema cuida do `{% if related_products|length > 0 %}`.
     */
    private function fetchRelated(Product $product)
    {
        if (! $product->category_id) {
            return collect();
        }

        return Product::active()
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->with(['images', 'variants'])
            ->orderBy('featured', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit(self::RELATED_LIMIT)
            ->get();
    }
}
