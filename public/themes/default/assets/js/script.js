/**
 * Replicantfy — Cart & UI v2
 */

const Cart = (function () {

    function getCsrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.content : '';
    }

    async function request(method, url, body) {
        const opts = {
            method,
            headers: {
                'Content-Type': 'application/json',
                'Accept':       'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
        };
        if (body !== undefined) opts.body = JSON.stringify(body);
        const res = await fetch(url, opts);
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        return res.json();
    }

    /**
     * Entrega ao runtime apenas a resposta comercial do carrinho.
     * O próprio runtime aplica uma whitelist antes de enviar aos provedores.
     * Se o asset de tracking ainda não carregou, a mutação fica na fila.
     */
    function trackCartMutation(response, mutation) {
        if (window.ReplicantTracking?.trackCartMutation) {
            window.ReplicantTracking.trackCartMutation(response, mutation);
            return;
        }

        window.REPLICANTFY_TRACKING_QUEUE = window.REPLICANTFY_TRACKING_QUEUE || [];
        window.REPLICANTFY_TRACKING_QUEUE.push({
            type: 'cart_mutation',
            response,
            mutation,
        });
    }

    function currentCartQuantity(key) {
        const item = Array.from(document.querySelectorAll('[data-key]'))
            .find(element => element.dataset.key === String(key));
        if (!item) return null;

        const display = item.querySelector('.quantity-control__display');
        const quantity = Number(display?.textContent);
        return Number.isFinite(quantity) ? quantity : null;
    }

    // -------------------------------------------------------------------------
    // API pública
    // -------------------------------------------------------------------------

    async function add(params) {
        try {
            const data = await request('POST', window.CART_ROUTES.add, params);
            trackCartMutation(data, {
                product_id: params.product_id,
                variant_id: params.variant_id ?? null,
                quantity: Number(params.quantity ?? 1),
            });
            updateCount(data.count ?? 0);
            if (typeof window.onCartUpdate === 'function') window.onCartUpdate(data);
            showToast('Produto adicionado ao carrinho!', 'success');
            return data;
        } catch { showToast('Erro ao adicionar produto.', 'error'); }
    }

    async function updateItem(key, quantity) {
        const previousQuantity = currentCartQuantity(key);

        try {
            const data = await request('POST', window.CART_ROUTES.update, {
                updates: { [key]: quantity }
            });

            const serverDelta = Number(data.tracking?.delta);
            const delta = Number.isFinite(serverDelta)
                ? serverDelta
                : (previousQuantity === null ? 0 : Number(quantity) - previousQuantity);
            if (delta > 0) {
                trackCartMutation(data, { key, quantity: delta, delta });
            }

            updateCount(data.count ?? 0);
            if (typeof window.onCartUpdate === 'function') window.onCartUpdate(data);
            return data;
        } catch { showToast('Erro ao atualizar carrinho.', 'error'); }
    }

    async function removeItem(key) {
        try {
            const url  = window.CART_ROUTES.remove + '/' + encodeURIComponent(key) + '.json';
            const data = await request('DELETE', url);
            updateCount(data.count ?? 0);
            if (typeof window.onCartUpdate === 'function') window.onCartUpdate(data);
            showToast('Item removido.', 'success');
            return data;
        } catch { showToast('Erro ao remover item.', 'error'); }
    }

    async function applyCoupon() {
        const input = document.getElementById('coupon-code');
        const msgEl = document.getElementById('coupon-message');
        if (!input || !input.value.trim()) return;

        try {
            const data = await request('POST', window.CART_ROUTES.couponApply, {
                code: input.value.trim()
            });
            if (msgEl) {
                msgEl.textContent = data.message ?? '';
                msgEl.className   = 'coupon-message ' + (data.success ? 'success' : 'error');
            }
            if (data.success) { updateCount(data.count ?? 0); updateSummary(data); }
        } catch {
            if (msgEl) { msgEl.textContent = 'Erro ao aplicar cupom.'; msgEl.className = 'coupon-message error'; }
        }
    }

    async function removeCoupon() {
        try {
            const data = await request('DELETE', window.CART_ROUTES.couponRemove);
            updateCount(data.count ?? 0);
            updateSummary(data);
            return data;
        } catch { showToast('Erro ao remover cupom.', 'error'); }
    }

    async function fetchCart() { return request('GET', window.CART_ROUTES.show); }

    // -------------------------------------------------------------------------
    // UI helpers
    // -------------------------------------------------------------------------

    function updateCount(count) {
        const el = document.getElementById('cart-count');
        if (!el) return;
        el.textContent = count;
        el.style.transform = 'scale(1.5)';
        setTimeout(() => { el.style.transform = ''; }, 220);
    }

    function updateSummary(data) {
        const fmt = v => 'R$ ' + parseFloat(v ?? 0).toFixed(2).replace('.', ',');
        const set = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
        set('summary-subtotal', fmt(data.subtotal));
        set('summary-total',    fmt(data.total));
        if ((data.discount ?? 0) > 0) set('summary-discount', '− ' + fmt(data.discount));
    }

    function showToast(message, type = '') {
        const toast = document.getElementById('toast');
        if (!toast) return;
        toast.textContent = message;
        toast.className   = 'toast' + (type ? ` toast--${type}` : '');
        requestAnimationFrame(() => requestAnimationFrame(() => toast.classList.add('show')));
        clearTimeout(toast._timer);
        toast._timer = setTimeout(() => toast.classList.remove('show'), 3200);
    }

    // -------------------------------------------------------------------------
    // Announcement bar
    // -------------------------------------------------------------------------

    function initAnnouncement() {
        const bar    = document.getElementById('announcement-bar');
        const close  = document.getElementById('announcement-close');
        const header = document.getElementById('site-header');
        const main   = document.getElementById('main-content');

        if (!bar || !close) return;

        // Já foi dispensado nesta sessão?
        if (sessionStorage.getItem('announcement_dismissed')) {
            bar.classList.add('hidden');
            header?.classList.add('no-announcement');
            main?.classList.add('site-main--no-announcement');
        }

        close.addEventListener('click', function () {
            bar.classList.add('hidden');
            header?.classList.add('no-announcement');
            main?.classList.add('site-main--no-announcement');
            sessionStorage.setItem('announcement_dismissed', '1');
        });
    }

    // -------------------------------------------------------------------------
    // Mobile menu
    // -------------------------------------------------------------------------

    function initMobileMenu() {
        const btn = document.getElementById('mobile-menu-btn');
        const nav = document.getElementById('mobile-nav');
        if (!btn || !nav) return;

        btn.addEventListener('click', function () {
            const open = nav.classList.toggle('open');
            btn.setAttribute('aria-expanded', open);
            nav.setAttribute('aria-hidden', !open);
        });
    }

    // -------------------------------------------------------------------------
    // Init
    // -------------------------------------------------------------------------

    function init() {
        if (window.CART_ROUTES?.show) {
            fetchCart().then(data => {
                if (data?.count !== undefined) updateCount(data.count);
            }).catch(() => {});
        }

        document.addEventListener('click', function (e) {
            const btn = e.target.closest('.btn-add-cart');
            if (!btn) return;
            const productId = parseInt(btn.dataset.productId, 10);
            if (!productId) return;
            btn.disabled = true;
            const orig = btn.innerHTML;
            btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="animation:spin .8s linear infinite"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h5M20 20v-5h-5M4 9a9 9 0 0115-6.7M20 15a9 9 0 01-15 6.7"/></svg>';
            add({ product_id: productId, quantity: 1 }).finally(() => {
                btn.disabled = false;
                btn.innerHTML = orig;
            });
        });

        initAnnouncement();
        initMobileMenu();
    }

    document.addEventListener('DOMContentLoaded', init);

    return { add, updateItem, removeItem, applyCoupon, removeCoupon, fetchCart, showToast };

})();

window.Cart = Cart;
