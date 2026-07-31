<?php

namespace Database\Factories;

use App\Models\Address;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 50, 2000);
        $discount = fake()->optional()->randomFloat(2, 0, $subtotal * 0.3);
        $shipping = fake()->randomFloat(2, 10, 50);
        $tax = $subtotal * 0.1;
        $total = $subtotal - ($discount ?? 0) + $shipping + $tax;

        return [
            'user_id' => User::factory(),
            'order_number' => fake()->unique()->numerify('ORD-#########'),
            'status' => fake()->randomElement(['pending', 'processing', 'paid', 'shipped', 'delivered', 'cancelled']),
            'payment_status' => fake()->randomElement(['pending', 'paid', 'failed']),
            'fulfillment_status' => fake()->randomElement(['pending', 'shipped', 'delivered']),
            'subtotal' => $subtotal,
            'discount_total' => $discount ?? 0,
            'shipping_total' => $shipping,
            'tax_total' => $tax,
            'total' => $total,
            'currency' => 'BRL',
            'payment_method' => fake()->randomElement(['stripe', 'mercadopago', 'paypal']),
            'transaction_id' => fake()->uuid(),
            'shipping_method' => fake()->randomElement(['Correios', 'Transportadora']),
            'shipping_address_id' => Address::factory(),
            'billing_address_id' => Address::factory(),
            'customer_note' => fake()->optional()->sentence(),
            'admin_note' => fake()->optional()->sentence(),
            'placed_at' => fake()->dateTimeBetween('-1 year', 'now'),
        ];
    }
}