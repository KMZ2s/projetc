<?php

/*
|--------------------------------------------------------------------------
| Configuração de Frete (Replicantfy)
|--------------------------------------------------------------------------
|
| Calculo simples baseado em região do CEP de destino. NÃO consulta a API
| dos Correios — usa tabela fixa por região, pra evitar dependência externa
| e dar previsibilidade ao operador.
|
| Para integrar Correios, Melhor Envio, Frenet, etc no futuro: substituir
| a lógica em App\Services\ShippingService::calculateForCep().
|
| Valores em centavos. Origin CEP é o CEP de despacho da loja, vem do .env.
|
*/

return [

    'origin_cep' => env('SHIPPING_ORIGIN_CEP', '60000000'),

    /*
    |----------------------------------------------------------------------
    | Frete grátis acima de
    |----------------------------------------------------------------------
    | Ainda é definido por settings.free_shipping_threshold no tema (em reais).
    | Aqui é só fallback quando o tema não passa nada.
    */
    'free_threshold_cents' => 19900,

    /*
    |----------------------------------------------------------------------
    | Tabela de regiões → preços
    |----------------------------------------------------------------------
    | Cada região mapeia pra UFs. Ao calcular, o ShippingService faz lookup
    | da UF do CEP via ViaCEP, encontra a região, retorna PAC + SEDEX.
    |
    | Editar à vontade. Preços em centavos.
    */
    'rates' => [

        'sudeste' => [
            'states' => ['SP', 'RJ', 'MG', 'ES'],
            'options' => [
                ['name' => 'PAC',   'price' => 1890, 'days' => '4 a 7 dias úteis'],
                ['name' => 'SEDEX', 'price' => 2990, 'days' => '1 a 3 dias úteis'],
            ],
        ],

        'sul' => [
            'states' => ['PR', 'SC', 'RS'],
            'options' => [
                ['name' => 'PAC',   'price' => 2490, 'days' => '6 a 10 dias úteis'],
                ['name' => 'SEDEX', 'price' => 3990, 'days' => '2 a 4 dias úteis'],
            ],
        ],

        'centro_oeste' => [
            'states' => ['DF', 'GO', 'MT', 'MS'],
            'options' => [
                ['name' => 'PAC',   'price' => 2990, 'days' => '7 a 12 dias úteis'],
                ['name' => 'SEDEX', 'price' => 4990, 'days' => '3 a 5 dias úteis'],
            ],
        ],

        'nordeste' => [
            'states' => ['BA', 'PE', 'CE', 'RN', 'PB', 'AL', 'SE', 'MA', 'PI'],
            'options' => [
                ['name' => 'PAC',   'price' => 1990, 'days' => '5 a 8 dias úteis'],
                ['name' => 'SEDEX', 'price' => 3490, 'days' => '2 a 4 dias úteis'],
            ],
        ],

        'norte' => [
            'states' => ['AM', 'RR', 'AP', 'PA', 'TO', 'RO', 'AC'],
            'options' => [
                ['name' => 'PAC',   'price' => 4490, 'days' => '10 a 20 dias úteis'],
                ['name' => 'SEDEX', 'price' => 6990, 'days' => '5 a 10 dias úteis'],
            ],
        ],

    ],

    /*
    |----------------------------------------------------------------------
    | Fallback (UF desconhecida)
    |----------------------------------------------------------------------
    */
    'default' => [
        'options' => [
            ['name' => 'PAC',   'price' => 3990, 'days' => '8 a 14 dias úteis'],
            ['name' => 'SEDEX', 'price' => 5990, 'days' => '4 a 7 dias úteis'],
        ],
    ],

];