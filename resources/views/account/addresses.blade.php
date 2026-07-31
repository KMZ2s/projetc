@php use Illuminate\Support\Facades\Auth; use Illuminate\Support\Str; @endphp
@extends('account.layout')
@section('title', 'Endereços')

@section('content')

    <h1 class="text-xl font-bold mb-6">Endereços</h1>

    @if ($addresses->count())
        <div class="grid grid-cols-2 gap-4 mb-6">
            @foreach ($addresses as $address)
                <div class="bg-white rounded-xl border border-gray-200 p-4 relative">
                    @if ($address->is_default)
                        <span class="absolute top-3 right-3 text-xs bg-gray-900 text-white px-2 py-0.5 rounded-full">Padrão</span>
                    @endif
                    <p class="text-sm font-medium mb-1">
                        {{ $address->street }}, {{ $address->number }}
                        @if ($address->complement) — {{ $address->complement }} @endif
                    </p>
                    <p class="text-xs text-gray-500">
                        {{ $address->neighborhood }} · {{ $address->city }}/{{ $address->state }}<br>
                        CEP {{ $address->zipcode }}
                    </p>
                    <form method="POST" action="{{ route('account.addresses.destroy', $address->id) }}" class="mt-3">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-xs text-red-400 hover:text-red-600"
                            onclick="return confirm('Remover este endereço?')">
                            Remover
                        </button>
                    </form>
                </div>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('account.addresses.store') }}" class="bg-white rounded-xl border border-gray-200 p-5">
        @csrf
        <h2 class="font-semibold text-sm mb-4">Adicionar endereço</h2>
        <div class="grid grid-cols-2 gap-4 mb-4">
            <div>
                <label class="block text-xs text-gray-500 mb-1">CEP</label>
                <input type="text" name="zipcode" id="cep" placeholder="00000-000"
                    class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
            </div>
            <div class="col-span-2 grid grid-cols-3 gap-4">
                <div class="col-span-2">
                    <label class="block text-xs text-gray-500 mb-1">Rua</label>
                    <input type="text" name="street" id="street"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Número</label>
                    <input type="text" name="number"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
                </div>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Complemento</label>
                <input type="text" name="complement"
                    class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Bairro</label>
                <input type="text" name="neighborhood" id="neighborhood"
                    class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Cidade</label>
                <input type="text" name="city" id="city"
                    class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Estado</label>
                <input type="text" name="state" id="state" maxlength="2" placeholder="SP"
                    class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
            </div>
            <div class="col-span-2 flex items-center gap-2">
                <input type="checkbox" name="is_default" id="is_default" value="1">
                <label for="is_default" class="text-sm text-gray-600">Definir como endereço padrão</label>
            </div>
        </div>
        <button type="submit" class="bg-gray-900 text-white text-sm px-4 py-2 rounded-lg hover:bg-gray-700 transition">
            Salvar endereço
        </button>
    </form>

@endsection

@push('scripts')
<script>
document.getElementById('cep').addEventListener('blur', function () {
    const cep = this.value.replace(/\D/g, '');
    if (cep.length !== 8) return;
    fetch(`https://viacep.com.br/ws/${cep}/json/`)
        .then(r => r.json())
        .then(d => {
            if (d.erro) return;
            document.getElementById('street').value       = d.logradouro || '';
            document.getElementById('neighborhood').value = d.bairro || '';
            document.getElementById('city').value         = d.localidade || '';
            document.getElementById('state').value        = d.uf || '';
        });
});
</script>
@endpush