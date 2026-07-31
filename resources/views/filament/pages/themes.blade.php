<x-filament-panels::page>
<div style="display:flex; flex-direction:column; gap:1.5rem;">

    {{-- ================================================================
         TABS
    ================================================================ --}}
    <div style="display:flex; gap:.5rem; border-bottom:1px solid rgba(255,255,255,.1); padding-bottom:0;">
        @foreach (['themes' => 'Temas instalados', 'editor' => 'Editor visual'] as $tab => $label)
            <button type="button" wire:click="$set('activeTab', '{{ $tab }}')"
                style="padding:.5rem 1rem; font-size:.875rem; font-weight:500; cursor:pointer; border:none; background:none;
                    border-bottom:2px solid {{ $activeTab === $tab ? 'rgb(234,179,8)' : 'transparent' }};
                    color:{{ $activeTab === $tab ? 'rgb(234,179,8)' : 'rgba(255,255,255,.5)' }};
                    margin-bottom:-1px; transition:all .15s;">
                {{ $label }}
            </button>
        @endforeach
    </div>

    {{-- ================================================================
         TAB: TEMAS INSTALADOS
    ================================================================ --}}
    @if ($activeTab === 'themes')

        {{-- Lista de temas --}}
        <x-filament::section>
            <x-slot name="heading">Temas disponíveis</x-slot>

            <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(240px, 1fr)); gap:1rem;">
                @foreach ($this->themes as $theme)
                    <div style="border-radius:.75rem; border:2px solid {{ $theme['is_active'] ? 'rgb(234,179,8)' : 'rgba(255,255,255,.1)' }};
                                background:rgba(255,255,255,.02); overflow:hidden;">

                        {{-- Preview --}}
                        <div style="height:140px; background:rgba(255,255,255,.05); display:flex; align-items:center; justify-content:center; overflow:hidden;">
                            @if ($theme['preview'])
                                <img src="{{ $theme['preview'] }}" alt="{{ $theme['name'] }}"
                                    style="width:100%; height:100%; object-fit:cover;">
                            @else
                                <svg style="width:3rem; height:3rem; color:rgba(255,255,255,.15);" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9.53 16.122a3 3 0 0 0-5.78 1.128 2.25 2.25 0 0 1-2.4 2.245 4.5 4.5 0 0 0 8.4-2.245c0-.399-.078-.78-.22-1.128Zm0 0a15.998 15.998 0 0 0 3.388-1.62m-5.043-.025a15.994 15.994 0 0 1 1.622-3.395m3.42 3.42a15.995 15.995 0 0 0 4.764-4.648l3.876-5.814a1.151 1.151 0 0 0-1.597-1.597L14.146 6.32a15.996 15.996 0 0 0-4.649 4.763m3.42 3.42a6.776 6.776 0 0 0-3.42-3.42"/>
                                </svg>
                            @endif
                        </div>

                        <div style="padding:1rem;">
                            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:.5rem;">
                                <p style="font-weight:600; font-size:.9rem; color:rgba(255,255,255,.9);">
                                    {{ $theme['label'] }}
                                </p>
                                @if ($theme['is_active'])
                                    <span style="font-size:.65rem; font-weight:600; padding:.2rem .5rem; border-radius:99px; background:rgba(234,179,8,.15); color:rgb(234,179,8);">
                                        ATIVO
                                    </span>
                                @endif
                            </div>

                            @if ($theme['version'] || $theme['author'])
                                <p style="font-size:.75rem; color:rgba(255,255,255,.35); margin-bottom:.75rem;">
                                    @if ($theme['version']) v{{ $theme['version'] }} @endif
                                    @if ($theme['author']) · {{ $theme['author'] }} @endif
                                </p>
                            @endif

                            <div style="display:flex; gap:.5rem; flex-wrap:wrap;">
                                @if (!$theme['is_active'])
                                    <x-filament::button size="sm" wire:click="activate('{{ $theme['name'] }}')" color="success">
                                        Ativar
                                    </x-filament::button>
                                @endif

                                <x-filament::button size="sm" color="gray"
                                    wire:click="$set('editingTheme', '{{ $theme['name'] }}'); $set('activeTab', 'editor')">
                                    Personalizar
                                </x-filament::button>

                                @if ($theme['name'] !== 'default' && !$theme['is_active'])
                                    <x-filament::button size="sm" color="danger"
                                        wire:click="delete('{{ $theme['name'] }}')"
                                        wire:confirm="Tem certeza que deseja remover este tema?">
                                        Remover
                                    </x-filament::button>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-filament::section>

    @endif

    {{-- ================================================================
         TAB: EDITOR VISUAL
    ================================================================ --}}
    @if ($activeTab === 'editor')

        <x-filament::section>
            <x-slot name="heading">Editor visual — {{ $editingTheme }}</x-slot>
            <x-slot name="description">Personalize as configurações do tema. As alterações são salvas no arquivo settings_data.json.</x-slot>

            {{-- Selector de tema --}}
            @if (count($this->themes) > 1)
                <div style="margin-bottom:1.25rem;">
                    <label style="font-size:.8rem; color:rgba(255,255,255,.5); display:block; margin-bottom:.4rem;">Editando tema</label>
                    <select wire:model.live="editingTheme"
                        style="padding:.4rem .75rem; border-radius:.5rem; border:1px solid rgba(255,255,255,.15); background:rgba(255,255,255,.05); color:rgba(255,255,255,.8); font-size:.875rem;">
                        @foreach ($this->themes as $theme)
                            <option value="{{ $theme['name'] }}">{{ $theme['label'] }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            {{-- Grupos de configurações --}}
            @foreach ($this->schema as $group)
                <div style="margin-bottom:2rem;">
                    <h3 style="font-size:.8rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:rgba(255,255,255,.4); margin-bottom:1rem; padding-bottom:.5rem; border-bottom:1px solid rgba(255,255,255,.07);">
                        {{ $group['name'] }}
                    </h3>

                    <div style="display:flex; flex-direction:column; gap:.875rem;">
                        @foreach ($group['settings'] as $setting)
                            <div>
                                <label style="display:block; font-size:.8rem; font-weight:500; color:rgba(255,255,255,.6); margin-bottom:.35rem;">
                                    {{ $setting['label'] }}
                                </label>

                                @if ($setting['type'] === 'text')
                                    <input type="text"
                                        wire:model.lazy="formData.{{ $setting['id'] }}"
                                        placeholder="{{ $setting['default'] ?? '' }}"
                                        style="width:100%; padding:.5rem .75rem; border-radius:.5rem; border:1px solid rgba(255,255,255,.12); background:rgba(255,255,255,.05); color:rgba(255,255,255,.85); font-size:.875rem;">

                                @elseif ($setting['type'] === 'textarea')
                                    <textarea
                                        wire:model.lazy="formData.{{ $setting['id'] }}"
                                        rows="3"
                                        placeholder="{{ $setting['default'] ?? '' }}"
                                        style="width:100%; padding:.5rem .75rem; border-radius:.5rem; border:1px solid rgba(255,255,255,.12); background:rgba(255,255,255,.05); color:rgba(255,255,255,.85); font-size:.875rem; resize:vertical;"></textarea>

                                @elseif ($setting['type'] === 'color')
                                    <div style="display:flex; align-items:center; gap:.75rem;">
                                        <input type="color"
                                            wire:model.lazy="formData.{{ $setting['id'] }}"
                                            value="{{ $formData[$setting['id']] ?? $setting['default'] ?? '#000000' }}"
                                            style="width:2.5rem; height:2.5rem; border-radius:.4rem; border:1px solid rgba(255,255,255,.15); cursor:pointer; padding:2px; background:transparent;">
                                        <input type="text"
                                            wire:model.lazy="formData.{{ $setting['id'] }}"
                                            value="{{ $formData[$setting['id']] ?? $setting['default'] ?? '' }}"
                                            placeholder="#000000"
                                            style="width:8rem; padding:.4rem .6rem; border-radius:.5rem; border:1px solid rgba(255,255,255,.12); background:rgba(255,255,255,.05); color:rgba(255,255,255,.85); font-size:.8rem; font-family:monospace;">
                                    </div>

                                @elseif ($setting['type'] === 'image_picker')
                                    <div style="display:flex; align-items:center; gap:.75rem; flex-wrap:wrap;">
                                        @if (!empty($formData[$setting['id']]))
                                            <img src="{{ asset('storage/' . $formData[$setting['id']]) }}"
                                                style="height:3rem; width:auto; border-radius:.4rem; border:1px solid rgba(255,255,255,.1); object-fit:contain; background:rgba(255,255,255,.05);"
                                                onerror="this.style.display='none'">
                                        @endif
                                        <input type="text"
                                            wire:model.lazy="formData.{{ $setting['id'] }}"
                                            placeholder="Caminho da imagem (ex: logos/logo.png)"
                                            style="flex:1; min-width:200px; padding:.5rem .75rem; border-radius:.5rem; border:1px solid rgba(255,255,255,.12); background:rgba(255,255,255,.05); color:rgba(255,255,255,.85); font-size:.8rem;">
                                    </div>

                                @elseif ($setting['type'] === 'checkbox')
                                    <label style="display:flex; align-items:center; gap:.5rem; cursor:pointer;">
                                        <input type="checkbox"
                                            wire:model.lazy="formData.{{ $setting['id'] }}"
                                            style="width:1rem; height:1rem; accent-color:rgb(234,179,8);">
                                        <span style="font-size:.875rem; color:rgba(255,255,255,.6);">{{ $setting['info'] ?? 'Ativar' }}</span>
                                    </label>

                                @elseif ($setting['type'] === 'select')
                                    <select wire:model.lazy="formData.{{ $setting['id'] }}"
                                        style="width:100%; padding:.5rem .75rem; border-radius:.5rem; border:1px solid rgba(255,255,255,.12); background:rgba(255,255,255,.05); color:rgba(255,255,255,.85); font-size:.875rem;">
                                        @foreach ($setting['options'] ?? [] as $opt)
                                            <option value="{{ $opt['value'] }}"
                                                {{ ($formData[$setting['id']] ?? '') === $opt['value'] ? 'selected' : '' }}>
                                                {{ $opt['label'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                @endif

                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach

            {{-- Salvar --}}
            <div style="padding-top:1rem; border-top:1px solid rgba(255,255,255,.07);">
                <x-filament::button wire:click="saveSettings" color="success" icon="heroicon-o-check">
                    Salvar configurações
                </x-filament::button>
            </div>

        </x-filament::section>

    @endif

</div>
</x-filament-panels::page>