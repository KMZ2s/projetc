@php
    $colors = [
        'pending'    => 'bg-yellow-100 text-yellow-700',
        'processing' => 'bg-blue-100 text-blue-700',
        'paid'       => 'bg-green-100 text-green-700',
        'shipped'    => 'bg-blue-100 text-blue-700',
        'delivered'  => 'bg-green-100 text-green-700',
        'cancelled'  => 'bg-red-100 text-red-700',
        'refunded'   => 'bg-gray-100 text-gray-600',
        'failed'     => 'bg-red-100 text-red-700',
    ];
    $labels = [
        'pending'    => 'Pendente',
        'processing' => 'Processando',
        'paid'       => 'Pago',
        'shipped'    => 'Enviado',
        'delivered'  => 'Entregue',
        'cancelled'  => 'Cancelado',
        'refunded'   => 'Reembolsado',
        'failed'     => 'Falhou',
    ];
    $color = $colors[$status] ?? 'bg-gray-100 text-gray-600';
    $label = $labels[$status] ?? ucfirst($status);
@endphp
<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $color }}">
    {{ $label }}
</span>