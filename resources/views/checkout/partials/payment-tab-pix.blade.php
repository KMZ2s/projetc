@php
    $pixDiscount = (int) $checkoutSettings->pix_discount_percent;
    $pixTotal = $pixDiscount > 0
        ? round($cart['total'] * (1 - $pixDiscount / 100), 2)
        : $cart['total'];
@endphp

<p class="checkout-pix-description">
    A confirmação é realizada em poucos minutos. Use o app do seu banco para pagar.
</p>
