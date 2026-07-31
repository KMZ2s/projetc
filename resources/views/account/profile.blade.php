@php use Illuminate\Support\Facades\Auth; use Illuminate\Support\Str; @endphp
@extends('account.layout')
@section('title', 'Meu perfil')

@section('content')

    <h1 class="text-xl font-bold mb-6">Meu perfil</h1>

    <form method="POST" action="{{ route('account.profile.update') }}" class="bg-white rounded-xl border border-gray-200 p-5 mb-4">
        @csrf
        @method('PUT')
        <h2 class="font-semibold text-sm mb-4">Dados pessoais</h2>
        <div class="grid grid-cols-2 gap-4 mb-4">
            <div>
                <label class="block text-xs text-gray-500 mb-1">Nome completo</label>
                <input type="text" name="name" value="{{ old('name', $user->name) }}"
                    class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm @error('name') border-red-400 @enderror">
                @error('name') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">E-mail</label>
                <input type="email" name="email" value="{{ old('email', $user->email) }}"
                    class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm @error('email') border-red-400 @enderror">
                @error('email') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Telefone</label>
                <input type="text" name="phone" value="{{ old('phone', $user->phone) }}"
                    class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">CPF/CNPJ</label>
                <input type="text" name="cpf_cnpj" value="{{ old('cpf_cnpj', $user->cpf_cnpj) }}"
                    class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
            </div>
        </div>
        <button type="submit" class="bg-gray-900 text-white text-sm px-4 py-2 rounded-lg hover:bg-gray-700 transition">
            Salvar alterações
        </button>
    </form>

    <form method="POST" action="{{ route('account.password.update') }}" class="bg-white rounded-xl border border-gray-200 p-5">
        @csrf
        @method('PUT')
        <h2 class="font-semibold text-sm mb-4">Alterar senha</h2>
        <div class="grid grid-cols-1 gap-4 mb-4 max-w-sm">
            <div>
                <label class="block text-xs text-gray-500 mb-1">Senha atual</label>
                <input type="password" name="current_password"
                    class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm @error('current_password') border-red-400 @enderror">
                @error('current_password') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Nova senha</label>
                <input type="password" name="password"
                    class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm @error('password') border-red-400 @enderror">
                @error('password') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Confirmar nova senha</label>
                <input type="password" name="password_confirmation"
                    class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
            </div>
        </div>
        <button type="submit" class="bg-gray-900 text-white text-sm px-4 py-2 rounded-lg hover:bg-gray-700 transition">
            Alterar senha
        </button>
    </form>

@endsection