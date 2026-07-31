<x-filament-panels::page>
<div style="display:flex; flex-direction:column; gap:1.5rem;">

    {{-- =================================================================== --}}
    {{-- SEÇÃO 1: Métodos de pagamento e parcelamento                       --}}
    {{-- =================================================================== --}}
    <x-filament::section>
        <x-slot name="heading">Métodos de pagamento e parcelamento</x-slot>
        <x-slot name="description">
            Habilita formas de pagamento, configura parcelas e desconto do PIX.
        </x-slot>

        <div style="display:flex; flex-direction:column; gap:1rem;">

            {{-- Toggle: Cartão de crédito --}}
            <label style="display:flex; gap:.75rem; align-items:center; cursor:pointer;
                          padding:.85rem 1rem; border-radius:.5rem;
                          background:rgba(255,255,255,.03);
                          border:1px solid rgba(255,255,255,.08);">
                <input type="checkbox"
                       wire:model="data.credit_card_enabled"
                       style="width:1.1rem; height:1.1rem; accent-color:#4ade80; cursor:pointer;">
                <div style="flex:1;">
                    <div style="color:rgba(255,255,255,.9); font-size:.9rem; font-weight:500;">
                        Cartão de crédito
                    </div>
                    <div style="color:rgba(255,255,255,.4); font-size:.75rem; margin-top:.15rem;">
                        Aceitar pagamentos com cartão (com 3DS quando exigido pelo banco).
                    </div>
                </div>
            </label>

            {{-- Toggle: PIX --}}
            <label style="display:flex; gap:.75rem; align-items:center; cursor:pointer;
                          padding:.85rem 1rem; border-radius:.5rem;
                          background:rgba(255,255,255,.03);
                          border:1px solid rgba(255,255,255,.08);">
                <input type="checkbox"
                       wire:model="data.pix_enabled"
                       style="width:1.1rem; height:1.1rem; accent-color:#4ade80; cursor:pointer;">
                <div style="flex:1;">
                    <div style="color:rgba(255,255,255,.9); font-size:.9rem; font-weight:500;">
                        PIX
                    </div>
                    <div style="color:rgba(255,255,255,.4); font-size:.75rem; margin-top:.15rem;">
                        Aceitar pagamentos via PIX com QR Code e copia-cola.
                    </div>
                </div>
            </label>

            {{-- Grid 2 colunas: parcelas --}}
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-top:.5rem;">
                <div>
                    <label style="display:block; font-size:.8rem; font-weight:500;
                                  color:rgba(255,255,255,.6); margin-bottom:.35rem;">
                        Máximo de parcelas
                    </label>
                    <input type="number" min="1" max="24"
                           wire:model="data.installments_max"
                           style="width:100%; padding:.5rem .75rem; border-radius:.5rem;
                                  border:1px solid rgba(255,255,255,.12);
                                  background:rgba(255,255,255,.05);
                                  color:rgba(255,255,255,.85);
                                  font-size:.875rem;">
                </div>

                <div>
                    <label style="display:block; font-size:.8rem; font-weight:500;
                                  color:rgba(255,255,255,.6); margin-bottom:.35rem;">
                        Parcelas sem juros (até)
                    </label>
                    <input type="number" min="1" max="24"
                           wire:model="data.installments_no_interest_max"
                           style="width:100%; padding:.5rem .75rem; border-radius:.5rem;
                                  border:1px solid rgba(255,255,255,.12);
                                  background:rgba(255,255,255,.05);
                                  color:rgba(255,255,255,.85);
                                  font-size:.875rem;">
                </div>
            </div>

            {{-- Grid 2 colunas: PIX --}}
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                <div>
                    <label style="display:block; font-size:.8rem; font-weight:500;
                                  color:rgba(255,255,255,.6); margin-bottom:.35rem;">
                        Desconto PIX (%)
                    </label>
                    <input type="number" min="0" max="100"
                           wire:model="data.pix_discount_percent"
                           style="width:100%; padding:.5rem .75rem; border-radius:.5rem;
                                  border:1px solid rgba(255,255,255,.12);
                                  background:rgba(255,255,255,.05);
                                  color:rgba(255,255,255,.85);
                                  font-size:.875rem;">
                </div>

                <div>
                    <label style="display:block; font-size:.8rem; font-weight:500;
                                  color:rgba(255,255,255,.6); margin-bottom:.35rem;">
                        PIX expira em (minutos)
                    </label>
                    <input type="number" min="5" max="1440"
                           wire:model="data.pix_expires_minutes"
                           style="width:100%; padding:.5rem .75rem; border-radius:.5rem;
                                  border:1px solid rgba(255,255,255,.12);
                                  background:rgba(255,255,255,.05);
                                  color:rgba(255,255,255,.85);
                                  font-size:.875rem;">
                </div>
            </div>

        </div>
    </x-filament::section>

    {{-- =================================================================== --}}
    {{-- SEÇÃO 2: Urgência                                                  --}}
    {{-- =================================================================== --}}
    <x-filament::section>
        <x-slot name="heading">Urgência</x-slot>
        <x-slot name="description">
            Mensagem e timer no topo do checkout para reforçar a conversão.
        </x-slot>

        <div style="display:flex; flex-direction:column; gap:1rem;">

            {{-- Toggle: timer ativo --}}
            <label style="display:flex; gap:.75rem; align-items:center; cursor:pointer;
                          padding:.85rem 1rem; border-radius:.5rem;
                          background:rgba(255,255,255,.03);
                          border:1px solid rgba(255,255,255,.08);">
                <input type="checkbox"
                       wire:model="data.urgency_timer_enabled"
                       style="width:1.1rem; height:1.1rem; accent-color:#4ade80; cursor:pointer;">
                <div style="flex:1;">
                    <div style="color:rgba(255,255,255,.9); font-size:.9rem; font-weight:500;">
                        Mostrar timer de urgência
                    </div>
                    <div style="color:rgba(255,255,255,.4); font-size:.75rem; margin-top:.15rem;">
                        Exibe contagem regressiva no topo do checkout.
                    </div>
                </div>
            </label>

            {{-- Tempo do timer --}}
            <div>
                <label style="display:block; font-size:.8rem; font-weight:500;
                              color:rgba(255,255,255,.6); margin-bottom:.35rem;">
                    Duração do timer (minutos)
                </label>
                <input type="number" min="1" max="60"
                       wire:model="data.urgency_timer_minutes"
                       style="width:100%; padding:.5rem .75rem; border-radius:.5rem;
                              border:1px solid rgba(255,255,255,.12);
                              background:rgba(255,255,255,.05);
                              color:rgba(255,255,255,.85);
                              font-size:.875rem;">
            </div>

            {{-- Mensagem de urgência --}}
            <div>
                <label style="display:block; font-size:.8rem; font-weight:500;
                              color:rgba(255,255,255,.6); margin-bottom:.35rem;">
                    Mensagem de urgência
                </label>
                <input type="text" maxlength="200"
                       wire:model="data.urgency_message"
                       placeholder="Ex: Despachamos seu pedido ainda hoje!"
                       style="width:100%; padding:.5rem .75rem; border-radius:.5rem;
                              border:1px solid rgba(255,255,255,.12);
                              background:rgba(255,255,255,.05);
                              color:rgba(255,255,255,.85);
                              font-size:.875rem;">
            </div>

        </div>
    </x-filament::section>

    {{-- =================================================================== --}}
    {{-- SEÇÃO 3: Downsell                                                   --}}
    {{-- =================================================================== --}}
    <x-filament::section>
        <x-slot name="heading">Downsell — Cartão recusado</x-slot>
        <x-slot name="description">
            Quando um cartão é recusado, oferece automaticamente PIX com desconto extra para tentar recuperar a venda.
        </x-slot>

        <div style="display:flex; flex-direction:column; gap:1rem;">

            {{-- Toggle: downsell ativo --}}
            <label style="display:flex; gap:.75rem; align-items:center; cursor:pointer;
                          padding:.85rem 1rem; border-radius:.5rem;
                          background:rgba(255,255,255,.03);
                          border:1px solid rgba(255,255,255,.08);">
                <input type="checkbox"
                       wire:model="data.downsell_enabled"
                       style="width:1.1rem; height:1.1rem; accent-color:#4ade80; cursor:pointer;">
                <div style="flex:1;">
                    <div style="color:rgba(255,255,255,.9); font-size:.9rem; font-weight:500;">
                        Ativar downsell em cartão recusado
                    </div>
                    <div style="color:rgba(255,255,255,.4); font-size:.75rem; margin-top:.15rem;">
                        Mostra modal oferecendo PIX com desconto após recusa de cartão.
                    </div>
                </div>
            </label>

            {{-- Desconto downsell --}}
            <div>
                <label style="display:block; font-size:.8rem; font-weight:500;
                              color:rgba(255,255,255,.6); margin-bottom:.35rem;">
                    Desconto adicional do PIX no downsell (%)
                </label>
                <input type="number" min="0" max="100"
                       wire:model="data.downsell_pix_discount_percent"
                       style="width:100%; padding:.5rem .75rem; border-radius:.5rem;
                              border:1px solid rgba(255,255,255,.12);
                              background:rgba(255,255,255,.05);
                              color:rgba(255,255,255,.85);
                              font-size:.875rem;">
            </div>

            {{-- Título do modal --}}
            <div>
                <label style="display:block; font-size:.8rem; font-weight:500;
                              color:rgba(255,255,255,.6); margin-bottom:.35rem;">
                    Título do modal
                </label>
                <input type="text" maxlength="200"
                       wire:model="data.downsell_title"
                       placeholder="Ex: Seu cartão de crédito foi recusado."
                       style="width:100%; padding:.5rem .75rem; border-radius:.5rem;
                              border:1px solid rgba(255,255,255,.12);
                              background:rgba(255,255,255,.05);
                              color:rgba(255,255,255,.85);
                              font-size:.875rem;">
            </div>

            {{-- Subtítulo do modal --}}
            <div>
                <label style="display:block; font-size:.8rem; font-weight:500;
                              color:rgba(255,255,255,.6); margin-bottom:.35rem;">
                    Subtítulo do modal
                </label>
                <textarea rows="3"
                          wire:model="data.downsell_subtitle"
                          placeholder="Mensagem persuasiva oferecendo o PIX como alternativa..."
                          style="width:100%; padding:.5rem .75rem; border-radius:.5rem;
                                 border:1px solid rgba(255,255,255,.12);
                                 background:rgba(255,255,255,.05);
                                 color:rgba(255,255,255,.85);
                                 font-size:.875rem; resize:vertical; min-height:80px;
                                 font-family:inherit;"></textarea>
            </div>

        </div>
    </x-filament::section>

    {{-- =================================================================== --}}
    {{-- Botão Salvar global                                                  --}}
    {{-- =================================================================== --}}
    <div style="display:flex; justify-content:flex-end; padding-top:.5rem;">
        <x-filament::button
            wire:click="save"
            icon="heroicon-o-check"
            color="success">
            Salvar configurações
        </x-filament::button>
    </div>

</div>
</x-filament-panels::page>