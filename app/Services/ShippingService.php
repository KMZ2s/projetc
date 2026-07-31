<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShippingService
{
    /**
     * Calcula opções de frete para um CEP de destino.
     *
     * @return array{
     *   success: bool,
     *   message?: string,
     *   cep?: string,
     *   city?: string,
     *   state?: string,
     *   region?: string,
     *   options?: array<int, array{name: string, price: int, formatted: string, days: string}>,
     * }
     */
    public function calculateForCep(string $cep): array
    {
        $cep = preg_replace('/\D/', '', $cep);

        if (strlen($cep) !== 8) {
            return ['success' => false, 'message' => 'CEP inválido. Use o formato 00000-000.'];
        }

        $location = $this->lookupCep($cep);

        if (!$location) {
            return ['success' => false, 'message' => 'CEP não encontrado.'];
        }

        $uf = strtoupper($location['uf'] ?? '');
        $region = $this->regionForState($uf);
        $rates  = config('shipping.rates', []);

        $options = $region && isset($rates[$region])
            ? $rates[$region]['options']
            : config('shipping.default.options', []);

        return [
            'success' => true,
            'cep'     => $this->formatCep($cep),
            'city'    => $location['localidade'] ?? '',
            'state'   => $uf,
            'region'  => $region,
            'options' => array_map(fn ($opt) => [
                'name'      => $opt['name'],
                'price'     => $opt['price'],
                'formatted' => $this->moneyBR($opt['price']),
                'days'      => $opt['days'],
            ], $options),
        ];
    }

    /**
     * Lookup CEP via ViaCEP, com cache de 24h.
     * Retorna null se não encontrar ou se o ViaCEP falhar.
     */
    protected function lookupCep(string $cep): ?array
    {
        return Cache::remember("viacep:{$cep}", now()->addDay(), function () use ($cep) {
            try {
                $response = Http::timeout(5)
                    ->retry(2, 200)
                    ->get("https://viacep.com.br/ws/{$cep}/json/");

                if (!$response->successful()) {
                    return null;
                }

                $data = $response->json();

                // ViaCEP retorna {"erro": true} pra CEPs inexistentes
                if (!is_array($data) || ($data['erro'] ?? false)) {
                    return null;
                }

                return $data;
            } catch (\Throwable $e) {
                Log::warning('ViaCEP lookup failed', [
                    'cep'   => $cep,
                    'error' => $e->getMessage(),
                ]);
                return null;
            }
        });
    }

    /**
     * Encontra a região (chave do config/shipping.rates) para uma UF.
     */
    protected function regionForState(string $uf): ?string
    {
        if ($uf === '') {
            return null;
        }

        foreach (config('shipping.rates', []) as $region => $data) {
            if (in_array($uf, $data['states'] ?? [], true)) {
                return $region;
            }
        }

        return null;
    }

    protected function formatCep(string $cep): string
    {
        return substr($cep, 0, 5) . '-' . substr($cep, 5, 3);
    }

    protected function moneyBR(int $cents): string
    {
        return 'R$ ' . number_format($cents / 100, 2, ',', '.');
    }
}