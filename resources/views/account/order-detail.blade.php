@php use Illuminate\Support\Facades\Auth; use Illuminate\Support\Str; @endphp
@extends('account.layout')
@section('title', 'Pedido ' . $order->order_number)

@section('content')

    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('account.orders') }}" class="text-sm text-gray-400 hover:text-gray-900">← Pedidos</a>
        <span class="text-gray-300">/</span>
        <h1 class="text-xl font-bold">{{ $order->order_number }}</h1>
        @include('account.partials.status-badge', ['status' => $order->status])
    </div>

    <div class="grid grid-cols-2 gap-4 mb-6">
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs text-gray-400 uppercase tracking-wide mb-2">Data do pedido</p>
            <p class="text-sm font-medium">{{ $order->placed_at->format('d/m/Y \à\s H:i') }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs text-gray-400 uppercase tracking-wide mb-2">Pagamento</p>
            <p class="text-sm font-medium capitalize">{{ $order->payment_method ?? '—' }}</p>
            @include('account.partials.status-badge', ['status' => $order->payment_status])
        </div>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 mb-4">
        <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="font-semibold text-sm">Itens do pedido</h2>
        </div>
        <div class="divide-y divide-gray-100">
            @foreach ($order->items as $item)
                <div class="px-5 py-4 flex items-center justify-between gap-4">
                    <div>
                        <p class="text-sm font-medium">{{ $item->product_name }}</p>
                        @if ($item->variant_sku)
                            <p class="text-xs text-gray-400">SKU: {{ $item->variant_sku }}</p>
                        @endif
                    </div>
                    <div class="text-right">
                        <p class="text-sm text-gray-500">{{ $item->quantity }}× R$ {{ number_format($item->unit_price, 2, ',', '.') }}</p>
                        <p class="text-sm font-semibold">R$ {{ number_format($item->total_price, 2, ',', '.') }}</p>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="px-5 py-4 border-t border-gray-100 flex flex-col gap-2 items-end text-sm">
            <div class="flex gap-8 text-gray-500">
                <span>Subtotal</span>
                <span>R$ {{ number_format($order->subtotal, 2, ',', '.') }}</span>
            </div>
            @if ($order->discount_total > 0)
                <div class="flex gap-8 text-green-600">
                    <span>Desconto</span>
                    <span>− R$ {{ number_format($order->discount_total, 2, ',', '.') }}</span>
                </div>
            @endif
            <div class="flex gap-8 font-bold text-base">
                <span>Total</span>
                <span>R$ {{ number_format($order->total, 2, ',', '.') }}</span>
            </div>
        </div>
    </div>

    @if ($order->shippingAddress)
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs text-gray-400 uppercase tracking-wide mb-2">Endereço de entrega</p>
            <p class="text-sm">
                {{ $order->shippingAddress->street }}, {{ $order->shippingAddress->number }}
                @if ($order->shippingAddress->complement) — {{ $order->shippingAddress->complement }} @endif
                <br>
                {{ $order->shippingAddress->neighborhood }} · {{ $order->shippingAddress->city }}/{{ $order->shippingAddress->state }}
                <br>
                CEP {{ $order->shippingAddress->zipcode }}
            </p>
        </div>
    @endif

@endsection