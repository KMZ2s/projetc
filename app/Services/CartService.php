<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Product;
use App\Models\Variant;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class CartService
{
    const SESSION_KEY = 'cart';

    // =========================================================================
    // Leitura
    // =========================================================================

    public function get(): array
    {
        return Session::get(self::SESSION_KEY, ['items' => [], 'coupon' => null]);
    }

    public function items(): array
    {
        return $this->get()['items'] ?? [];
    }

    public function count(): int
    {
        return array_sum(array_column($this->items(), 'quantity'));
    }

    public function subtotal(): float
    {
        return array_reduce($this->items(), function ($carry, $item) {
            return $carry + ($item['price'] * $item['quantity']);
        }, 0.0);
    }

    public function discount(): float
    {
        $coupon = $this->get()['coupon'] ?? null;
        if (empty($coupon)) {
            return 0.0;
        }

        $subtotal = $this->subtotal();

        if ($coupon['type'] === 'percentage') {
            return round($subtotal * ($coupon['value'] / 100), 2);
        }

        return min((float) $coupon['value'], $subtotal);
    }

    public function total(): float
    {
        return max(0.0, $this->subtotal() - $this->discount());
    }

    public function summary(): array
    {
        $cart = $this->get();

        return [
            'items'    => $this->items(),
            'coupon'   => $cart['coupon'],
            'count'    => $this->count(),
            'subtotal' => $this->subtotal(),
            'discount' => $this->discount(),
            'total'    => $this->total(),
        ];
    }

    // =========================================================================
    // Mutação
    // =========================================================================

    public function add(int $productId, int $quantity = 1, ?int $variantId = null): array
    {
        $product = Product::findOrFail($productId);

        $price = (float) $product->price;
        $sku   = $product->sku;

        if ($variantId) {
            $variant = Variant::findOrFail($variantId);
            $price   = (float) $variant->price;
            $sku     = $variant->sku;
        }

        $key  = $this->itemKey($productId, $variantId);
        $cart = $this->get();

        if (isset($cart['items'][$key])) {
            $cart['items'][$key]['quantity'] += $quantity;
        } else {
            $cart['items'][$key] = [
                'key'        => $key,
                'product_id' => $product->id,
                'variant_id' => $variantId,
                'name'       => $product->name,
                'slug'       => $product->slug,
                'sku'        => $sku,
                'price'      => $price,
                'image'      => $product->images()->first()?->src,
                'quantity'   => $quantity,
            ];
        }

        Session::put(self::SESSION_KEY, $cart);

        return $this->summary();
    }

    public function update(string $key, int $quantity): array
    {
        $cart = $this->get();

        if ($quantity <= 0) {
            unset($cart['items'][$key]);
        } elseif (isset($cart['items'][$key])) {
            $cart['items'][$key]['quantity'] = $quantity;
        }

        Session::put(self::SESSION_KEY, $cart);

        return $this->summary();
    }

    public function remove(string $key): array
    {
        $cart = $this->get();
        unset($cart['items'][$key]);
        Session::put(self::SESSION_KEY, $cart);

        return $this->summary();
    }

    public function clear(): void
    {
        Session::forget(self::SESSION_KEY);
    }

    // =========================================================================
    // Cupom — usa helpers do model Coupon
    // =========================================================================

    public function applyCoupon(string $code): array
    {
        $coupon = Coupon::where('code', $code)->first();

        if (!$coupon) {
            return ['success' => false, 'message' => 'Cupom inválido ou expirado.'];
        }

        // Valida status e datas via helper do model
        if (!$coupon->isValid()) {
            return ['success' => false, 'message' => 'Este cupom não está disponível.'];
        }

        // Valida pedido mínimo
        if ($coupon->min_order_value && $this->subtotal() < $coupon->min_order_value) {
            $minFormatted = 'R$ ' . number_format($coupon->min_order_value, 2, ',', '.');
            return [
                'success' => false,
                'message' => "Pedido mínimo de {$minFormatted} para este cupom.",
            ];
        }

        // Valida limite de uso (global e por usuário)
        $userId = Auth::id();

        if ($userId && !$coupon->isAvailableForUser($userId)) {
            return ['success' => false, 'message' => 'Você atingiu o limite de uso deste cupom.'];
        }

        if (!$userId && !$coupon->hasUsagesAvailable()) {
            return ['success' => false, 'message' => 'Este cupom atingiu o limite de usos.'];
        }

        $cart           = $this->get();
        $cart['coupon'] = [
            'id'    => $coupon->id,
            'code'  => $coupon->code,
            'type'  => $coupon->type,
            'value' => $coupon->value,
        ];
        Session::put(self::SESSION_KEY, $cart);

        return array_merge(
            ['success' => true, 'message' => 'Cupom aplicado com sucesso!'],
            $this->summary()
        );
    }

    public function removeCoupon(): array
    {
        $cart           = $this->get();
        $cart['coupon'] = null;
        Session::put(self::SESSION_KEY, $cart);

        return $this->summary();
    }

    // =========================================================================
    // Merge de sessão ao fazer login
    // =========================================================================

    /**
     * Mescla o carrinho da sessão atual com o carrinho do usuário recém-logado.
     *
     * O Laravel/Fortify chama session()->regenerate() no login, que gera um novo
     * ID de sessão MAS mantém os dados existentes. Ou seja, itens adicionados
     * como guest permanecem após o login — não há perda de dados na maioria dos casos.
     *
     * Este método serve para o cenário futuro onde o carrinho for persistido no
     * banco (ex: tabela `carts`), permitindo recuperar itens de sessões anteriores
     * do usuário e mesclar com os itens atuais da sessão.
     *
     * Para ativar: chamar este método em um listener do evento `Illuminate\Auth\Events\Login`.
     */
    public function mergeSessionCartOnLogin(): void
    {
        $user = Auth::user();
        if (!$user) {
            return;
        }

        // Itens que o guest adicionou antes de logar
        $sessionItems = $this->items();

        if (empty($sessionItems)) {
            return;
        }

        // Por ora a sessão persiste naturalmente após o login (sem perda de dados).
        // Quando o carrinho for persistido em banco, implementar aqui a lógica de merge:
        //
        // $savedCart = Cart::where('user_id', $user->id)->first();
        // if ($savedCart) {
        //     foreach ($savedCart->items as $item) {
        //         $key = $this->itemKey($item->product_id, $item->variant_id);
        //         if (!isset($sessionItems[$key])) {
        //             $this->add($item->product_id, $item->quantity, $item->variant_id);
        //         }
        //     }
        // }
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function itemKey(int $productId, ?int $variantId): string
    {
        return $variantId ? "p{$productId}_v{$variantId}" : "p{$productId}";
    }
}