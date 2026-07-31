<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex,nofollow">

    <title>@yield('title', 'Finalizar pedido') · {{ config('app.name') }}</title>

    @php
        try {
            $trackingConfig = app(\App\Services\TrackingManager::class)->publicConfig();
        } catch (\Throwable) {
            $trackingConfig = [
                'integrations' => [],
                'utmify_script_enabled' => false,
            ];
        }

        $pageTrackingContext = $trackingContext ?? ['event' => 'page_view'];
        $trackingConfigBase64 = base64_encode(json_encode(
            $trackingConfig,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        ));
        $trackingContextBase64 = base64_encode(json_encode(
            $pageTrackingContext,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        ));
    @endphp
    <script>
    (() => {
        const decodeJsonBase64 = (encoded) => {
            const bytes = Uint8Array.from(atob(encoded), char => char.charCodeAt(0));
            return JSON.parse(new TextDecoder().decode(bytes));
        };

        window.REPLICANTFY_TRACKING_CONFIG = decodeJsonBase64('{{ $trackingConfigBase64 }}');
        window.REPLICANTFY_TRACKING_CONTEXT = decodeJsonBase64('{{ $trackingContextBase64 }}');
    })();
    </script>
    @if ($trackingConfig['utmify_script_enabled'] ?? false)
        <script
            src="https://cdn.utmify.com.br/scripts/utms/latest.js"
            data-utmify-prevent-xcod-sck
            data-utmify-prevent-subids
            async
            defer
        ></script>
    @endif
    <script src="{{ asset('tracking-assets/tracking.js') }}" defer></script>

    {{-- Fontes --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

    {{-- CSS isolado do checkout (paleta Casa Dolce, independente do tema) --}}
    <link rel="stylesheet" href="{{ asset('checkout-assets/checkout.css') }}?v={{ filemtime(public_path('checkout-assets/checkout.css')) }}">
    <link rel="stylesheet" href="{{ asset('checkout-assets/emporio-checkout.css') }}?v={{ filemtime(public_path('checkout-assets/emporio-checkout.css')) }}">

    @stack('head')
</head>
<body class="checkout-body @yield('body-class')">

    @include('checkout.partials.header')

    <main class="checkout-main" role="main">
        @yield('content')
    </main>

    @include('checkout.partials.footer')

    @stack('scripts')
</body>
</html>
