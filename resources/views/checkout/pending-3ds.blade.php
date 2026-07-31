@extends('checkout.layout')

@section('title', "Autenticação adicional · Pedido {$order->order_number}")

@push('head')
    <meta name="checkout-status-url" content="{{ route('checkout.status', $order) }}">
@endpush

@push('scripts')
    <script src="{{ asset('checkout-assets/checkout-3ds.js') }}" defer></script>
@endpush

@section('content')
<div class="checkout-3ds"
     data-3ds-page
     data-3ds-state="initial"
     data-3ds-status-url="{{ route('checkout.status', $order) }}">

    <div class="checkout-3ds__container">

        {{-- Header --}}
        <div class="checkout-3ds__hero">
            <div class="checkout-3ds__icon" aria-hidden="true">
                <svg width="36" height="36" viewBox="0 0 24 24" fill="none">
                    <path d="M12 2L4 6v6c0 5 3.5 9.5 8 11 4.5-1.5 8-6 8-11V6l-8-4z"
                          stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                    <path d="M9 12l2 2 4-4"
                          stroke="currentColor" stroke-width="2.2"
                          stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>

            <h1 class="checkout-3ds__title">Autenticação adicional</h1>

            <p class="checkout-3ds__subtitle">
                Seu banco precisa confirmar essa transação. Vamos abrir uma janela
                onde você poderá completar a autenticação com segurança.
            </p>

            <div class="checkout-3ds__order">
                <span>Pedido <strong>#{{ $order->order_number }}</strong></span>
                <span class="checkout-3ds__order-amount">R$ {{ number_format($order->total, 2, ',', '.') }}</span>
            </div>
        </div>

        {{-- Como funciona --}}
        <div class="checkout-3ds__steps">
            <h3 class="checkout-3ds__steps-title">Como funciona</h3>
            <ol>
                <li>Clique em <strong>"Iniciar autenticação"</strong> abaixo</li>
                <li>Confirme a transação no app ou pelo SMS do seu banco</li>
                <li>Aguarde — vamos redirecionar você automaticamente</li>
            </ol>
        </div>

        {{-- Form invisível que submete pro ACS na popup --}}
        <form id="checkout-3ds-form"
              action="{{ $threeDs['acs_url'] }}"
              method="POST"
              target="checkout3ds_popup"
              data-3ds-form
              hidden>
            <input type="hidden" name="creq" value="{{ $threeDs['creq'] }}">
        </form>

        {{-- ESTADO: initial — botão pra iniciar --}}
        <button type="button"
                class="checkout-3ds__cta"
                data-3ds-state-show="initial"
                data-3ds-start>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M14 5l7 7-7 7M21 12H3"
                      stroke="currentColor" stroke-width="2"
                      stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <span>Iniciar autenticação</span>
        </button>

        {{-- ESTADO: waiting — aguardando confirmação --}}
        <div class="checkout-3ds__waiting" data-3ds-state-show="waiting">
            <div class="checkout-3ds__waiting-status" role="status" aria-live="polite">
                <span class="checkout-3ds__waiting-spinner" aria-hidden="true">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                        <path d="M21 12a9 9 0 11-9-9"
                              stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </span>
                <span>Aguardando confirmação do banco...</span>
            </div>

            <p class="checkout-3ds__waiting-text">
                Não feche esta janela. Vamos redirecionar você assim que o banco confirmar a transação.
                Se a janela do banco fechou, clique abaixo para reabrir.
            </p>

            <button type="button"
                    class="checkout-3ds__secondary"
                    data-3ds-start>
                Reabrir autenticação
            </button>
        </div>

        {{-- ESTADO: error — popup blocker ou recusa --}}
        <div class="checkout-3ds__error" data-3ds-state-show="error">
            <div class="checkout-3ds__error-icon" aria-hidden="true">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
                    <path d="M12 8v4M12 16h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
            </div>

            <p class="checkout-3ds__error-text" data-3ds-error-message>
                A janela de autenticação foi bloqueada pelo navegador.
            </p>

            <button type="button" class="checkout-3ds__cta" data-3ds-start>
                <span>Tentar novamente</span>
            </button>
        </div>

    </div>
</div>
@endsection