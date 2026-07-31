@php
    // Calcula valores do downsell baseado no subtotal da Order original.
    // Desconto downsell IGNORA cupom (substitui qualquer desconto anterior).
    $oldTotal      = (float) $failedOrder->total;
    $newSubtotal   = (float) $failedOrder->subtotal;
    $discountPct   = $checkoutSettings->downsell_pix_discount_percent;
    $newDiscount   = round($newSubtotal * ($discountPct / 100), 2);
    $newTotal      = $newSubtotal - $newDiscount;
    $savings       = $oldTotal - $newTotal;
@endphp

<div class="checkout-downsell"
     data-downsell-modal
     role="dialog"
     aria-modal="true"
     aria-labelledby="downsell-title"
     aria-describedby="downsell-subtitle">

    <div class="checkout-downsell__overlay" aria-hidden="true"></div>

    <div class="checkout-downsell__content" role="document">

        {{-- Botão de fechar (X) --}}
        <button type="button"
                class="checkout-downsell__close"
                data-downsell-close
                aria-label="Fechar e tentar outro cartão">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
        </button>

        {{-- Header --}}
        <div class="checkout-downsell__header">
            <div class="checkout-downsell__icon" aria-hidden="true">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none">
                    <path d="M5 12l4-4 3 3-4 4-3-3zM15 5l4 4-3 3-4-4 3-3zM12 19l-3-3 4-4 3 3-4 4zM19 12l-3 3-4-4 4-3 3 4z"
                          stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                </svg>
            </div>

            <h2 id="downsell-title" class="checkout-downsell__title">
                {{ $checkoutSettings->downsell_title }}
            </h2>

            <p id="downsell-subtitle" class="checkout-downsell__subtitle">
                {{ $checkoutSettings->downsell_subtitle }}
            </p>
        </div>

        {{-- Comparação de preços --}}
        <div class="checkout-downsell__pricing">

            <div class="checkout-downsell__pricing-row checkout-downsell__pricing-row--old">
                <span class="checkout-downsell__pricing-label">Cartão recusado</span>
                <span class="checkout-downsell__price-old">
                    R$ {{ number_format($oldTotal, 2, ',', '.') }}
                </span>
            </div>

            <div class="checkout-downsell__pricing-savings">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M12 19V5M5 12l7-7 7 7" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <span>Economize R$ {{ number_format($savings, 2, ',', '.') }} ({{ $discountPct }}%)</span>
            </div>

            <div class="checkout-downsell__pricing-row checkout-downsell__pricing-row--new">
                <span class="checkout-downsell__pricing-label">Pague com PIX</span>
                <span class="checkout-downsell__price-new">
                    R$ {{ number_format($newTotal, 2, ',', '.') }}
                </span>
            </div>

        </div>

        {{-- CTAs --}}
        <form action="{{ route('checkout.retry-pix', $failedOrder) }}"
              method="POST"
              class="checkout-downsell__form">
            @csrf
            <button type="submit" class="checkout-downsell__cta" data-downsell-submit>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M5 12l4-4 3 3-4 4-3-3zM15 5l4 4-3 3-4-4 3-3zM12 19l-3-3 4-4 3 3-4 4zM19 12l-3 3-4-4 4-3 3 4z"
                          stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                </svg>
                <span>Pagar com PIX por R$ {{ number_format($newTotal, 2, ',', '.') }}</span>
                <span class="checkout-downsell__cta-spinner" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                        <path d="M21 12a9 9 0 11-9-9" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </span>
            </button>
        </form>

        <button type="button"
                class="checkout-downsell__secondary"
                data-downsell-close>
            Tentar com outro cartão
        </button>

    </div>
</div>