@php
    $items   = $order->items;
    $address = $order->shippingAddress;

    // Estados (só 1 é true)
    $isPaid       = $order->isPaid();
    $isPixPending = $order->isPixPending();
    $isFailed     = !$isPaid && !$isPixPending;

    // Detalhes de pagamento
    $paymentData  = $order->payment_data ?? [];
    $cardBrand    = $paymentData['card_brand']  ?? null;
    $lastDigits   = $paymentData['last_digits'] ?? null;
    $installments = $paymentData['installments'] ?? null;
    $reason       = $paymentData['reason'] ?? null;

    // Forma de pagamento legível
    $methodLabel = match ($order->payment_method) {
        'pix'         => 'PIX',
        'credit_card' => 'Cartão de crédito'
                         . ($cardBrand ? ' ' . ucfirst($cardBrand) : '')
                         . ($lastDigits ? " final {$lastDigits}" : ''),
        'debit_card'  => 'Cartão de débito'
                         . ($cardBrand ? ' ' . ucfirst($cardBrand) : '')
                         . ($lastDigits ? " final {$lastDigits}" : ''),
        default       => $order->payment_method,
    };
@endphp

@extends('checkout.layout')

@section('title', $isPaid
    ? "Pedido #{$order->order_number} confirmado"
    : "Pedido #{$order->order_number}")

