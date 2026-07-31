/* ============================================================================
   Replicantfy — Checkout PIX (C1)
   ============================================================================
   Tela dedicada do PIX. Funcionalidades:
     1. Countdown da expiração (data-pix-expires-at em ISO 8601)
     2. Polling no /checkout/status/{order} a cada 3s
     3. Copy to clipboard do código PIX
     4. Estados: ativo / expirado
     5. Pausa polling quando aba fica oculta (Page Visibility API)
   ============================================================================ */

(function () {
    'use strict';

    const POLL_INTERVAL_MS = 5000;     // mesmo intervalo do checkout original
    const HIDDEN_PAUSE_MS  = 30000;    // pausa polling após 30s sem foco
    const COPY_FEEDBACK_MS = 2000;     // tempo do "Copiado!"

    document.addEventListener('DOMContentLoaded', init);

    function init() {
        const page = document.querySelector('[data-pix-page]');
        if (!page) return;

        initCountdown(page);
        initPolling(page);
        initCopy(page);
    }

    // ========================================================================
    // COUNTDOWN
    // ========================================================================

    function initCountdown(page) {
        const display = page.querySelector('[data-pix-countdown]');
        const expiresAtRaw = page.dataset.pixExpiresAt;
        if (!display || !expiresAtRaw) return;

        const expiresAt = new Date(expiresAtRaw).getTime();
        if (isNaN(expiresAt)) return;

        let intervalId = null;

        function tick() {
            const remaining = Math.max(0, Math.floor((expiresAt - Date.now()) / 1000));

            if (remaining <= 0) {
                showExpired(page);
                if (intervalId) clearInterval(intervalId);
                return;
            }

            const m = Math.floor(remaining / 60);
            const s = remaining % 60;
            display.textContent = `${m}:${s.toString().padStart(2, '0')}`;
        }

        tick();
        intervalId = setInterval(tick, 1000);
    }

    function showExpired(page) {
        const active  = page.querySelector('[data-pix-active]');
        const expired = page.querySelector('[data-pix-expired]');
        if (active)  active.setAttribute('hidden', '');
        if (expired) expired.removeAttribute('hidden');
    }

    // ========================================================================
    // POLLING
    // ========================================================================

    function initPolling(page) {
        const statusUrl = page.dataset.pixStatusUrl;
        if (!statusUrl) return;

        let pollingId = null;
        let hiddenSinceMs = null;
        let stopped = false;
        const verifyButton = page.querySelector('[data-pix-verify]');
        const verifyLabel = page.querySelector('[data-pix-verify-label]');

        async function poll(manual = false) {
            if (stopped) return;

            let feedback = 'Pagamento não identificado';

            try {
                if (manual && verifyButton) verifyButton.dataset.loading = 'true';
                if (manual && verifyLabel) verifyLabel.textContent = 'Verificando...';
                const response = await fetch(statusUrl, {
                    headers: { 'Accept': 'application/json' },
                    credentials: 'same-origin',
                });

                if (!response.ok) {
                    feedback = 'Não foi possível verificar';
                    return; // tenta de novo no próximo tick
                }

                const data = await response.json();

                if (data.is_paid && data.redirect_url) {
                    // PAGO! Redireciona pra confirmação
                    stopped = true;
                    if (pollingId) clearInterval(pollingId);
                    window.location.href = data.redirect_url;
                    return;
                }

                if (data.is_failed) {
                    // Marcado como failed pelo webhook — mostra expirado
                    stopped = true;
                    if (pollingId) clearInterval(pollingId);
                    showExpired(page);
                }

            } catch (e) {
                // Silencioso — tenta de novo no próximo tick
                console.warn('PIX polling error:', e);
                feedback = 'Não foi possível verificar';
            } finally {
                if (manual && verifyButton) verifyButton.dataset.loading = 'false';
                if (manual && verifyLabel && !stopped) {
                    verifyLabel.textContent = feedback;
                    setTimeout(function () {
                        if (!stopped) verifyLabel.textContent = 'Verificar pagamento';
                    }, COPY_FEEDBACK_MS);
                }
            }
        }

        function startPolling() {
            if (pollingId || stopped) return;
            poll(false); // primeiro tick imediato
            pollingId = setInterval(function () {
                poll(false);
            }, POLL_INTERVAL_MS);
        }

        function stopPolling() {
            if (pollingId) {
                clearInterval(pollingId);
                pollingId = null;
            }
        }

        // Page Visibility — pausa polling após 30s sem foco
        document.addEventListener('visibilitychange', function () {
            if (document.hidden) {
                hiddenSinceMs = Date.now();
            } else {
                hiddenSinceMs = null;
                if (!pollingId && !stopped) startPolling();
            }
        });

        // Checa periodicamente se ficou hidden há mais de HIDDEN_PAUSE_MS
        setInterval(function () {
            if (hiddenSinceMs && (Date.now() - hiddenSinceMs > HIDDEN_PAUSE_MS)) {
                stopPolling();
            }
        }, 5000);

        startPolling();

        if (verifyButton) {
            verifyButton.addEventListener('click', function () {
                poll(true);
            });
        }
    }

    // ========================================================================
    // COPY TO CLIPBOARD
    // ========================================================================

    function initCopy(page) {
        const btn   = page.querySelector('[data-pix-copy-btn]');
        const input = page.querySelector('[data-pix-copy-input]');
        const label = page.querySelector('[data-pix-copy-label]');
        if (!btn || !input) return;

        btn.addEventListener('click', async function () {
            const text = input.value;

            try {
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    await navigator.clipboard.writeText(text);
                } else {
                    // Fallback pra browsers velhos
                    input.select();
                    input.setSelectionRange(0, 99999);
                    document.execCommand('copy');
                    input.blur();
                }

                showCopiedFeedback(btn, label);

            } catch (e) {
                console.warn('Clipboard error', e);
                // Tenta fallback ainda
                try {
                    input.select();
                    document.execCommand('copy');
                    input.blur();
                    showCopiedFeedback(btn, label);
                } catch {
                    /* no-op */
                }
            }
        });
    }

    function showCopiedFeedback(btn, label) {
        const originalText = label ? label.textContent : 'Copiar';
        if (label) label.textContent = 'Copiado!';
        btn.classList.add('is-copied');

        setTimeout(function () {
            if (label) label.textContent = originalText;
            btn.classList.remove('is-copied');
        }, COPY_FEEDBACK_MS);
    }
})();
