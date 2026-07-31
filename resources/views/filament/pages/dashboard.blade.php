<x-filament-panels::page>

    {{-- Seletor de período --}}
    <div style="display:flex; align-items:center; gap:.75rem; flex-wrap:wrap; margin-bottom:1rem;">

        @foreach (['today' => 'Hoje', 'week' => 'Esta semana', 'month' => 'Este mês', 'custom' => 'Personalizado'] as $val => $lbl)
            <button type="button" wire:click="$set('period', '{{ $val }}')"
                style="padding:.35rem .9rem; border-radius:.5rem; font-size:.8rem; font-weight:500; border:2px solid; cursor:pointer; transition:all .15s;
                    {{ $period === $val
                        ? 'border-color:rgb(234,179,8); background:rgba(234,179,8,.1); color:rgb(234,179,8);'
                        : 'border-color:rgba(255,255,255,.1); background:transparent; color:rgba(255,255,255,.5);' }}">
                {{ $lbl }}
            </button>
        @endforeach

        @if ($period === 'custom')
            <div style="display:flex; align-items:center; gap:.5rem;">
                <input type="date" wire:model.live="dateFrom"
                    style="padding:.3rem .6rem; border-radius:.5rem; border:1px solid rgba(255,255,255,.15); background:rgba(255,255,255,.05); color:rgba(255,255,255,.8); font-size:.8rem;">
                <span style="color:rgba(255,255,255,.3); font-size:.8rem;">até</span>
                <input type="date" wire:model.live="dateTo"
                    style="padding:.3rem .6rem; border-radius:.5rem; border:1px solid rgba(255,255,255,.15); background:rgba(255,255,255,.05); color:rgba(255,255,255,.8); font-size:.8rem;">
            </div>
        @endif

    </div>

    {{-- Widgets --}}
    <x-filament-widgets::widgets
        :widgets="$this->getWidgets()"
        :columns="$this->getColumns()"
        :data="['period' => $period, 'dateFrom' => $dateFrom, 'dateTo' => $dateTo]"
    />

</x-filament-panels::page>