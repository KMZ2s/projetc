@php use Illuminate\Support\Facades\Auth; use Illuminate\Support\Str; @endphp
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Minha Conta') — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 text-gray-900 font-sans">

<header class="bg-white border-b border-gray-200 sticky top-0 z-10">
    <div class="max-w-6xl mx-auto px-4 h-16 flex items-center justify-between gap-4">
        <a href="{{ url('/') }}" class="font-bold text-lg tracking-tight">
            {{ config('app.name') }}
        </a>
        <nav class="flex items-center gap-4 text-sm">
            <a href="{{ url('/') }}" class="text-gray-500 hover:text-gray-900">← Voltar à loja</a>
            <form method="POST" action="{{ url('/logout') }}">
                @csrf
                <button class="text-gray-500 hover:text-gray-900">Sair</button>
            </form>
        </nav>
    </div>
</header>

<div class="max-w-6xl mx-auto px-4 py-8">
    <div class="flex gap-8">

        {{-- Sidebar --}}
        <aside class="w-52 flex-shrink-0">
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div class="px-4 py-4 border-b border-gray-100">
                    <p class="font-semibold text-sm truncate">{{ Auth::user()->name }}</p>
                    <p class="text-xs text-gray-400 truncate">{{ Auth::user()->email }}</p>
                </div>
                <nav class="p-2 flex flex-col gap-0.5">
                    @php
                        $navItems = [
                            ['route' => 'account.index',     'label' => 'Visão geral'],
                            ['route' => 'account.orders',    'label' => 'Meus pedidos'],
                            ['route' => 'account.profile',   'label' => 'Meu perfil'],
                            ['route' => 'account.addresses', 'label' => 'Endereços'],
                        ];
                    @endphp
                    @foreach ($navItems as $item)
                        <a href="{{ route($item['route']) }}"
                           class="px-3 py-2 rounded-lg text-sm transition
                               {{ request()->routeIs($item['route'])
                                   ? 'bg-gray-900 text-white font-medium'
                                   : 'text-gray-600 hover:bg-gray-100' }}">
                            {{ $item['label'] }}
                        </a>
                    @endforeach
                </nav>
            </div>
        </aside>

        {{-- Conteúdo principal --}}
        <main class="flex-1 min-w-0">
            @if (session('success'))
                <div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 rounded-lg text-sm">
                    {{ session('success') }}
                </div>
            @endif

            @yield('content')
        </main>

    </div>
</div>

@stack('scripts')
</body>
</html>