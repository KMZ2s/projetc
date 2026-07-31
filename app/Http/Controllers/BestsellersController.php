<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Twig\Environment;

class BestSellersController extends Controller
{
    /**
     * Janela de tempo (em dias) considerada pra ranquear best sellers.
     * 90 dias captura tendência sem ficar refém de picos sazonais antigos
     * (Black Friday do ano passado, por exemplo). Ajustar aqui se a loja
     * tiver dinâmica diferente.
     */
    private const WINDOW_DAYS = 90;

    /**
     * Quantidade de produtos por página (paridade com listagem normal).
     */
    private const PER_PAGE = 12;

    /**
     * GET /mais-vendidos
     *
     * Lista produtos ordenados por volume de vendas em pedidos pagos
     * dos últimos N dias (definido por WINDOW_DAYS).
     *
     * Aceita ?sort= como override:
     *   - sem ?sort= ou ?sort=best_sellers (default): ordena por volume
     *   - ?sort=newest|price_asc|price_desc|featured: usa applySort
     *     do CollectionController, mantendo o template apto a oferecer
     *     o mesmo dropdown de ordenação da coleção normal
     *
     * Reusa templates/collection.twig com flag is_bestsellers=true pro
     * tema customizar a hero (título "Mais vendidos", sem subcategorias).
     *
     * IMPORTANTE: assume que orders.status guarda 'paid' (lowercase).
     * Se o projeto usar outro casing/valor, ajustar o where abaixo.
     * Status do BlackcatPay no webhook é 'PAID' uppercase, mas o Order
     * model local pode normalizar — confirmar com o WebhookProcessor.
     */
    public function index(Request $request, Environment $twig)
    {
        $sortParam = $request->query('sort');
        $isBestSellersSort = ($sortParam === null || $sortParam === 'best_sellers');

        $query = Product::active()
            ->with(['images', 'variants', 'category']);

        if ($isBestSellersSort) {
            $this->applyBestSellersSort($query);
            $currentSort = 'best_sellers';
        } else {
            CollectionController::applySort($query, CollectionController::resolveSort($sortParam));
            $currentSort = CollectionController::resolveSort($sortParam);
        }

        $products = $query->paginate(self::PER_PAGE)->withQueryString();

        return $twig->render('templates/collection.twig', [
            'category'       => null,
            'subcategories'  => collect(),
            'is_subcategory' => false,
            'products'       => $products,
            'current_sort'   => $currentSort,
            'is_bestsellers' => true,
            'settings'       => theme_settings(),
        ]);
    }

    /**
     * Aplica ordenação por volume de vendas via joinSub.
     *
     * Lógica: subquery soma `quantity` de order_items agrupado por
     * product_id, considerando apenas pedidos pagos nos últimos N dias.
     * Resultado é joinado ao produtos e ordenado por sold_count desc.
     *
     * Produtos sem vendas no período NÃO aparecem (joinSub é INNER) —
     * isso é desejado: "mais vendidos" tem que ter venda.
     */
    private function applyBestSellersSort($query): void
    {
        $subquery = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.status', 'paid')
            ->where('orders.created_at', '>=', now()->subDays(self::WINDOW_DAYS))
            ->select('order_items.product_id', DB::raw('SUM(order_items.quantity) as sold_count'))
            ->groupBy('order_items.product_id');

        $query->joinSub($subquery, 'sales', function ($join) {
                $join->on('sales.product_id', '=', 'products.id');
            })
            ->orderBy('sales.sold_count', 'desc')
            ->select('products.*');
    }
}