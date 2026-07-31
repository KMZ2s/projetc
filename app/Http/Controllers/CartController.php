<?php

namespace App\Http\Controllers;

use App\Services\CartService;
use App\Services\TrackingManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Twig\Environment;

class CartController extends Controller
{
    public function __construct(
        protected CartService $cart,
        protected TrackingManager $tracking,
    ) {}

    // =========================================================================
    // GET /carrinho — view Twig
    // =========================================================================

    public function index(Environment $twig): string
    {
        $summary = $this->cart->summary();

        return $twig->render('templates/cart.twig', [
            'cart' => $summary,
            'settings' => theme_settings(),
            'tracking_context' => $this->tracking->browserContextForCart($summary),
        ]);
    }

    // =========================================================================
    // GET /cart.json
    // =========================================================================

    public function show(): JsonResponse
    {
        return response()->json($this->cart->summary());
    }

    // =========================================================================
    // POST /cart/add.json
    // =========================================================================

    public function add(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['sometimes', 'integer', 'min:1'],
            'variant_id' => ['nullable', 'integer', 'exists:variants,id'],
        ]);

        $summary = $this->cart->add(
            productId: (int) $validated['product_id'],
            quantity: (int) ($validated['quantity'] ?? 1),
            variantId: isset($validated['variant_id']) ? (int) $validated['variant_id'] : null,
        );

        return response()->json($summary);
    }

    // =========================================================================
    // POST /cart/update.json
    // Body: { updates: { "p1": 2, "p1_v3": 0, ... } }
    // =========================================================================

    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'updates' => ['required', 'array'],
            'updates.*' => ['integer', 'min:0'],
        ]);

        $summary = null;
        foreach ($request->input('updates') as $key => $quantity) {
            $summary = $this->cart->update((string) $key, (int) $quantity);
        }

        return response()->json($summary ?? $this->cart->summary());
    }

    // =========================================================================
    // DELETE /cart/items/{key}.json
    // =========================================================================

    public function remove(string $key): JsonResponse
    {
        return response()->json($this->cart->remove($key));
    }

    // =========================================================================
    // POST /cart/coupon.json
    // =========================================================================

    public function applyCoupon(Request $request): JsonResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:50']]);

        $result = $this->cart->applyCoupon($request->input('code'));

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    // =========================================================================
    // DELETE /cart/coupon.json
    // =========================================================================

    public function removeCoupon(): JsonResponse
    {
        return response()->json($this->cart->removeCoupon());
    }
}
