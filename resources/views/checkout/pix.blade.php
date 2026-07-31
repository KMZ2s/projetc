@extends('checkout.layout')

@section('title', 'Pagamento PIX · Pedido ' . $order->order_number)
@section('body-class', 'checkout-body--pix')

@push('head')
    <meta name="checkout-status-url" content="{{ route('checkout.status', $order) }}">
@endpush

@push('scripts')
    <script src="{{ asset('checkout-assets/checkout-pix.js') }}?v={{ filemtime(public_path('checkout-assets/checkout-pix.js')) }}" defer></script>
@endpush

@php
    $paymentData = $order->payment_data ?? [];
    $copyPaste = $paymentData['copy_paste'] ?? $paymentData['qr_code'] ?? '';
    $qrCodeBase64 = $paymentData['qr_code_base64'] ?? null;
    $qrCodeSrc = $qrCodeBase64 && !str_starts_with($qrCodeBase64, 'data:')
        && !str_starts_with($qrCodeBase64, 'http://')
        && !str_starts_with($qrCodeBase64, 'https://')
            ? 'data:image/png;base64,' . $qrCodeBase64
            : $qrCodeBase64;

    // Alguns retornos do gateway trazem somente o payload copia-e-cola.
    // Gerar o QR localmente evita depender de um serviço externo e garante
    // que a tela continue utilizável nesse formato de resposta.
    if (!$qrCodeSrc && $copyPaste) {
        try {
            $qrOptions = new \chillerlan\QRCode\QROptions([
                'outputType' => \chillerlan\QRCode\QRCode::OUTPUT_MARKUP_SVG,
                'outputBase64' => true,
                'scale' => 6,
                'quietzoneSize' => 2,
            ]);
            $qrCodeSrc = (new \chillerlan\QRCode\QRCode($qrOptions))->render($copyPaste);
        } catch (\Throwable) {
            $qrCodeSrc = null;
        }
    }
    $fallbackExpiry = $order->created_at
        ? $order->created_at->copy()->addMinutes((int) ($checkoutSettings->pix_expires_minutes ?: 10))->toIso8601String()
        : now()->addMinutes(10)->toIso8601String();
    $expiresAt = $paymentData['expires_at'] ?? $fallbackExpiry;
    $totalFormatted = number_format((float) $order->total, 2, ',', '.');
    $customerName = $order->customer_name ?? $order->user?->display_name ?? 'Cliente';
    $firstName = explode(' ', trim($customerName))[0] ?: 'Cliente';
@endphp

