@php use Illuminate\Support\Facades\Auth; use Illuminate\Support\Str; @endphp
@extends('account.layout')
@section('title', 'Meus pedidos')

@section('content')

    <h1 class="text-xl font-bold mb-6">Meus pedidos</h1>

    <div class="bg-white rounded-xl border border-gray-200">
        @if ($orders->count())
            <div class="divide-y divide-gray-100">
                @foreach ($orders as $order)
                    <div class="px-5 py-4 flex items-center justify-between gap-4">
                        <div>
                            <a href="{{ route('account.order-detail', $order->order_number) }}"
                               class="font-medium text-sm hover:underline">
                                {{ $order->order_number }}
                            </a>
                            <p class="text-xs text-gray-400 mt-0.5">
                                {{ $order->placed_at->format('d/m/Y \à\s H:i') }}
                                · {{ $order->items->count() }} {{ Str::plural('item', $order->items->count()) }}
                            </p>
                        </div>
                        <div class="flex items-center gap-3">
                            @include('account.partials.status-badge', ['status' => $order->status])
                            <span class="font-semibold text-sm whitespace-nowrap">
                                R$ {{ number_format($order->total, 2, ',', '.') }}
                            </span>
                            <a href="{{ route('account.order-detail', $order->order_number) }}"
                               class="text-xs text-gray-400 hover:text-gray-900">
                                Detalhes →
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="px-5 py-4 border-t border-gray-100">
                {{ $orders->links() }}
            </div>
        @else
            <div class="px-5 py-10 text-center text-sm text-gray-400">
                Nenhum pedido encontrado.
            </div>
        @endif
    </div>

@endsection