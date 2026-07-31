@extends('checkout.layout')

@section('title', 'Finalizar pedido')

@push('head')
    {{-- Metadata pro JS consumir settings --}}
    <meta name="checkout-installments-max"             content="{{ $checkoutSettings->installments_max }}">
    <meta name="checkout-installments-no-interest-max" content="{{ $checkoutSettings->installments_no_interest_max }}">
    <meta name="checkout-pix-discount"                 content="{{ $checkoutSettings->pix_discount_percent }}">
    <meta name="checkout-pix-enabled"                  content="{{ $checkoutSettings->pix_enabled ? '1' : '0' }}">
    <meta name="checkout-card-enabled"                 content="{{ $checkoutSettings->credit_card_enabled ? '1' : '0' }}">
@endpush

@push('scripts')
    <script src="{{ asset('checkout-assets/checkout.js') }}?v={{ filemtime(public_path('checkout-assets/checkout.js')) }}" defer></script>
@endpush

@section('content')
<div class="checkout">
    <div class="checkout__container">

        <ol class="checkout-progress" data-checkout-progress aria-label="Etapas da compra">
            <li class="checkout-progress__step is-active" data-progress-step="identification" aria-current="step">
                <span>1</span><strong>Identificação</strong>
            </li>
            <li class="checkout-progress__step" data-progress-step="delivery">
                <span>2</span><strong>Entrega</strong>
            </li>
            <li class="checkout-progress__step" data-progress-step="payment">
                <span>3</span><strong>Pagamento</strong>
            </li>
        </ol>

        @if (session('error'))
            <div class="checkout-alert checkout-alert--error" role="alert">
                <svg class="checkout-alert__icon" width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
                    <path d="M12 8v4M12 16h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
                <span>{{ session('error') }}</span>
            </div>
        @endif

        <form action="{{ route('checkout.process') }}"
              method="POST"
              class="checkout-grid"
              id="checkout-form"
              data-checkout-form
              novalidate>
            @csrf
            <input type="hidden" name="customer_note" value="{{ old('customer_note') }}">

            <div class="checkout-grid__main">
                @include('checkout.partials.step-identification')
                @include('checkout.partials.step-delivery')
                @include('checkout.partials.step-payment')
            </div>

            <aside class="checkout-grid__summary">
                @include('checkout.partials.order-summary')
            </aside>

        </form>

    </div>
</div>

<div class="checkout-processing" data-checkout-processing hidden role="status" aria-live="assertive">
    <span class="checkout-processing__spinner" aria-hidden="true"></span>
    <strong>aguarde...</strong>
    <p>Estamos processando seu pedido.</p>
    <small>Não feche esta tela.</small>
</div>

{{-- ==================================================================== --}}
{{-- Modal de downsell (renderiza condicionalmente)                       --}}
{{-- ==================================================================== --}}
@if ($failedOrder && $checkoutSettings->downsell_enabled && $checkoutSettings->pix_enabled)
    @include('checkout.partials.downsell-modal')
@endif
@endsection
