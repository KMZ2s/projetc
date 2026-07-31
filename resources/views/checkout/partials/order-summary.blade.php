@php
    $items = $cart['items'] ?? [];
    $subtotal = (float) ($cart['subtotal'] ?? 0);
    $discount = (float) ($cart['discount'] ?? 0);
    $total = (float) ($cart['total'] ?? 0);
    $coupon = $cart['coupon'] ?? null;
    $count = (int) ($cart['count'] ?? 0);
@endphp

<div class="checkout-summary" data-checkout-summary
    data-base-total="{{ number_format($total, 2, '.', '') }}"
     data-cart-update-url="{{ route('cart.update') }}">
    <button type="button" class="checkout-summary__mobile-toggle" data-summary-toggle
            aria-expanded="true" aria-controls="checkout-summary-panel">
        <span>RESUMO (<span data-cart-count>{{ $count }}</span>)</span>
        <strong>R$ <span data-mobile-total>{{ number_format($total, 2, ',', '.') }}</span></strong>
        <svg class="checkout-summary__chevron" aria-hidden="true" width="16" height="16"
             viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round">
            <path d="m18 15-6-6-6 6"/>
        </svg>
    </button>

    <div class="checkout-summary__panel" id="checkout-summary-panel" data-summary-panel>
        <div class="checkout-summary__head">
            <h3 class="checkout-summary__title">RESUMO (<span data-cart-count>{{ $count }}</span>)</h3>
        </div>

        <div class="checkout-coupon"
             data-coupon
             data-coupon-url="{{ route('cart.coupon.apply') }}">
            @if ($coupon)
                <div class="checkout-coupon__active" data-coupon-active>
                    <span class="checkout-coupon__active-check" aria-hidden="true">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="m20 6-11 11-5-5"/>
                        </svg>
                    </span>
                    <span class="checkout-coupon__active-code">{{ $coupon['code'] }}</span>
                    @if ($discount > 0)
                        <strong class="checkout-coupon__active-discount">
                            −R$ {{ number_format($discount, 2, ',', '.') }}
                        </strong>
                    @endif
                    <button type="button"
                            class="checkout-coupon__remove"
                            data-coupon-remove
                            aria-label="Remover cupom {{ $coupon['code'] }}">
                        <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2" stroke-linecap="round">
                            <path d="M18 6 6 18"/>
                            <path d="m6 6 12 12"/>
                        </svg>
                    </button>
                </div>
            @else
                <label for="checkout-coupon">Tem um cupom?</label>
                <div class="checkout-coupon__row">
                    <input id="checkout-coupon" type="text"
                           placeholder="Código do cupom" autocomplete="off"
                           autocapitalize="characters" spellcheck="false"
                           data-coupon-input>
                    <button type="button" data-coupon-apply>Aplicar</button>
                </div>
            @endif
            <p class="checkout-coupon__message" data-coupon-message aria-live="polite"></p>
        </div>

        <ul class="checkout-summary__items" role="list">
            @foreach ($items as $item)
                <li class="checkout-summary__item"
                    data-cart-item
                    data-cart-key="{{ $item['key'] }}"
                    data-cart-quantity="{{ $item['quantity'] }}"
                    data-cart-unit-price="{{ number_format((float) $item['price'], 2, '.', '') }}">
                    <div class="checkout-summary__item-image">
                        @if (!empty($item['image']))
                            <img src="{{ $item['image'] }}" alt="{{ $item['name'] }}"
                                 loading="lazy" width="48" height="48">
                        @else
                            <span class="checkout-summary__item-image-placeholder" aria-hidden="true">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                                     stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                     stroke-linejoin="round">
                                    <path d="m7.5 4.27 9 5.15"/>
                                    <path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/>
                                    <path d="m3.3 7 8.7 5 8.7-5"/>
                                    <path d="M12 22V12"/>
                                </svg>
                            </span>
                        @endif
                    </div>
                    <div class="checkout-summary__item-info">
                        <h4 class="checkout-summary__item-name">{{ $item['name'] }}</h4>
                        <div class="checkout-summary__item-price" data-cart-item-price>
                            R$ {{ number_format($item['price'] * $item['quantity'], 2, ',', '.') }}
                        </div>
                        <div class="checkout-summary__quantity" aria-label="Quantidade de {{ $item['name'] }}">
                            <button type="button" data-cart-quantity-change="-1"
                                    aria-label="Diminuir quantidade de {{ $item['name'] }}">
                                <svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24"
                                     fill="none" stroke="currentColor" stroke-width="2"
                                     stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M5 12h14"/>
                                </svg>
                            </button>
                            <span data-cart-item-quantity>{{ $item['quantity'] }}</span>
                            <button type="button" data-cart-quantity-change="1"
                                    aria-label="Aumentar quantidade de {{ $item['name'] }}">
                                <svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24"
                                     fill="none" stroke="currentColor" stroke-width="2"
                                     stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M5 12h14"/>
                                    <path d="M12 5v14"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </li>
            @endforeach
        </ul>

        <dl class="checkout-summary__totals">
            <div class="checkout-summary__total-row">
                <dt>Subtotal</dt>
                <dd>R$ <span data-cart-subtotal>{{ number_format($subtotal, 2, ',', '.') }}</span></dd>
            </div>

            @if ($coupon && $discount > 0)
                <div class="checkout-summary__total-row checkout-summary__total-row--discount">
                    <dt>Cupom {{ $coupon['code'] }}</dt>
                    <dd>−R$ {{ number_format($discount, 2, ',', '.') }}</dd>
                </div>
            @endif

            <div class="checkout-summary__total-row is-empty" data-shipping-row>
                <dt>
                    <svg aria-hidden="true" width="12" height="12" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"/>
                        <path d="M15 18H9"/>
                        <path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14"/>
                        <circle cx="17" cy="18" r="2"/>
                        <circle cx="7" cy="18" r="2"/>
                    </svg>
                    <span data-shipping-label-summary>Frete</span>
                </dt>
                <dd data-shipping-amount>A calcular</dd>
            </div>

            <div class="checkout-summary__total-row checkout-summary__total-row--pix-discount"
                 data-pix-discount-row hidden>
                <dt>Desconto PIX</dt>
                <dd data-pix-discount-value>−R$ 0,00</dd>
            </div>

            <div class="checkout-summary__total-row checkout-summary__total-row--final">
                <dt>Total</dt>
                <dd>
                    <span class="checkout-summary__total-currency">R$</span>
                    <span class="checkout-summary__total-amount" data-total-amount>
                        {{ number_format($total, 2, ',', '.') }}
                    </span>
                </dd>
            </div>
        </dl>

    </div>
