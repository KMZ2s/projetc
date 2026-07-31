@php
    $oldMethod = old('payment_method');
    $defaultMethod = $oldMethod
        ?: ($checkoutSettings->pix_enabled && !$checkoutSettings->credit_card_enabled
            ? 'pix'
            : ($checkoutSettings->credit_card_enabled ? 'credit_card' : 'pix'));

    // Total e PIX info pra labels
    $cartTotal     = $cart['total'];
    $pixDiscount   = $checkoutSettings->pix_discount_percent;
    $pixTotal      = $pixDiscount > 0
        ? round($cartTotal * (1 - $pixDiscount / 100), 2)
        : $cartTotal;
@endphp

<section class="checkout-step checkout-step--pending checkout-step-payment"
         data-checkout-step="payment"
         data-step-fields="payment_method,card_number,card_holder_name,card_expiry_month,card_expiry_year,card_cvv">

    {{-- Cabeçalho --}}
    <div class="checkout-step__head">
        <span class="checkout-step__num" aria-hidden="true">3</span>
        <h2 class="checkout-step__title">Pagamento</h2>
    </div>

    <div class="checkout-step__body">
        <p class="checkout-step__payment-intro">Escolha uma forma de pagamento</p>

    {{-- ============================================================ --}}
    {{-- Tabs cartão / PIX (radios estilizados)                         --}}
    {{-- ============================================================ --}}
    <div class="checkout-payment-tabs" role="radiogroup" aria-label="Forma de pagamento">

        @if ($checkoutSettings->credit_card_enabled)
            <label class="checkout-payment-tab" data-payment-tab="credit_card">
                <input type="radio"
                       name="payment_method"
                       value="credit_card"
                       class="checkout-payment-tab__radio"
                       @checked($defaultMethod === 'credit_card')>

                <span class="checkout-payment-tab__icon" aria-hidden="true">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                        <rect x="2" y="5" width="20" height="14" rx="2" stroke="currentColor" stroke-width="2"/>
                        <path d="M2 10h20" stroke="currentColor" stroke-width="2"/>
                        <path d="M6 15h4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </span>

                <div class="checkout-payment-tab__label">
                    <strong>Cartão de crédito</strong>
                    @if ($checkoutSettings->installments_no_interest_max > 1)
                        <small>Em até {{ $checkoutSettings->installments_no_interest_max }}x sem juros</small>
                    @else
                        <small>Pagamento à vista</small>
                    @endif
                </div>

                <span class="checkout-payment-tab__check" aria-hidden="true">✓</span>
            </label>
        @endif

        @if ($checkoutSettings->pix_enabled)
            <label class="checkout-payment-tab" data-payment-tab="pix">
                <input type="radio"
                       name="payment_method"
                       value="pix"
                       class="checkout-payment-tab__radio"
                       @checked($defaultMethod === 'pix')>

                <span class="checkout-payment-tab__radio-mark" aria-hidden="true"></span>
                <span class="checkout-payment-tab__icon" aria-hidden="true">
                    <svg width="20" height="20" viewBox="0 0 166 166" fill="currentColor">
                        <path d="M128.886 126.429c-6.484 0-12.582-2.525-17.167-7.108L86.931 94.533c-1.74-1.745-4.774-1.74-6.514 0l-24.879 24.879c-4.585 4.582-10.683 7.107-17.168 7.107h-4.884l31.394 31.395c9.805 9.804 25.702 9.804 35.507 0l31.486-31.485h-2.987ZM38.371 38.748c6.484 0 12.582 2.525 17.167 7.108l24.879 24.883c1.791 1.792 4.718 1.8 6.514-.002l24.788-24.791c4.585-4.583 10.683-7.107 17.168-7.107h2.985L100.388 7.354c-9.806-9.805-25.703-9.805-35.508 0L33.487 38.748h4.884Z"/>
                        <path d="m157.914 64.881-19.026-19.027a3.64 3.64 0 0 1-1.351.273h-8.65c-4.473 0-8.851 1.813-12.011 4.976L92.087 75.891a11.9 11.9 0 0 1-8.412 3.481 11.9 11.9 0 0 1-8.413-3.478L50.381 51.012c-3.161-3.162-7.538-4.975-12.011-4.975H27.734c-.454 0-.878-.107-1.278-.258L7.354 64.881c-9.805 9.804-9.805 25.701 0 35.507l19.101 19.101a3.65 3.65 0 0 1 1.279-.258H38.37c4.473 0 8.85-1.814 12.011-4.976L75.26 89.376c4.497-4.493 12.335-4.495 16.827.003l24.789 24.785c3.16 3.163 7.538 4.977 12.011 4.977h8.65c.479 0 .932.104 1.351.272l19.026-19.025c9.805-9.806 9.805-25.703 0-35.507Z"/>
                    </svg>
                </span>

                <div class="checkout-payment-tab__label">
                    <strong>Pix</strong>
                </div>
            </label>
        @endif

    </div>

    {{-- ============================================================ --}}
    {{-- Conteúdo das tabs                                              --}}
    {{-- ============================================================ --}}
    <div class="checkout-payment-bodies">

        @if ($checkoutSettings->credit_card_enabled)
            <div @class([
                     'checkout-payment-body',
                     'is-active' => $defaultMethod === 'credit_card',
                 ])
                 data-payment-body="credit_card">
                @include('checkout.partials.payment-tab-card')
            </div>
        @endif

        @if ($checkoutSettings->pix_enabled)
            <div @class([
                     'checkout-payment-body',
                     'is-active' => $defaultMethod === 'pix',
                 ])
                 data-payment-body="pix">
                @include('checkout.partials.payment-tab-pix')
            </div>
        @endif

    </div>

    {{-- Campos de device data (3DS) — preenchidos pelo JS no B4 --}}
    <input type="hidden" name="device_language"        data-device-field="language">
    <input type="hidden" name="device_color_depth"     data-device-field="color_depth">
    <input type="hidden" name="device_screen_height"   data-device-field="screen_height">
    <input type="hidden" name="device_screen_width"    data-device-field="screen_width">
    <input type="hidden" name="device_time_difference" data-device-field="time_difference">
    <input type="hidden" name="device_java_enabled"    data-device-field="java_enabled" value="0">

    <button type="submit" class="checkout-summary__cta checkout-payment__cta" data-checkout-submit>
        <span data-cta-label>
            Finalizar Compra • R$ <span data-cta-total>{{ number_format($pixTotal, 2, ',', '.') }}</span>
        </span>
        <span class="checkout-summary__cta-spinner" data-cta-spinner aria-hidden="true"></span>
    </button>

    </div>
</section>
