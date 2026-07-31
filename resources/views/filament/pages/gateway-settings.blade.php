<x-filament-panels::page>
<div style="display:flex; flex-direction:column; gap:1.5rem;">

    @foreach ($this->definitions as $definition)
        @php
            $slug     = $definition['slug'];
            $state    = $gateways[$slug] ?? [];
            $isActive = $state['is_active'] ?? false;
        @endphp

        <x-filament::section>

            {{-- Cabeçalho --}}
            <x-slot name="heading">
                <div style="display:flex; align-items:center; gap:.75rem;">
                    <span>{{ $definition['label'] }}</span>
                    <span style="
                        display:inline-flex; align-items:center;
                        padding:.15rem .6rem; border-radius:99px;
                        font-size:.7rem; font-weight:600;
                        background:{{ $isActive ? 'rgba(74,222,128,.15)' : 'rgba(255,255,255,.07)' }};
                        color:{{ $isActive ? '#4ade80' : 'rgba(255,255,255,.4)' }};">
                        {{ $isActive ? 'ATIVO' : 'INATIVO' }}
                    </span>
                </div>
            </x-slot>

            @if (!empty($definition['description']))
                <x-slot name="description">{{ $definition['description'] }}</x-slot>
            @endif

            {{-- Campos --}}
            <div style="display:flex; flex-direction:column; gap:1rem; margin-bottom:1.25rem;">
                @foreach ($definition['fields'] as $field)
                    <div>
                        <label style="display:block; font-size:.8rem; font-weight:500;
                                      color:rgba(255,255,255,.6); margin-bottom:.35rem;">
                            {{ $field['label'] }}
                            @if (!empty($field['hint']))
                                <span style="font-weight:400; color:rgba(255,255,255,.35); margin-left:.35rem;">
                                    — {{ $field['hint'] }}
                                </span>
                            @endif
                        </label>
                        <input
                            type="{{ $field['type'] === 'password' ? 'password' : 'text' }}"
                            wire:model="gateways.{{ $slug }}.{{ $field['key'] }}"
                            placeholder="{{ $field['placeholder'] ?? ('Insira ' . $field['label']) }}"
                            style="width:100%; padding:.5rem .75rem; border-radius:.5rem;
                                   border:1px solid rgba(255,255,255,.12);
                                   background:rgba(255,255,255,.05);
                                   color:rgba(255,255,255,.85);
                                   font-size:.875rem; font-family:monospace;">
                    </div>
                @endforeach

                {{-- URL do Webhook — somente leitura --}}
                <div>
                    <label style="display:block; font-size:.8rem; font-weight:500;
                                  color:rgba(255,255,255,.6); margin-bottom:.35rem;">
                        URL do Webhook
                        <span style="font-weight:400; color:rgba(255,255,255,.35); margin-left:.35rem;">
                            — configure no painel da {{ $definition['label'] }}
                        </span>
                    </label>
                    <div style="display:flex; gap:.5rem; align-items:center;">
                        <input
                            type="text"
                            id="webhook-url-{{ $slug }}"
                            value="{{ $definition['webhook_url'] }}"
                            readonly
                            style="flex:1; padding:.5rem .75rem; border-radius:.5rem;
                                   border:1px solid rgba(255,255,255,.08);
                                   background:rgba(255,255,255,.02);
                                   color:rgba(255,255,255,.5);
                                   font-size:.8rem; font-family:monospace; cursor:default;">
                        <button
                            type="button"
                            onclick="copyWebhookUrl('{{ $slug }}')"
                            id="copy-btn-{{ $slug }}"
                            style="padding:.5rem .9rem; border-radius:.5rem; font-size:.8rem;
                                   font-weight:500; cursor:pointer; white-space:nowrap;
                                   border:1px solid rgba(255,255,255,.12);
                                   background:rgba(255,255,255,.05);
                                   color:rgba(255,255,255,.6);
                                   transition:all .15s;">
                            Copiar URL
                        </button>
                    </div>
                </div>
            </div>

            {{-- Ações --}}
            <div style="display:flex; gap:.75rem; flex-wrap:wrap; align-items:center;
                        padding-top:1rem; border-top:1px solid rgba(255,255,255,.07);">

                <x-filament::button
                    wire:click="save('{{ $slug }}')"
                    icon="heroicon-o-check"
                    color="gray">
                    Salvar credenciais
                </x-filament::button>

                <x-filament::button
                    wire:click="testConnection('{{ $slug }}')"
                    icon="heroicon-o-signal"
                    color="primary">
                    Testar conexão
                </x-filament::button>

                @if ($isActive)
                    <x-filament::button
                        wire:click="toggle('{{ $slug }}')"
                        wire:confirm="Deseja desativar o gateway {{ $definition['label'] }}?"
                        icon="heroicon-o-pause"
                        color="danger">
                        Desativar
                    </x-filament::button>
                @else
                    <x-filament::button
                        wire:click="toggle('{{ $slug }}')"
                        icon="heroicon-o-play"
                        color="success">
                        Ativar
                    </x-filament::button>
                @endif

            </div>

        </x-filament::section>
    @endforeach

    <p style="font-size:.75rem; color:rgba(255,255,255,.25); text-align:center;">
        Para adicionar um novo gateway, peça ao desenvolvedor para incluí-lo no código.
    </p>

</div>

<script>
function copyWebhookUrl(slug) {
    const input = document.getElementById('webhook-url-' + slug);
    const btn   = document.getElementById('copy-btn-' + slug);
    if (!input) return;

    navigator.clipboard.writeText(input.value).then(() => {
        btn.textContent = '✓ Copiado!';
        btn.style.color = '#4ade80';
        setTimeout(() => {
            btn.textContent = 'Copiar URL';
            btn.style.color = 'rgba(255,255,255,.6)';
        }, 2500);
    });
}
</script>
</x-filament-panels::page>