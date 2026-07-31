<x-filament-panels::page>
<div style="display:flex; flex-direction:column; gap:1.5rem;" wire:poll.5s="$refresh">

    {{-- ================================================================
         IMPORTAÇÃO
    ================================================================ --}}
    <x-filament::section>
        <x-slot name="heading">Importar CSV</x-slot>
        <x-slot name="description">Importe produtos, clientes ou estoque via arquivo CSV.</x-slot>

        <div style="display:flex; flex-direction:column; gap:1.25rem;">

            {{-- Tipo de importação --}}
            <div>
                <p class="fi-fo-field-wrp-label fi-fo-text-input-label" style="margin-bottom:.5rem;">Tipo de importação</p>
                <div style="display:flex; gap:.5rem; flex-wrap:wrap;">
                    @foreach (['products' => 'Produtos', 'customers' => 'Clientes', 'stock' => 'Estoque'] as $val => $lbl)
                        <button type="button" wire:click="$set('importType', '{{ $val }}')"
                            style="padding:.4rem 1rem; border-radius:.5rem; font-size:.875rem; font-weight:500; border:2px solid; cursor:pointer; transition:all .15s;
                                {{ $importType === $val
                                    ? 'border-color: rgb(234 179 8); background:rgba(234,179,8,.1); color:rgb(234 179 8);'
                                    : 'border-color: rgba(255,255,255,.1); background:transparent; color:rgba(255,255,255,.6);' }}">
                            {{ $lbl }}
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- Download template --}}
            <div style="display:flex; align-items:center; gap:.4rem; font-size:.8rem; color:rgba(255,255,255,.4);">
                <svg style="width:1rem;height:1rem;flex-shrink:0;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m.75 12 3 3m0 0 3-3m-3 3v-6m-1.5-9H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/>
                </svg>
                Baixar template:
                <button wire:click="downloadTemplate('{{ $importType }}')"
                    style="color:rgb(234 179 8); text-decoration:underline; background:none; border:none; cursor:pointer; font-size:.8rem; font-weight:500;">
                    template_{{ $importType }}.csv
                </button>
            </div>

            {{-- Upload CSV --}}
            <div>
                <p class="fi-fo-field-wrp-label" style="margin-bottom:.4rem;">Arquivo CSV *</p>
                <input type="file" wire:model="csvFile" accept=".csv"
                    style="display:block; width:100%; font-size:.875rem; color:rgba(255,255,255,.7);
                           padding:.5rem; border-radius:.5rem; border:1px dashed rgba(255,255,255,.2);
                           background:rgba(255,255,255,.03); cursor:pointer;">
                @error('csvFile')
                    <p style="font-size:.75rem; color:#f87171; margin-top:.25rem;">{{ $message }}</p>
                @enderror
            </div>

            {{-- Upload ZIP --}}
            @if ($importType === 'products')
                <div>
                    <p class="fi-fo-field-wrp-label" style="margin-bottom:.25rem;">
                        Arquivo ZIP de imagens
                        <span style="font-weight:400; color:rgba(255,255,255,.35);">(opcional)</span>
                    </p>
                    <p style="font-size:.75rem; color:rgba(255,255,255,.35); margin-bottom:.4rem;">
                        Compacte as imagens em um .zip e referencie pelo nome do arquivo na coluna "Image Src".
                    </p>
                    <input type="file" wire:model="zipFile" accept=".zip"
                        style="display:block; width:100%; font-size:.875rem; color:rgba(255,255,255,.7);
                               padding:.5rem; border-radius:.5rem; border:1px dashed rgba(255,255,255,.2);
                               background:rgba(255,255,255,.03); cursor:pointer;">
                </div>
            @endif

            {{-- Botões --}}
            <div style="display:flex; gap:.75rem; flex-wrap:wrap;">
                <x-filament::button wire:click="loadPreview" color="gray" icon="heroicon-o-eye"
                    wire:loading.attr="disabled" wire:target="csvFile,loadPreview">
                    <span wire:loading.remove wire:target="loadPreview">Pré-visualizar</span>
                    <span wire:loading wire:target="loadPreview">Carregando...</span>
                </x-filament::button>

                @if ($showPreview)
                    <x-filament::button wire:click="import" color="success" icon="heroicon-o-arrow-up-tray"
                        wire:loading.attr="disabled" wire:target="import">
                        <span wire:loading.remove wire:target="import">Confirmar importação</span>
                        <span wire:loading wire:target="import">Processando...</span>
                    </x-filament::button>
                @endif
            </div>

            {{-- Preview --}}
            @if ($showPreview && !empty($preview['rows']))
                <div>
                    <p style="font-size:.875rem; font-weight:500; color:rgba(255,255,255,.7); margin-bottom:.5rem;">
                        Primeiras {{ count($preview['rows']) }} linhas do arquivo:
                    </p>
                    <div style="overflow-x:auto; border-radius:.5rem; border:1px solid rgba(255,255,255,.1);">
                        <table style="width:100%; font-size:.75rem; border-collapse:collapse;">
                            <thead style="background:rgba(255,255,255,.05);">
                                <tr>
                                    @foreach ($preview['headers'] as $header)
                                        <th style="padding:.5rem .75rem; text-align:left; font-weight:600; color:rgba(255,255,255,.5); white-space:nowrap; border-bottom:1px solid rgba(255,255,255,.1);">
                                            {{ $header }}
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($preview['rows'] as $i => $row)
                                    <tr style="{{ $i % 2 === 0 ? '' : 'background:rgba(255,255,255,.02);' }}">
                                        @foreach ($row as $cell)
                                            <td style="padding:.4rem .75rem; color:rgba(255,255,255,.65); white-space:nowrap; max-width:200px; overflow:hidden; text-overflow:ellipsis; border-bottom:1px solid rgba(255,255,255,.05);">
                                                {{ $cell ?: '—' }}
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

        </div>
    </x-filament::section>

    {{-- ================================================================
         EXPORTAÇÃO
    ================================================================ --}}
    <x-filament::section>
        <x-slot name="heading">Exportar CSV</x-slot>
        <x-slot name="description">Exporte seus dados no formato compatível com Shopify.</x-slot>

        <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:1rem;">

            @foreach ([
                'products'  => ['label' => 'Produtos',  'count' => $this->productsCount],
                'customers' => ['label' => 'Clientes',  'count' => $this->customersCount],
                'orders'    => ['label' => 'Pedidos',   'count' => $this->ordersCount],
            ] as $type => $info)
                <div style="border-radius:.75rem; border:1px solid rgba(255,255,255,.1); padding:1.25rem; display:flex; flex-direction:column; gap:1rem; background:rgba(255,255,255,.02);">
                    <div>
                        <p style="font-size:.7rem; text-transform:uppercase; letter-spacing:.05em; color:rgba(255,255,255,.4); margin-bottom:.25rem;">
                            {{ $info['label'] }}
                        </p>
                        <p style="font-size:1.75rem; font-weight:700; color:rgba(255,255,255,.9);">
                            {{ number_format($info['count']) }}
                        </p>
                    </div>
                    <x-filament::button wire:click="export('{{ $type }}')" color="gray" size="sm"
                        icon="heroicon-o-arrow-down-tray"
                        wire:loading.attr="disabled" wire:target="export('{{ $type }}')">
                        Exportar {{ $info['label'] }}
                    </x-filament::button>
                </div>
            @endforeach

        </div>
    </x-filament::section>

    {{-- ================================================================
         HISTÓRICO
    ================================================================ --}}
    <x-filament::section>
        <x-slot name="heading">Histórico de importações</x-slot>
        <x-slot name="description">Atualiza automaticamente a cada 5 segundos.</x-slot>

        @forelse ($this->imports as $import)
            <div style="display:flex; align-items:center; justify-content:space-between; gap:1rem; padding:.75rem 0; border-bottom:1px solid rgba(255,255,255,.06);">

                <div style="display:flex; align-items:center; gap:.75rem; min-width:0;">
                    <div style="width:.6rem; height:.6rem; border-radius:50%; flex-shrink:0;
                        background:{{ match($import->status) {
                            'pending'    => '#facc15',
                            'processing' => '#60a5fa',
                            'done'       => '#4ade80',
                            'failed'     => '#f87171',
                            default      => '#9ca3af',
                        } }};
                        {{ in_array($import->status, ['pending','processing']) ? 'animation:pulse 2s infinite;' : '' }}">
                    </div>
                    <div style="min-width:0;">
                        <p style="font-size:.875rem; font-weight:500; color:rgba(255,255,255,.85); overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                            {{ basename($import->filename) }}
                        </p>
                        <p style="font-size:.75rem; color:rgba(255,255,255,.35); margin-top:.1rem;">
                            {{ ucfirst($import->type) }} · {{ $import->created_at->format('d/m/Y H:i') }}
                        </p>
                    </div>
                </div>

                <div style="display:flex; align-items:center; gap:1rem; flex-shrink:0;">

                    @if (in_array($import->status, ['pending','processing']))
                        <div style="display:flex; align-items:center; gap:.5rem; font-size:.75rem; color:rgba(255,255,255,.4);">
                            <div style="width:6rem; height:.375rem; background:rgba(255,255,255,.1); border-radius:99px; overflow:hidden;">
                                <div style="height:100%; background:rgb(234 179 8); border-radius:99px; width:{{ $import->progress }}%; transition:width .5s;"></div>
                            </div>
                            {{ $import->progress }}%
                        </div>
                    @elseif ($import->status === 'done')
                        <span style="font-size:.75rem; color:rgba(255,255,255,.35);">
                            {{ number_format($import->processed_rows) }}/{{ number_format($import->total_rows) }} linhas
                            @if ($import->error_rows > 0)
                                · <span style="color:#f87171;">{{ $import->error_rows }} erros</span>
                            @endif
                        </span>
                    @endif

                    @if ($import->error_file)
                        <a href="{{ \Illuminate\Support\Facades\Storage::url($import->error_file) }}" target="_blank"
                           style="font-size:.75rem; color:#f87171; text-decoration:underline;">
                            Baixar erros
                        </a>
                    @endif

                    <span style="display:inline-flex; align-items:center; padding:.2rem .6rem; border-radius:99px; font-size:.7rem; font-weight:500;
                        background:{{ match($import->status) {
                            'pending'    => 'rgba(250,204,21,.15)',
                            'processing' => 'rgba(96,165,250,.15)',
                            'done'       => 'rgba(74,222,128,.15)',
                            'failed'     => 'rgba(248,113,113,.15)',
                            default      => 'rgba(156,163,175,.15)',
                        } }};
                        color:{{ match($import->status) {
                            'pending'    => '#facc15',
                            'processing' => '#60a5fa',
                            'done'       => '#4ade80',
                            'failed'     => '#f87171',
                            default      => '#9ca3af',
                        } }};">
                        {{ match($import->status) {
                            'pending'    => 'Aguardando',
                            'processing' => 'Processando',
                            'done'       => 'Concluído',
                            'failed'     => 'Falhou',
                            default      => ucfirst($import->status),
                        } }}
                    </span>

                </div>
            </div>
        @empty
            <p style="text-align:center; padding:2rem 0; font-size:.875rem; color:rgba(255,255,255,.3);">
                Nenhuma importação realizada ainda.
            </p>
        @endforelse

    </x-filament::section>

</div>
</x-filament-panels::page>