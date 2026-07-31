/* ============================================================================
   Replicantfy — Checkout 3DS (C4)
   ============================================================================
   Tela de autenticação 3DS. Funcionalidades:
     1. Click em "Iniciar autenticação" → window.open + form.submit (target=popup)
     2. Polling /checkout/status/{order} a cada 3s
     3. Estados via data-3ds-state: initial / waiting / error
     4. Detecta popup blocker e redireciona pro estado error
     5. Botão "Reabrir autenticação" no estado waiting
   ============================================================================ */

(function () {
    'use strict';

    const POLL_INTERVAL_MS = 3000;
    const POPUP_FEATURES   = 'width=600,height=700,scrollbars=yes,resizable=yes';
    const POPUP_NAME       = 'checkout3ds_popup';

    document.addEventListener('DOMContentLoaded', init);

    function init() {
        const page = document.querySelector('[data-3ds-page]');
        if (!page) return;

        initStartButtons(page);
        initPolling(page);
    }

    // ========================================================================
    // STATE MANAGEMENT
    // ========================================================================

    function setState(page, state) {
        page.setAttribute('data-3ds-state', state);
    }

    function setErrorMessage(page, message) {
        const msg = page.querySelector('[data-3ds-error-message]');
        if (msg) msg.textContent = message;
    }

    // ========================================================================
    // POPUP + FORM SUBMIT
    // ========================================================================

    function initStartButtons(page) {
        // Todos os [data-3ds-start] (CTA principal, restart, retry no error)
        page.querySelectorAll('[data-3ds-start]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                openPopupAndSubmit(page);
            });
        });
    }

    function openPopupAndSubmit(page) {
        const form = page.querySelector('[data-3ds-form]');
        if (!form) {
            setErrorMessage(page, 'Erro interno: formulário 3DS não encontrado.');
            setState(page, 'error');
            return;
        }

        // Abre popup (precisa estar dentro do click handler pra contornar popup blocker)
        const popup = window.open('about:blank', POPUP_NAME, POPUP_FEATURES);

        if (!popup || popup.closed || typeof popup.closed === 'undefined') {
            setErrorMessage(
                page,
                'A janela de autenticação foi bloqueada pelo seu navegador. ' +
                'Permita popups deste site nas configurações e tente novamente.'
            );
            setState(page, 'error');
            return;
        }

        // Submete o form (target=POPUP_NAME → cai dentro da popup)
        try {
            form.submit();
        } catch (e) {
            console.warn('3DS form submit error:', e);
            setErrorMessage(page, 'Não foi possível abrir a autenticação. Tente novamente.');
            setState(page, 'error');
            popup.close();
            return;
        }

        // UI vai pra estado "aguardando confirmação"
        setState(page, 'waiting');
    }

    // ========================================================================
    // POLLING — fonte de verdade do status do pedido
    // ========================================================================

    function initPolling(page) {
        const statusUrl = page.getAttribute('data-3ds-status-url');
        if (!statusUrl) return;

        let stopped = false;

        async function poll() {
            if (stopped) return;

            try {
                const response = await fetch(statusUrl, {
                    headers: { 'Accept': 'application/json' },
                    credentials: 'same-origin',
                });

                if (!response.ok) return; // tenta no próximo tick

                const data = await response.json();

                if (data.is_paid && data.redirect_url) {
                    // PAGO! Redireciona pra confirmação.
                    stopped = true;
                    window.location.href = data.redirect_url;
                    return;
                }

                if (data.is_failed) {
                    // Recusado pelo banco
                    stopped = true;
                    setErrorMessage(
                        page,
                        'O pagamento foi recusado pelo banco. Você pode tentar com outro cartão.'
                    );
                    setState(page, 'error');
                }

            } catch (e) {
                console.warn('3DS polling error:', e);
            }
        }

        // Primeiro tick imediato (cobre caso edge: webhook chegou enquanto carregava)
        poll();
        setInterval(poll, POLL_INTERVAL_MS);
    }
})();