@section('content')
<div class="checkout-pix-page"
     data-pix-page
     data-pix-expires-at="{{ $expiresAt }}"
     data-pix-status-url="{{ route('checkout.status', $order) }}">
    <div class="checkout-pix-page__container">
        <div class="checkout-pix-page__active" data-pix-active>
            <header class="checkout-pix-page__intro">
                <h1>Quase lá...</h1>
                <p>
                    Pague seu Pix dentro de <strong data-pix-countdown>10:00</strong><br>
                    para garantir sua compra.
                </p>
                <span class="checkout-pix-page__status">
                    <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/>
                        <polyline points="12 6 12 12 16 14"/>
                    </svg>
                    Aguardando pagamento
                    <span class="checkout-pix-page__dots" aria-hidden="true"><i></i><i></i><i></i></span>
                </span>
            </header>

            <aside class="checkout-pix-page__urgency">
                <span class="checkout-pix-page__urgency-icon" aria-hidden="true">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M11 21.73a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73z"/>
                        <path d="M12 22V12"/>
                        <path d="m3.3 7 7.703 4.734a2 2 0 0 0 1.994 0L20.7 7"/>
                        <path d="m7.5 4.27 9 5.15"/>
                    </svg>
                </span>
                <div>
                    <strong>Parabéns, {{ $firstName }}! 🎉</strong>
                    <p><b>Seu pedido foi separado e já está em processo de embalo.</b></p>
                    <p>
                        Para garantir o envio imediato, finalize o pagamento via Pix.
                        Caso o pagamento não seja confirmado, o produto poderá ser
                        disponibilizado para outros compradores.
                    </p>
                </div>
            </aside>

            <section class="checkout-pix-page__card" aria-label="Pagamento PIX">
                @if ($qrCodeSrc)
                    <div class="checkout-pix-page__qr-wrap">
                        <img src="{{ $qrCodeSrc }}"
                             alt="QR Code PIX para pagamento de R$ {{ $totalFormatted }}"
                             class="checkout-pix-page__qr" width="160" height="160">
                    </div>
                @else
                    <div class="checkout-pix-page__qr-error">
                        Use o código PIX abaixo para concluir o pagamento.
                    </div>
                @endif

                <p class="checkout-pix-page__amount">
                    Valor do Pix: <strong>R$ {{ $totalFormatted }}</strong>
                </p>

                @if ($copyPaste)
                    <input type="text" value="{{ $copyPaste }}" readonly
                           class="checkout-pix-page__copy-input" data-pix-copy-input
                           aria-label="Código PIX copia e cola">
                    <button type="button" class="checkout-pix-page__copy-btn" data-pix-copy-btn>
                        <svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect width="14" height="14" x="8" y="8" rx="2" ry="2"/>
                            <path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/>
                        </svg>
                        <span data-pix-copy-label>Copiar código</span>
                    </button>
                @endif

                <button type="button" class="checkout-pix-page__verify" data-pix-verify>
                    <svg class="checkout-pix-page__verify-icon" aria-hidden="true"
                         width="20" height="20" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"/>
                        <path d="m21 21-4.3-4.3"/>
                    </svg>
                    <span data-pix-verify-label>Verificar pagamento</span>
                </button>

                <section class="checkout-pix-page__steps">
                    <h2>COMO PAGAR SEU PIX</h2>
                    <ol>
                        <li>
                            <span class="checkout-pix-page__step-icon" aria-hidden="true">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect width="14" height="20" x="5" y="2" rx="2" ry="2"/>
                                    <path d="M12 18h.01"/>
                                </svg>
                            </span>
                            <p><strong>Clique no botão acima para copiar</strong> o
                                <b class="checkout-pix-page__accent">CÓDIGO PIX</b> gerado.</p>
                            <b class="checkout-pix-page__step-number">1</b>
                        </li>
                        <li>
                            <span class="checkout-pix-page__step-icon" aria-hidden="true">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M14 4.1 12 6"/>
                                    <path d="m5.1 8-2.9-.8"/>
                                    <path d="m6 12-1.9 2"/>
                                    <path d="M7.2 2.2 8 5.1"/>
                                    <path d="M9.037 9.69a.498.498 0 0 1 .653-.653l11 4.5a.5.5 0 0 1-.074.949l-4.349 1.041a1 1 0 0 0-.74.739l-1.04 4.35a.5.5 0 0 1-.95.074z"/>
                                </svg>
                            </span>
                            <p><strong>No aplicativo do seu banco, clique em</strong>
                                <b class="checkout-pix-page__accent">PIX</b> e procure a opção
                                "<b class="checkout-pix-page__accent">COPIA E COLA</b>"</p>
                            <b class="checkout-pix-page__step-number">2</b>
                        </li>
                        <li>
                            <span class="checkout-pix-page__step-icon" aria-hidden="true">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21.801 10A10 10 0 1 1 17 3.335"/>
                                    <path d="m9 11 3 3L22 4"/>
                                </svg>
                            </span>
                            <p><strong>Cole o</strong> <b class="checkout-pix-page__accent">CÓDIGO PIX</b>
                                <strong>copiado acima e confirme para aprovar sua compra!</strong></p>
                            <b class="checkout-pix-page__step-number">3</b>
                        </li>
                    </ol>
                </section>

                <aside class="checkout-pix-page__security">
                    <svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="m21.73 18-8-14a2 2 0 0 0-3.46 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/>
                        <path d="M12 9v4"/>
                        <path d="M12 17h.01"/>
                    </svg>
                    <p>
                        Os bancos reforçaram a segurança do Pix e podem exibir alertas
                        preventivos durante o pagamento. Fique tranquilo — sua transação
                        é segura e está totalmente protegida.
                    </p>
                </aside>
            </section>
        </div>

        <div class="checkout-pix-page__expired" data-pix-expired hidden>
            <div class="checkout-pix-page__expired-icon" aria-hidden="true">!</div>
            <h2>Seu PIX expirou</h2>
            <p>Não se preocupe. Volte à loja para gerar um novo pedido com PIX.</p>
            <a href="{{ route('products.index') }}" class="checkout-pix-page__expired-cta">
                Voltar para a loja
            </a>
        </div>
    </div>
</div>
@endsection
