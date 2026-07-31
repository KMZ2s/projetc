<?php

// rcrowe/twigbridge 0.14.7 — mantido apenas para compatibilidade com o
// pacote instalado. A configuração real do Twig é feita no AppServiceProvider
// via singleton próprio, que substitui o binding do TwigBridge.

return [
    'paths'      => [],
    'extension'  => 'twig',
    'extensions' => [],
    'environment' => [
        'debug'            => env('APP_DEBUG', false),
        'charset'          => 'utf-8',
        'cache'            => storage_path('framework/views/twig'),
        'auto_reload'      => true,
        'strict_variables' => false,
        'autoescape'       => 'html',
        'optimizations'    => -1,
    ],
    'functions' => [],
    'filters'   => [],
    'globals'   => [],
];