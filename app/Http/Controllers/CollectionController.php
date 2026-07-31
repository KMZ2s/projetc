<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Twig\Environment;

class CollectionController extends Controller
{
    /**
     * Sorts permitidos pra listagens de coleção. Qualquer outro valor
     * recebido via ?sort= cai silenciosamente no default 'featured'.
     *
     * Public porque ProductController::index e BestSellersController
     * reaproveitam tanto a constante quanto o applySort() abaixo.
     */
    public const ALLOWED_SORTS = ['featured', 'newest', 'price_asc', 'price_desc'];

    /**
     * GET /colecao/{slug}
     *
     * Página de categoria. Cenário A da Fase 11: categoria-pai exibe
     * produtos da subárvore inteira (próprios + filhas + netas), via
     * Category::descendantIds(). Lista de filhas vai pro tema renderizar
     * pills de navegação interna.
     *
     * Variáveis enviadas ao template:
     *   - category       : Category (Eloquent)
     *   - subcategories  : Collection<Category> (filhas ativas)
     *   - is_subcategory : bool (true se essa categoria tem parent)
     *   - products       : LengthAwarePaginator
     *   - current_sort   : string ('featured'|'newest'|'price_asc'|'price_desc')
     *   - is_bestsellers : bool (sempre false aqui — flag pra template diferenciar
     *                     a hero quando vier do BestSellersController)
     *   - settings       : array (theme_settings())
     */
    public function show($slug, Request $request, Environment $twig)
    {
        $category = Category::where('slug', $slug)->firstOrFail();

        $sort = $this->resolveSort($request->query('sort'));

        $query = Product::active()
            ->whereIn('category_id', $category->descendantIds())
            ->with(['images', 'variants']);

        self::applySort($query, $sort);

        $products = $query->paginate(12)->withQueryString();

        return $twig->render('templates/collection.twig', [
            'category'       => $category,
            'subcategories'  => $category->activeChildren()->get(),
            'is_subcategory' => $category->parent_id !== null,
            'products'       => $products,
            'current_sort'   => $sort,
            'is_bestsellers' => false,
            'settings'       => theme_settings(),
        ]);
    }

    /**
     * Valida e normaliza o valor de ?sort=. Reusado pelos controllers
     * que aceitam o mesmo conjunto de sorts.
     */
    public static function resolveSort($value): string
    {
        return in_array($value, self::ALLOWED_SORTS, true) ? $value : 'featured';
    }

    /**
     * Aplica ordenação no query builder. Centraliza a lógica pra que
     * ProductController::index e BestSellersController reusem sem
     * duplicar regras.
     *
     * Default 'featured': produtos com flag featured=true vêm primeiro,
     * desempate por created_at desc (mais novo primeiro).
     */
    public static function applySort($query, string $sort): void
    {
        switch ($sort) {
            case 'newest':
                $query->orderBy('created_at', 'desc');
                break;

            case 'price_asc':
                $query->orderBy('price', 'asc');
                break;

            case 'price_desc':
                $query->orderBy('price', 'desc');
                break;

            case 'featured':
            default:
                $query->orderBy('featured', 'desc')
                      ->orderBy('created_at', 'desc');
                break;
        }
    }
}