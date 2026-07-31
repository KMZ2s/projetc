<?php

namespace Database\Seeders;

use App\Models\PaymentGateway;
use Illuminate\Database\Seeder;

class PaymentGatewaySeeder extends Seeder
{
    public function run(): void
    {
        $gateways = [
            [
                'name'      => 'BlackcatPay',
                'slug'      => 'blackcatpay',
                'is_active' => false,
                'api_key'   => null,
                'position'  => 1,
            ],
        ];

        foreach ($gateways as $gateway) {
            PaymentGateway::updateOrCreate(
                ['slug' => $gateway['slug']],
                $gateway
            );
        }
    }
}