@section('content')
<div class="checkout-confirmation">
    <div class="checkout-confirmation__container">

        {{-- ============================================================ --}}
        {{-- ESTADO: PAGO                                                   --}}
        {{-- ============================================================ --}}
        @if ($isPaid)

            <div class="checkout-confirmation__hero">
                <div class="checkout-confirmation__icon checkout-confirmation__icon--paid" aria-hidden="true">
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none">
                        <path d="M5 12l5 5 9-9"
                              stroke="currentColor" stroke-width="2.5"
                              stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>

                <h1 class="checkout-confirmation__title">Pedido confirmado!</h1>

                <p class="checkout-confirmation__order-number">
                    Pedido <strong>#{{ $order->order_number }}</strong>
                </p>

                <p class="checkout-confirmation__message">
                    Recebemos seu pagamento e estamos preparando seu pedido.
                    Você receberá atualizações por email assim que ele for despachado.
                </p>
            </div>

            <div class="checkout-confirmation__divider" role="presentation"></div>

            {{-- Resumo do pedido --}}
            <section class="checkout-confirmation__section">
                <h2 class="checkout-confirmation__section-title">Resumo do pedido</h2>

                <ul class="checkout-confirmation__items" role="list">
                    @foreach ($items as $item)
                        <li class="checkout-confirmation__item">
                            <div class="checkout-confirmation__item-info">
                                <span class="checkout-confirmation__item-name">
                                    {{ $item->product_name }}
                                </span>
                                <span class="checkout-confirmation__item-qty">
                                    {{ $item->quantity }} {{ $item->quantity === 1 ? 'unidade' : 'unidades' }}
                                </span>
                            </div>
                            <span class="checkout-confirmation__item-price">
                                R$ {{ number_format($item->total_price, 2, ',', '.') }}
                            </span>
                        </li>
                    @endforeach
                </ul>

                <dl class="checkout-confirmation__totals">
                    <div class="checkout-confirmation__total-row">
                        <dt>Subtotal</dt>
                        <dd>R$ {{ number_format($order->subtotal, 2, ',', '.') }}</dd>
                    </div>

                    @if ($order->discount_total > 0)
                        <div class="checkout-confirmation__total-row checkout-confirmation__total-row--discount">
                            <dt>Desconto</dt>
                            <dd>−R$ {{ number_format($order->discount_total, 2, ',', '.') }}</dd>
                        </div>
                    @endif

                    <div class="checkout-confirmation__total-row checkout-confirmation__total-row--final">
                        <dt>Total pago</dt>
                        <dd>R$ {{ number_format($order->total, 2, ',', '.') }}</dd>
                    </div>
                </dl>
            </section>

            <div class="checkout-confirmation__divider" role="presentation"></div>

            {{-- Pagamento --}}
            <section class="checkout-confirmation__section">
                <h2 class="checkout-confirmation__section-title">Pagamento</h2>
                <dl class="checkout-confirmation__payment-info">
                    <div>
                        <dt>Forma</dt>
                        <dd>{{ $methodLabel }}</dd>
                    </div>
                    @if ($installments && $installments > 1)
                        <div>
                            <dt>Parcelas</dt>
                            <dd>{{ $installments }}x</dd>
                        </div>
                    @endif
                    <div>
                        <dt>Pedido feito em</dt>
                        <dd>{{ $order->placed_at?->format('d/m/Y \à\s H:i') }}</dd>
                    </div>
                </dl>
            </section>

            @if ($address)
                <div class="checkout-confirmation__divider" role="presentation"></div>

                {{-- Endereço --}}
                <section class="checkout-confirmation__section">
                    <h2 class="checkout-confirmation__section-title">Endereço de entrega</h2>
                    <address class="checkout-confirmation__address">
                        <strong>{{ $order->customer_name ?? $order->user?->display_name ?? 'Cliente' }}</strong>
                        {{ $address->street }}, {{ $address->number }}@if ($address->complement) — {{ $address->complement }}@endif
                        {{ $address->neighborhood }} · {{ $address->city }}/{{ $address->state }}
                        CEP {{ $address->zipcode }}
                    </address>
                </section>
            @endif

            <div class="checkout-confirmation__divider" role="presentation"></div>

            {{-- CTAs --}}
            <div class="checkout-confirmation__actions">
                @auth
                    <a href="{{ route('account.orders') }}" class="checkout-confirmation__cta">
                        Ver meus pedidos
                    </a>
                @endauth
                <a href="{{ url('/produtos') }}" class="checkout-confirmation__secondary">
                    Continuar comprando
                </a>
            </div>

        {{-- ============================================================ --}}
        {{-- ESTADO: PIX PENDENTE                                          --}}
        {{-- ============================================================ --}}
        @elseif ($isPixPending)

            <div class="checkout-confirmation__hero">
                <div class="checkout-confirmation__icon checkout-confirmation__icon--pending" aria-hidden="true">
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none">
                        <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2"/>
                        <path d="M12 7v5l3 2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </div>

                <h1 class="checkout-confirmation__title">Aguardando pagamento</h1>

                <p class="checkout-confirmation__order-number">
                    Pedido <strong>#{{ $order->order_number }}</strong>
                </p>

                <p class="checkout-confirmation__message">
                    Seu PIX ainda não foi confirmado. Volte para a tela de pagamento
                    para escanear o QR Code novamente ou copiar o código.
                </p>
            </div>

            <div class="checkout-confirmation__actions">
                <a href="{{ route('checkout.pix', $order) }}" class="checkout-confirmation__cta">
                    Voltar para a tela de PIX
                </a>
                @auth
                    <a href="{{ route('account.orders') }}" class="checkout-confirmation__secondary">
                        Ver meus pedidos
                    </a>
                @endauth
            </div>

        {{-- ============================================================ --}}
        {{-- ESTADO: FAILED / EDGE                                          --}}
        {{-- ============================================================ --}}
        @else

            <div class="checkout-confirmation__hero">
                <div class="checkout-confirmation__icon checkout-confirmation__icon--failed" aria-hidden="true">
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none">
                        <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2"/>
                        <path d="M12 8v4M12 16h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </div>

                <h1 class="checkout-confirmation__title">Pedido não confirmado</h1>

                <p class="checkout-confirmation__order-number">
                    Pedido <strong>#{{ $order->order_number }}</strong>
                </p>

                <p class="checkout-confirmation__message">
                    @if ($reason)
                        {{ $reason }}
                    @else
                        O pagamento não foi processado com sucesso.
                    @endif
                </p>
            </div>

            <div class="checkout-confirmation__actions">
                <a href="{{ route('checkout') }}" class="checkout-confirmation__cta">
                    Tentar novamente
                </a>
                @auth
                    <a href="{{ route('account.orders') }}" class="checkout-confirmation__secondary">
                        Ver meus pedidos
                    </a>
                @endauth
            </div>

        @endif

    </div>
</div>
@endsection
