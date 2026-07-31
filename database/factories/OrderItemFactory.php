<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Variant;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition(): array
    {
        $product = Product::inRandomOrder()->first() ?? Product::factory();
        $variant = $product->variants->first();

        $quantity = fake()->numberBetween(1, 5);
        $unitPrice = $variant ? $variant->price : $product->price;
        $totalPrice = $quantity * $unitPrice;

        return [
            'order_id' => Order::factory(),
            'product_id' => $product->id,
            'variant_id' => $variant?->id,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_price' => $totalPrice,
            'discount' => 0,
            'product_name' => $product->name,
            'variant_sku' => $variant?->sku,
            'variant_options' => $variant?->options,
        ];
    }
}