</div>

<div class="checkout-trust" aria-label="Garantias da compra">
    <article class="checkout-trust__item">
        <div class="checkout-trust__icon" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/>
            </svg>
        </div>
        <div>
            <div class="checkout-trust__stars" aria-label="5 estrelas">
                @for ($star = 0; $star < 5; $star++)
                    <svg aria-hidden="true" width="12" height="12" viewBox="0 0 24 24"
                         fill="currentColor" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round">
                        <path d="M11.525 2.295a.53.53 0 0 1 .95 0l2.31 4.679a2.123 2.123 0 0 0 1.595 1.16l5.166.756a.53.53 0 0 1 .294.904l-3.736 3.638a2.123 2.123 0 0 0-.611 1.878l.882 5.14a.53.53 0 0 1-.771.56l-4.618-2.428a2.122 2.122 0 0 0-1.973 0L6.396 21.01a.53.53 0 0 1-.77-.56l.881-5.139a2.122 2.122 0 0 0-.611-1.879L2.16 9.795a.53.53 0 0 1 .294-.906l5.165-.755a2.122 2.122 0 0 0 1.597-1.16z"/>
                    </svg>
                @endfor
            </div>
            <h3>Mercado Pago</h3>
            <p>Nossos pagamentos são gerenciados pelo Mercado Pago. Segurança criptografada em todas as compras.</p>
        </div>
    </article>
    <article class="checkout-trust__item">
        <div class="checkout-trust__icon" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/>
                <path d="M3 3v5h5"/>
                <path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"/>
                <path d="M16 16h5v5"/>
            </svg>
        </div>
        <div>
            <div class="checkout-trust__stars" aria-label="5 estrelas">
                @for ($star = 0; $star < 5; $star++)
                    <svg aria-hidden="true" width="12" height="12" viewBox="0 0 24 24"
                         fill="currentColor" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round">
                        <path d="M11.525 2.295a.53.53 0 0 1 .95 0l2.31 4.679a2.123 2.123 0 0 0 1.595 1.16l5.166.756a.53.53 0 0 1 .294.904l-3.736 3.638a2.123 2.123 0 0 0-.611 1.878l.882 5.14a.53.53 0 0 1-.771.56l-4.618-2.428a2.122 2.122 0 0 0-1.973 0L6.396 21.01a.53.53 0 0 1-.77-.56l.881-5.139a2.122 2.122 0 0 0-.611-1.879L2.16 9.795a.53.53 0 0 1 .294-.906l5.165-.755a2.122 2.122 0 0 0 1.597-1.16z"/>
                    </svg>
                @endfor
            </div>
            <h3>Garantia de Reembolso</h3>
            <p>Receba sua compra ou nossa equipe devolverá todo seu dinheiro de volta na sua conta em poucos minutos.</p>
        </div>
    </article>
    <article class="checkout-trust__item">
        <div class="checkout-trust__icon" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"/>
                <path d="M15 18H9"/>
                <path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14"/>
                <circle cx="17" cy="18" r="2"/>
                <circle cx="7" cy="18" r="2"/>
            </svg>
        </div>
        <div>
            <div class="checkout-trust__stars" aria-label="5 estrelas">
                @for ($star = 0; $star < 5; $star++)
                    <svg aria-hidden="true" width="12" height="12" viewBox="0 0 24 24"
                         fill="currentColor" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round">
                        <path d="M11.525 2.295a.53.53 0 0 1 .95 0l2.31 4.679a2.123 2.123 0 0 0 1.595 1.16l5.166.756a.53.53 0 0 1 .294.904l-3.736 3.638a2.123 2.123 0 0 0-.611 1.878l.882 5.14a.53.53 0 0 1-.771.56l-4.618-2.428a2.122 2.122 0 0 0-1.973 0L6.396 21.01a.53.53 0 0 1-.77-.56l.881-5.139a2.122 2.122 0 0 0-.611-1.879L2.16 9.795a.53.53 0 0 1 .294-.906l5.165-.755a2.122 2.122 0 0 0 1.597-1.16z"/>
                    </svg>
                @endfor
            </div>
            <h3>Entrega Segura</h3>
            <p>Já entregamos mais de 5.000 produtos para todo o Brasil!</p>
        </div>
    </article>
</div>
