@php use Illuminate\Support\Facades\Auth; use Illuminate\Support\Str; @endphp
@extends('account.layout')
@section('title', 'Visão geral')

@section('content')

    <h1 class="text-xl font-bold mb-6">Olá, {{ Auth::user()->name }} 👋</h1>

    <div class="grid grid-cols-3 gap-4 mb-8">
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">Total de pedidos</p>
            <p class="text-2xl font-bold">{{ Auth::user()->orders()->count() }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">Pedidos pendentes</p>
            <p class="text-2xl font-bold">{{ Auth::user()->orders()->where('status', 'pending')->count() }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">Endereços salvos</p>
            <p class="text-2xl font-bold">{{ Auth::user()->addresses()->count() }}</p>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-gray-200">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <h2 class="font-semibold text-sm">Pedidos recentes</h2>
            <a href="{{ route('account.orders') }}" class="text-xs text-gray-400 hover:text-gray-900">Ver todos</a>
        </div>

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
                                {{ $order->placed_at->format('d/m/Y') }}
                                · {{ $order->items->count() }} {{ Str::plural('item', $order->items->count()) }}
                            </p>
                        </div>
                        <div class="flex items-center gap-3">
                            @include('account.partials.status-badge', ['status' => $order->status])
                            <span class="font-semibold text-sm">
                                R$ {{ number_format($order->total, 2, ',', '.') }}
                            </span>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="px-5 py-10 text-center text-sm text-gray-400">
                Você ainda não fez nenhum pedido.
                <a href="{{ url('/') }}" class="block mt-2 text-gray-900 font-medium hover:underline">
                    Começar a comprar →
                </a>
            </div>
        @endif
    </div>

@endsection