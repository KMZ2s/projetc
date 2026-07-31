/* ============================================================================
   Replicantfy — Checkout JS (Sessão B · Passo B4)
   ============================================================================
   Vanilla JS, zero dependências.
   Estrutura modular dentro de IIFE.

   Módulos:
     1. Masks            — telefone, CPF/CNPJ, CEP, cartão, mês, ano, CVV
     2. Validation       — Luhn (cartão) + Mod-11 (CPF) client-side
     3. ViaCEP           — autocompletar endereço pelo CEP
     4. Card 3D          — espelhamento + flip ao focar CVV + bandeira
     5. Payment Tabs     — sync radio cartão/PIX com bodies + summary
     6. Steps            — validar → fechar → abrir próximo / editar
     7. Device Data      — coleta info do navegador pra 3DS (hidden inputs)
     8. Submit           — validação final + loading state
     9. Urgency Timer    — countdown da barra de urgência

   Inicialização: DOMContentLoaded
   ============================================================================ */

(function () {
    'use strict';

    // ========================================================================
    // CONSTANTS
    // ========================================================================

    function getMeta(name, fallback) {
        const el = document.querySelector(`meta[name="${name}"]`);
        return el ? el.getAttribute('content') : fallback;
    }

    const META = {
        installmentsMax:           parseInt(getMeta('checkout-installments-max', '12'), 10),
        installmentsNoInterestMax: parseInt(getMeta('checkout-installments-no-interest-max', '12'), 10),
        pixDiscount:               parseFloat(getMeta('checkout-pix-discount', '0')),
        pixEnabled:                getMeta('checkout-pix-enabled',  '1') === '1',
        cardEnabled:               getMeta('checkout-card-enabled', '1') === '1',
    };

    const VIACEP_URL = 'https://viacep.com.br/ws/{cep}/json/';

    // Patterns de detecção de bandeira (testados contra dígitos puros, sem espaços)
    const CARD_BRANDS = [
        { name: 'visa',       pattern: /^4/,                                              src: '/checkout-assets/img/card-flags/visa.svg' },
        { name: 'mastercard', pattern: /^(5[1-5]|2[2-7])/,                                src: '/checkout-assets/img/card-flags/mastercard.svg' },
        { name: 'amex',       pattern: /^3[47]/,                                          src: '/checkout-assets/img/card-flags/AMEX.svg' },
        { name: 'elo',        pattern: /^(40117[8-9]|431274|438935|451416|457393|4576|504175|627780|636297|636368|65)/, src: '/checkout-assets/img/card-flags/elo.svg' },
        { name: 'hipercard',  pattern: /^(606282|3841)/,                                  src: '/checkout-assets/img/card-flags/hipercard.svg' },
    ];

    // ========================================================================
    // UTILS
    // ========================================================================

    function debounce(fn, ms) {
        let t;
        return function (...args) {
            clearTimeout(t);
            t = setTimeout(() => fn.apply(this, args), ms);
        };
    }

    function onlyDigits(str) {
        return (str || '').toString().replace(/\D/g, '');
    }

    /** Parse "1.234,56" → 1234.56 */
    function parseBR(str) {
        return parseFloat((str || '').toString().replace(/\./g, '').replace(',', '.')) || 0;
    }

    /** Format 1234.56 → "1.234,56" */
    function moneyBR(value) {
        return value.toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
    }

    function trackCheckoutCartMutation(response, mutation) {
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

    function trackCheckoutPayment(form) {
        if (window.ReplicantTracking?.trackCheckoutPayment) {
            window.ReplicantTracking.trackCheckoutPayment(form);
            return;
        }

        // O runtime encontra o formulário quando drenar a fila. Nenhum valor
        // de identificação, endereço ou cartão é copiado para a fila.
        window.REPLICANTFY_TRACKING_QUEUE = window.REPLICANTFY_TRACKING_QUEUE || [];
        window.REPLICANTFY_TRACKING_QUEUE.push({ type: 'checkout_payment' });
    }

    // ========================================================================
    // MÓDULO 1 — MASKS
    // ========================================================================

    const masks = {
        phone(value) {
            const d = onlyDigits(value).slice(0, 11);
            if (d.length === 0) return '';
            if (d.length <= 2)  return `(${d}`;
            if (d.length <= 6)  return `(${d.slice(0, 2)}) ${d.slice(2)}`;
            if (d.length <= 10) return `(${d.slice(0, 2)}) ${d.slice(2, 6)}-${d.slice(6)}`;
            return `(${d.slice(0, 2)}) ${d.slice(2, 7)}-${d.slice(7)}`;
        },

        cpf_cnpj(value) {
            const d = onlyDigits(value);
            if (d.length <= 11) {
                // CPF: 000.000.000-00
                const v = d.slice(0, 11);
                if (v.length <= 3)  return v;
                if (v.length <= 6)  return `${v.slice(0, 3)}.${v.slice(3)}`;
                if (v.length <= 9)  return `${v.slice(0, 3)}.${v.slice(3, 6)}.${v.slice(6)}`;
                return `${v.slice(0, 3)}.${v.slice(3, 6)}.${v.slice(6, 9)}-${v.slice(9)}`;
            }
            // CNPJ: 00.000.000/0000-00
            const v = d.slice(0, 14);
            return `${v.slice(0, 2)}.${v.slice(2, 5)}.${v.slice(5, 8)}/${v.slice(8, 12)}${v.length > 12 ? '-' + v.slice(12) : ''}`;
        },

        zip(value) {
            const d = onlyDigits(value).slice(0, 8);
            return d.length <= 5 ? d : `${d.slice(0, 5)}-${d.slice(5)}`;
        },

        card_number(value) {
            const d = onlyDigits(value);
            const isAmex = /^3[47]/.test(d);

            if (isAmex) {
                const v = d.slice(0, 15);
                return v.replace(/^(\d{0,4})(\d{0,6})(\d{0,5}).*/, function (_, a, b, c) {
                    return [a, b, c].filter(Boolean).join(' ');
                });
            }

            const v = d.slice(0, 19);
            return (v.match(/.{1,4}/g) || []).join(' ');
        },

        month(value) { return onlyDigits(value).slice(0, 2); },
        year(value)  { return onlyDigits(value).slice(0, 4); },
        cvv(value)   { return onlyDigits(value).slice(0, 4); },
    };

    function applyMaskToInput(input) {
        const maskName = input.dataset.mask;
        if (!maskName || !masks[maskName]) return;

        const mask = masks[maskName];

        // Aplica on-load (caso vier valor pré-preenchido do DB)
        if (input.value) input.value = mask(input.value);

        input.addEventListener('input', function () {
            const cursorAtEnd = input.selectionStart === input.value.length;
            input.value = mask(input.value);
            if (cursorAtEnd) {
                input.setSelectionRange(input.value.length, input.value.length);
            }
        });
    }

    function initMasks() {
        document.querySelectorAll('[data-mask]').forEach(applyMaskToInput);
    }

    function initCountrySelect() {
        const select = document.querySelector('[data-country-select]');
        const flag = document.querySelector('[data-country-flag]');
        const dial = document.querySelector('[data-country-dial]');
        if (!select || !flag || !dial) return;

        function syncCountry() {
            const option = select.options[select.selectedIndex];
            if (!option) return;

            const code = (option.dataset.countryFlag || 'br').toLowerCase();
            const name = option.dataset.countryName || option.textContent;
            dial.textContent = option.dataset.countryDial || '+55';
            flag.src = code === 'br'
                ? '/checkout-assets/img/br.svg'
                : `https://flagcdn.com/w40/${code}.png`;
            flag.alt = name;
        }

        select.addEventListener('change', syncCountry);
        syncCountry();
    }

    // ========================================================================
    // MÓDULO 2 — VALIDATION (Luhn + Mod-11)
    // ========================================================================

    function validateLuhn(digits) {
        if (digits.length < 13 || digits.length > 19) return false;
        let sum = 0;
        for (let i = 0; i < digits.length; i++) {
            let d = parseInt(digits[digits.length - 1 - i], 10);
            if (i % 2 === 1) {
                d *= 2;
                if (d > 9) d -= 9;
            }
            sum += d;
        }
        return sum % 10 === 0;
    }

    function validateCpf(cpf) {
        if (!/^\d{11}$/.test(cpf))   return false;
        if (/^(\d)\1+$/.test(cpf))   return false;

        let sum = 0;
        for (let i = 0; i < 9; i++) sum += parseInt(cpf[i], 10) * (10 - i);
        let d1 = (sum * 10) % 11;
        if (d1 >= 10) d1 = 0;
        if (parseInt(cpf[9], 10) !== d1) return false;

        sum = 0;
        for (let i = 0; i < 10; i++) sum += parseInt(cpf[i], 10) * (11 - i);
        let d2 = (sum * 10) % 11;
        if (d2 >= 10) d2 = 0;
        return parseInt(cpf[10], 10) === d2;
    }

    function validateCnpj(cnpj) {
        if (!/^\d{14}$/.test(cnpj)) return false;
        if (/^(\d)\1+$/.test(cnpj)) return false;

        const w1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        const w2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

        let sum = 0;
        for (let i = 0; i < 12; i++) sum += parseInt(cnpj[i], 10) * w1[i];
        let r = sum % 11;
        const d1 = r < 2 ? 0 : 11 - r;
        if (parseInt(cnpj[12], 10) !== d1) return false;

        sum = 0;
        for (let i = 0; i < 13; i++) sum += parseInt(cnpj[i], 10) * w2[i];
        r = sum % 11;
        const d2 = r < 2 ? 0 : 11 - r;
        return parseInt(cnpj[13], 10) === d2;
    }

    function validateCpfCnpj(value) {
        const d = onlyDigits(value);
        if (d.length === 11) return validateCpf(d);
        if (d.length === 14) return validateCnpj(d);
        return false;
    }

    // ========================================================================
    // MÓDULO 3 — VIACEP
    // ========================================================================

    async function fetchViaCep(zip) {
        const cep = onlyDigits(zip);
        if (cep.length !== 8) return null;
        try {
            const response = await fetch(VIACEP_URL.replace('{cep}', cep));
            if (!response.ok) return null;
            const data = await response.json();
            if (data.erro) return null;
            return data;
        } catch (e) {
            console.warn('ViaCEP fetch failed', e);
            return null;
        }
    }

    function initViaCep() {
        const source = document.querySelector('[data-viacep-source]');
        if (!source) return;

        const wrap = source.closest('.checkout-field__input-wrap');
        const addressFields = document.querySelector('[data-address-fields]');
        const hint = document.querySelector('[data-cep-hint]');

        const onChange = debounce(async function () {
            const cep = onlyDigits(source.value);
            if (cep.length !== 8) {
                if (addressFields) addressFields.setAttribute('hidden', '');
                if (hint) hint.removeAttribute('hidden');
                return;
            }

            if (addressFields) addressFields.removeAttribute('hidden');
            if (hint) hint.setAttribute('hidden', '');

            if (wrap) wrap.dataset.loading = 'true';
            const data = await fetchViaCep(cep);
            if (wrap) wrap.dataset.loading = 'false';

            if (!data) {
                if (hint) hint.removeAttribute('hidden');
                showClientError(source, 'CEP não encontrado');
                return;
            }

            source.closest('.checkout-field')
                ?.querySelectorAll('.checkout-field__error--client')
                .forEach(error => error.remove());
            source.classList.remove('checkout-field__input--invalid');

            const fields = {
                street:       data.logradouro,
                neighborhood: data.bairro,
                city:         data.localidade,
                state:        data.uf,
            };

            Object.entries(fields).forEach(function ([key, value]) {
                if (!value) return;
                const target = document.querySelector(`[data-viacep-target="${key}"]`);
                if (target) {
                    target.value = value;
                    // Dispara input pra acionar listeners (summary update etc.)
                    target.dispatchEvent(new Event('input', { bubbles: true }));
                }
            });
        }, 300);

        source.addEventListener('input', onChange);
    }

    // ========================================================================
    // MÓDULO 4 — CARD 3D (mirror + flip + brand)
    // ========================================================================

    function initCard() {
        const visual = document.querySelector('[data-card-visual]');
        if (!visual) return;

        const inputs = {
            number: document.querySelector('[data-card-mirror="number"]'),
            holder: document.querySelector('[data-card-mirror="holder"]'),
            month:  document.querySelector('[data-card-mirror="month"]'),
            year:   document.querySelector('[data-card-mirror="year"]'),
            cvv:    document.querySelector('[data-card-mirror="cvv"]'),
        };

        const displays = {
            number: visual.querySelector('[data-card-number-display]'),
            holder: visual.querySelector('[data-card-holder-display]'),
            expiry: visual.querySelector('[data-card-expiry-display]'),
            cvv:    visual.querySelector('[data-card-cvv-display]'),
            brand:  visual.querySelector('[data-card-brand]'),
        };

        function updateBrand(digits) {
            if (!displays.brand) return;

            const detected = digits.length >= 4
                ? CARD_BRANDS.find(b => b.pattern.test(digits))
                : null;

            if (detected) {
                displays.brand.innerHTML = `<img src="${detected.src}" alt="${detected.name}">`;
                visual.dataset.brand = detected.name;
            } else {
                displays.brand.innerHTML = '<span class="checkout-card-visual__brand-placeholder">CARD</span>';
                visual.dataset.brand = '';
            }
        }

        function updateNumber() {
            if (!displays.number) return;
            const formatted = inputs.number ? inputs.number.value : '';
            displays.number.textContent = formatted || '•••• •••• •••• ••••';
            updateBrand(onlyDigits(formatted));
        }

        function updateHolder() {
            if (!displays.holder) return;
            const v = (inputs.holder?.value || '').toUpperCase();
            displays.holder.textContent = v || 'NOME COMPLETO';
        }

        function updateExpiry() {
            if (!displays.expiry) return;
            const m = inputs.month?.value || '';
            const y = inputs.year?.value  || '';
            const yShort = y.length === 4 ? y.slice(2) : y;

            if (!m && !y) {
                displays.expiry.textContent = 'MM/AA';
                return;
            }

            const mFmt = m.padEnd(2, 'M');
            const yFmt = yShort.padEnd(2, 'A');
            displays.expiry.textContent = `${mFmt}/${yFmt}`;
        }

        function updateCvv() {
            if (!displays.cvv) return;
            const v = inputs.cvv?.value || '';
            displays.cvv.textContent = v || '•••';
        }

        // Listeners de espelhamento
        if (inputs.number) inputs.number.addEventListener('input', updateNumber);
        if (inputs.holder) inputs.holder.addEventListener('input', updateHolder);
        if (inputs.month)  inputs.month.addEventListener('input', updateExpiry);
        if (inputs.year)   inputs.year.addEventListener('input', updateExpiry);
        if (inputs.cvv)    inputs.cvv.addEventListener('input', updateCvv);

        // Auto-skip mês → ano quando completo
        if (inputs.month && inputs.year) {
            inputs.month.addEventListener('input', function () {
                if (inputs.month.value.length === 2) inputs.year.focus();
            });
        }

        // Card flip ao focar CVV
        const flipTrigger = document.querySelector('[data-card-flip-trigger]');
        if (flipTrigger) {
            flipTrigger.addEventListener('focus', () => visual.classList.add('is-flipped'));
            flipTrigger.addEventListener('blur',  () => visual.classList.remove('is-flipped'));
        }

        // Sync inicial (para caso de form re-renderizado com old values)
        updateNumber();
        updateHolder();
        updateExpiry();
        updateCvv();
    }

    // ========================================================================
    // MÓDULO 5 — PAYMENT TABS (radio cartão/PIX → bodies + summary)
    // ========================================================================

    function initPaymentTabs() {
        const radios = document.querySelectorAll('.checkout-payment-tab__radio');
        if (!radios.length) return;

        function syncBodies() {
            const checked = document.querySelector('.checkout-payment-tab__radio:checked');
            if (!checked) return;
            const method = checked.value;

            document.querySelectorAll('[data-payment-body]').forEach(function (body) {
                if (body.dataset.paymentBody === method) {
                    body.classList.add('is-active');
                } else {
                    body.classList.remove('is-active');
                }
            });

            updatePixDiscount(method);
            updateInstallmentsInfoVisibility(method);
        }

        radios.forEach(r => r.addEventListener('change', syncBodies));
        syncBodies();
    }

    /** Mantém frete, desconto PIX e CTAs com o mesmo total exibido. */
    function updatePixDiscount(method) {
        const row     = document.querySelector('[data-pix-discount-row]');
        const valueEl = document.querySelector('[data-pix-discount-value]');
        const totalEl = document.querySelector('[data-total-amount]');
        const summary = document.querySelector('[data-checkout-summary]');
        if (!row || !valueEl || !totalEl || !summary) return;

        const baseTotal = parseFloat(summary.dataset.baseTotal || '0') || 0;
        const shippingInput = document.querySelector('[name="shipping_method"]:checked');
        const shipping = shippingInput
            ? parseFloat(shippingInput.dataset.shippingPrice || '0') || 0
            : 0;
        const discount = method === 'pix' && META.pixDiscount > 0
            ? baseTotal * (META.pixDiscount / 100)
            : 0;
        const newTotal = Math.max(0, baseTotal - discount + shipping);
        const shippingAmount = document.querySelector('[data-shipping-amount]');
        const shippingRow = document.querySelector('[data-shipping-row]');
        const shippingLabel = document.querySelector('[data-shipping-label-summary]');

        if (discount > 0) {
            valueEl.textContent = '−R$ ' + moneyBR(discount);
            row.removeAttribute('hidden');
        } else {
            row.setAttribute('hidden', '');
        }

        if (shippingAmount) {
            shippingAmount.textContent = shippingInput
                ? (shipping === 0 ? 'Grátis' : 'R$ ' + moneyBR(shipping))
                : 'A calcular';
        }
        if (shippingRow) {
            shippingRow.classList.toggle('is-empty', !shippingInput);
            shippingRow.classList.toggle('is-free', Boolean(shippingInput) && shipping === 0);
        }
        if (shippingLabel) {
            shippingLabel.textContent = shippingInput
                ? `Frete (${shippingInput.dataset.shippingLabel || 'Entrega'})`
                : 'Frete';
        }

        totalEl.textContent = moneyBR(newTotal);
        document.querySelectorAll('[data-mobile-total], [data-cta-total]').forEach(function (el) {
            el.textContent = moneyBR(newTotal);
        });

        const preview = document.querySelector('[data-payment-preview-total]');
        if (preview) preview.textContent = 'R$ ' + moneyBR(newTotal);
    }

    function updateInstallmentsInfoVisibility(method) {
        const info = document.querySelector('[data-installments-info]');
        if (!info) return;

        if (method === 'pix') {
            info.setAttribute('hidden', '');
        } else if (META.installmentsNoInterestMax >= 2) {
            info.removeAttribute('hidden');
        }
    }

    // ========================================================================
    // MÓDULO 6 — STEPS (validar → fechar / abrir próximo / editar)
    // ========================================================================

    function initSteps() {
        const allSteps = Array.from(document.querySelectorAll('[data-checkout-step]'));
        if (!allSteps.length) return;

        let initial = allSteps.find(step => step.classList.contains('checkout-step--active'))
            || allSteps.find(step => !step.classList.contains('checkout-step--complete'))
            || allSteps[allSteps.length - 1];
        activateStep(initial, false);

        // Botões "Continuar"
        document.querySelectorAll('[data-step-continue]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const step = btn.closest('[data-checkout-step]');
                if (!step) return;

                if (validateStepFields(step)) {
                    completeStep(step);
                    openNextStep(step);
                }
            });
        });

        // Botões "Editar"
        document.querySelectorAll('[data-step-edit]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const step = btn.closest('[data-checkout-step]');
                if (!step) return;
                activateStep(step);
            });
        });

        // No mobile, as etapas concluídas do indicador também funcionam como
        // atalhos para o cliente revisar os dados anteriores.
        document.querySelectorAll('[data-progress-step]').forEach(function (progress) {
            progress.setAttribute('role', 'button');

            const openCompletedStep = function () {
                if (!progress.classList.contains('is-complete')) return;

                const target = document.querySelector(
                    `[data-checkout-step="${progress.dataset.progressStep}"]`,
                );
                if (!target) return;

                activateStep(target);
                if (window.matchMedia('(max-width: 1023px)').matches) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            };

            progress.addEventListener('click', openCompletedStep);
            progress.addEventListener('keydown', function (event) {
                if (event.key !== 'Enter' && event.key !== ' ') return;
                event.preventDefault();
                openCompletedStep();
            });
        });

        // Atualizar resumo quando user digita
        document.querySelectorAll('[data-summary-target]').forEach(function (input) {
            input.addEventListener('input', function () {
                const step = input.closest('[data-checkout-step]');
                if (!step) return;
                const targetName = input.dataset.summaryTarget;
                const summaryEl  = step.querySelector(`[data-summary-${targetName}]`);
                if (summaryEl) summaryEl.textContent = input.value || '';
            });
        });
    }

    function validateStepFields(step) {
        const fieldNames = (step.dataset.stepFields || '').split(',')
            .map(s => s.trim()).filter(Boolean);

        let firstInvalid = null;

        fieldNames.forEach(function (name) {
            const candidates = Array.from(step.querySelectorAll(`[name="${name}"]`));
            const input = candidates[0];
            if (!input) return;

            // Limpa erro anterior do client
            input.classList.remove('checkout-field__input--invalid');
            input.closest('.checkout-field, .checkout-shipping')
                ?.querySelectorAll('.checkout-field__error--client')
                .forEach(error => error.remove());

            const selectedRadio = input.type === 'radio'
                ? candidates.find(candidate => candidate.checked)
                : null;
            const value = input.type === 'radio'
                ? (selectedRadio?.value || '')
                : (input.value || '').trim();
            const isRequired = input.hasAttribute('required') || input.required;

            // Vazio em campo obrigatório
            if (isRequired && !value) {
                showClientError(input, 'Campo obrigatório');
                if (!firstInvalid) firstInvalid = input;
                return;
            }

            // Validações específicas
            if (name === 'cpf_cnpj' && value && !validateCpfCnpj(value)) {
                showClientError(input, 'CPF inválido');
                if (!firstInvalid) firstInvalid = input;
            }

            if (name === 'name' && value && value.trim().split(/\s+/).length < 2) {
                showClientError(input, 'Informe seu nome e sobrenome');
                if (!firstInvalid) firstInvalid = input;
            }

            if (name === 'email' && value && !input.checkValidity()) {
                showClientError(input, 'E-mail inválido');
                if (!firstInvalid) firstInvalid = input;
            }

            if (name === 'phone' && value && onlyDigits(value).length < 10) {
                showClientError(input, 'Celular inválido');
                if (!firstInvalid) firstInvalid = input;
            }

            if (name === 'card_number' && value && !validateLuhn(onlyDigits(value))) {
                showClientError(input, 'Número do cartão inválido');
                if (!firstInvalid) firstInvalid = input;
            }

            if (name === 'zip' && value && onlyDigits(value).length !== 8) {
                showClientError(input, 'CEP deve ter 8 dígitos');
                if (!firstInvalid) firstInvalid = input;
            }
        });

        if (firstInvalid) {
            firstInvalid.focus();
            firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return false;
        }

        return true;
    }

    function showClientError(input, message) {
        input.classList.add('checkout-field__input--invalid');

        // Não duplica erro do servidor
        const field = input.closest('.checkout-field, .checkout-shipping');
        const existingServerError = field?.querySelector('.checkout-field__error');
        if (existingServerError && !existingServerError.classList.contains('checkout-field__error--client')) {
            return;
        }

        const err = document.createElement('span');
        err.className = 'checkout-field__error checkout-field__error--client';
        err.setAttribute('role', 'alert');
        err.textContent = message;

        // Insere depois do input (ou depois do wrap se houver)
        if (input.type === 'radio' && field) {
            field.appendChild(err);
            return;
        }

        const wrap = input.closest('.checkout-field__input-wrap, .checkout-card-expiry');
        (wrap || input).insertAdjacentElement('afterend', err);
    }

    function completeStep(step) {
        step.classList.remove('checkout-step--active');
        step.classList.remove('checkout-step--pending');
        step.classList.add('checkout-step--complete');
        syncStepSummary(step);
        updateProgress();
    }

    function activateStep(step, shouldFocus = true) {
        const steps = Array.from(document.querySelectorAll('[data-checkout-step]'));
        const activeIndex = steps.indexOf(step);

        steps.forEach(function (candidate, index) {
            candidate.classList.remove('checkout-step--active', 'checkout-step--pending');
            if (index < activeIndex) {
                candidate.classList.add('checkout-step--complete');
            } else if (candidate === step) {
                candidate.classList.remove('checkout-step--complete');
                candidate.classList.add('checkout-step--active');
            } else {
                candidate.classList.remove('checkout-step--complete');
                candidate.classList.add('checkout-step--pending');
            }
        });
        updateProgress();

        const firstInput = step.querySelector(
            'input:not([readonly]):not([type="hidden"]):not([type="radio"]), select, textarea',
        );
        if (firstInput && shouldFocus) {
            setTimeout(() => firstInput.focus(), 50);
        }
    }

    function openNextStep(currentStep) {
        const all = Array.from(document.querySelectorAll('[data-checkout-step]'));
        const idx = all.indexOf(currentStep);
        const next = all[idx + 1];
        if (!next) return;

        // Se próximo já está complete, mantém (user clicou em editar antes e voltou)
        if (next.classList.contains('checkout-step--complete')) return;

        activateStep(next);
        if (window.matchMedia('(max-width: 1023px)').matches) {
            next.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    function syncStepSummary(step) {
        const inputs = step.querySelectorAll('[data-summary-target]');
        inputs.forEach(function (input) {
            const targetName = input.dataset.summaryTarget;
            const summaryEl  = step.querySelector(`[data-summary-${targetName}]`);
            if (summaryEl) summaryEl.textContent = input.value || '';
        });
    }

    function updateProgress() {
        const steps = Array.from(document.querySelectorAll('[data-checkout-step]'));
        const activeIndex = steps.findIndex(step => step.classList.contains('checkout-step--active'));

        document.querySelectorAll('[data-progress-step]').forEach(function (progress, index) {
            progress.classList.toggle('is-active', index === activeIndex);
            progress.classList.toggle('is-complete', index < activeIndex);
            progress.tabIndex = index < activeIndex ? 0 : -1;
            progress.setAttribute('aria-disabled', index < activeIndex ? 'false' : 'true');
            if (index === activeIndex) {
                progress.setAttribute('aria-current', 'step');
            } else {
                progress.removeAttribute('aria-current');
            }
        });
    }

    function initShipping() {
        document.querySelectorAll('[name="shipping_method"]').forEach(function (radio) {
            radio.addEventListener('change', function () {
                const method = document.querySelector('[name="payment_method"]:checked')?.value || 'pix';
                updatePixDiscount(method);
            });
        });

        const method = document.querySelector('[name="payment_method"]:checked')?.value || 'pix';
        updatePixDiscount(method);
    }

    function initSummaryToggle() {
        const button = document.querySelector('[data-summary-toggle]');
        const panel = document.querySelector('[data-summary-panel]');
        if (!button || !panel) return;

        const mobile = window.matchMedia('(max-width: 1023px)');

        function syncForViewport() {
            if (mobile.matches) {
                button.setAttribute('aria-expanded', 'true');
                panel.removeAttribute('hidden');
            } else {
                button.setAttribute('aria-expanded', 'true');
                panel.removeAttribute('hidden');
            }
        }

        syncForViewport();
        mobile.addEventListener?.('change', syncForViewport);

        button.addEventListener('click', function () {
            const expanded = button.getAttribute('aria-expanded') === 'true';
            button.setAttribute('aria-expanded', String(!expanded));
            panel.toggleAttribute('hidden', expanded);
        });
    }

    function initCartQuantity() {
        const summary = document.querySelector('[data-checkout-summary]');
        const endpoint = summary?.dataset.cartUpdateUrl;
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
        if (!summary || !endpoint || !csrf) return;

        summary.querySelectorAll('[data-cart-item]').forEach(function (item) {
            item.querySelectorAll('[data-cart-quantity-change]').forEach(function (button) {
                button.addEventListener('click', async function () {
                    if (button.disabled || item.dataset.loading === 'true') return;

                    const current = parseInt(item.dataset.cartQuantity || '1', 10);
                    const delta = parseInt(button.dataset.cartQuantityChange || '0', 10);
                    const quantity = Math.max(1, current + delta);
                    if (quantity === current) return;

                    item.dataset.loading = 'true';
                    item.querySelectorAll('[data-cart-quantity-change]').forEach(control => {
                        control.disabled = true;
                    });

                    try {
                        const response = await fetch(endpoint, {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrf,
                            },
                            body: JSON.stringify({
                                updates: { [item.dataset.cartKey]: quantity },
                            }),
                        });

                        if (!response.ok) throw new Error(`HTTP ${response.status}`);
                        const data = await response.json();
                        const responseItems = Array.isArray(data.items)
                            ? data.items
                            : Object.values(data.items || {});
                        const updated = responseItems.find(
                            candidate => candidate.key === item.dataset.cartKey,
                        );
                        if (!updated) return;

                        if (delta > 0) {
                            trackCheckoutCartMutation(data, {
                                key: item.dataset.cartKey,
                                quantity: delta,
                                delta,
                            });
                        }

                        item.dataset.cartQuantity = String(updated.quantity);
                        item.querySelector('[data-cart-item-quantity]').textContent = updated.quantity;
                        item.querySelector('[data-cart-item-price]').textContent =
                            'R$ ' + moneyBR(Number(updated.price) * Number(updated.quantity));

                        document.querySelectorAll('[data-cart-count]').forEach(function (count) {
                            count.textContent = data.count;
                        });

                        const subtotal = document.querySelector('[data-cart-subtotal]');
                        if (subtotal) subtotal.textContent = moneyBR(Number(data.subtotal));

                        summary.dataset.baseTotal = Number(data.total).toFixed(2);
                        const method = document.querySelector('[name="payment_method"]:checked')?.value || 'pix';
                        updatePixDiscount(method);
                    } catch (error) {
                        console.warn('Cart quantity error:', error);
                    } finally {
                        item.dataset.loading = 'false';
                        item.querySelectorAll('[data-cart-quantity-change]').forEach(control => {
                            control.disabled = false;
                        });
                    }
                });
            });
        });
    }

    function initCoupon() {
        const coupon = document.querySelector('[data-coupon]');
        const applyButton = coupon?.querySelector('[data-coupon-apply]');
        const removeButton = coupon?.querySelector('[data-coupon-remove]');
        const input = coupon?.querySelector('[data-coupon-input]');
        const message = coupon?.querySelector('[data-coupon-message]');
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
        if (!coupon || !message || !csrf) return;

        const endpoint = coupon.dataset.couponUrl || '/cart/coupon.json';

        function setMessage(text, state = '') {
            message.textContent = text;
            if (state) {
                message.dataset.state = state;
            } else {
                delete message.dataset.state;
            }
        }

        async function responseJson(response) {
            try {
                return await response.json();
            } catch (error) {
                return {};
            }
        }

        async function applyCoupon() {
            if (!applyButton || !input || applyButton.disabled) return;

            const code = input.value.trim().toUpperCase();
            input.value = code;

            if (!code) {
                setMessage('Digite o código do cupom.', 'error');
                return;
            }

            applyButton.disabled = true;
            setMessage('Aplicando...');

            try {
                const response = await fetch(endpoint, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                    body: JSON.stringify({ code }),
                });
                const data = await responseJson(response);

                if (response.ok && data.success) {
                    setMessage(data.message || 'Cupom aplicado!', 'success');
                    window.location.reload();
                    return;
                }

                setMessage(data.message || 'Cupom inválido.', 'error');
            } catch (error) {
                setMessage('Não foi possível aplicar o cupom.', 'error');
            } finally {
                applyButton.disabled = false;
            }
        }

        if (input && applyButton) {
            input.addEventListener('input', function () {
                const cursor = input.selectionStart;
                input.value = input.value.toUpperCase();
                if (cursor !== null) input.setSelectionRange(cursor, cursor);
                setMessage('');
            });

            input.addEventListener('keydown', function (event) {
                if (event.key !== 'Enter') return;
                event.preventDefault();
                event.stopPropagation();
                applyCoupon();
            });

            applyButton.addEventListener('click', applyCoupon);
        }

        if (removeButton) {
            removeButton.addEventListener('click', async function () {
                if (removeButton.disabled) return;

                removeButton.disabled = true;
                removeButton.setAttribute('aria-busy', 'true');
                setMessage('Removendo...');

                try {
                    const response = await fetch(endpoint, {
                        method: 'DELETE',
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                        },
                    });
                    const data = await responseJson(response);

                    if (response.ok) {
                        setMessage(data.message || 'Cupom removido.', 'success');
                        window.location.reload();
                        return;
                    }

                    setMessage(data.message || 'Não foi possível remover o cupom.', 'error');
                } catch (error) {
                    setMessage('Não foi possível remover o cupom.', 'error');
                } finally {
                    removeButton.disabled = false;
                    removeButton.removeAttribute('aria-busy');
                }
            });
        }
    }

    // ========================================================================
    // MÓDULO 7 — DEVICE DATA (pra 3DS)
    // ========================================================================

    function initDeviceData() {
        const data = {
            language:        navigator.language || navigator.userLanguage || 'pt-BR',
            color_depth:     screen.colorDepth || 24,
            screen_height:   screen.height || 0,
            screen_width:    screen.width  || 0,
            time_difference: -new Date().getTimezoneOffset(),
            java_enabled:    (navigator.javaEnabled && navigator.javaEnabled()) ? '1' : '0',
        };

        document.querySelectorAll('[data-device-field]').forEach(function (input) {
            const key = input.dataset.deviceField;
            if (key in data) input.value = data[key];
        });
    }

    // ========================================================================
    // MÓDULO 8 — SUBMIT (validação final + loading)
    // ========================================================================

    function initSubmit() {
        const form = document.querySelector('[data-checkout-form]');
        if (!form) return;

        form.addEventListener('submit', function (e) {
            // Valida todos os steps
            const steps = document.querySelectorAll('[data-checkout-step]');
            let allValid = true;

            let firstInvalidStep = null;
            steps.forEach(function (step) {
                if (!validateStepFields(step)) {
                    allValid = false;
                    if (!firstInvalidStep) firstInvalidStep = step;
                }
            });

            if (!allValid) {
                e.preventDefault();
                if (firstInvalidStep) activateStep(firstInvalidStep);
                return;
            }

            // AddPaymentInfo só é emitido após todas as etapas estarem válidas.
            // O runtime lê apenas itens, total e método de pagamento.
            trackCheckoutPayment(form);

            // Loading state no CTA
            const cta = document.querySelector('[data-checkout-submit]');
            if (cta) {
                cta.dataset.loading = 'true';
                cta.disabled = true;
            }

            const overlay = document.querySelector('[data-checkout-processing]');
            if (overlay) overlay.removeAttribute('hidden');
        });
    }

    // ========================================================================
    // MÓDULO 9 — URGENCY TIMER
    // ========================================================================

    function initUrgencyTimer() {
        const timer = document.querySelector('[data-urgency-timer]');
        const display = document.querySelector('[data-urgency-display]');
        if (!timer || !display) return;

        const minutes = parseInt(timer.dataset.urgencyTimer, 10);
        if (!minutes || minutes <= 0) return;

        let totalSeconds = minutes * 60;

        function tick() {
            if (totalSeconds <= 0) {
                const bar = document.querySelector('.checkout-urgency');
                if (bar) bar.style.display = 'none';
                clearInterval(intervalId);
                return;
            }
            const m = Math.floor(totalSeconds / 60);
            const s = totalSeconds % 60;
            display.textContent = `${m}:${s.toString().padStart(2, '0')}`;
            totalSeconds--;
        }

        tick();
        const intervalId = setInterval(tick, 1000);
    }

    // ========================================================================
    // INIT
    // ========================================================================

    function init() {
        initMasks();
        initCountrySelect();
        initViaCep();
        initCard();
        initPaymentTabs();
        initSteps();
        initShipping();
        initSummaryToggle();
        initCartQuantity();
        initCoupon();
        initDeviceData();
        initSubmit();
        initUrgencyTimer();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    /* ============================================================================
    C2 — Downsell modal
    ============================================================================
    APPEND este bloco ao fim de public/checkout-assets/checkout.js
    ANTES do `})();` final (último fechamento do IIFE).

    Funcionalidades:
        - Auto-abre se data-downsell-modal está presente
        - Fecha em [data-downsell-close] e tecla ESC
        - NÃO fecha clicando no overlay (decisão de UX)
        - Loading no botão CTA quando submete o form
   ============================================================================ */

    document.addEventListener('DOMContentLoaded', function () {
        const modal = document.querySelector('[data-downsell-modal]');
        if (!modal) return;

        function open() {
            modal.classList.add('is-open');
            document.body.style.overflow = 'hidden';

            // Foca no botão primário pra acessibilidade
            const cta = modal.querySelector('[data-downsell-submit]');
            if (cta) setTimeout(() => cta.focus(), 100);
        }

        function close() {
            modal.classList.remove('is-open');
            document.body.style.overflow = '';
        }

        // Botões "Tentar com outro cartão" e X de fechar
        modal.querySelectorAll('[data-downsell-close]').forEach(function (btn) {
            btn.addEventListener('click', close);
        });

        // ESC fecha
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && modal.classList.contains('is-open')) {
                close();
            }
        });

        // Loading state no submit
        const form = modal.querySelector('form');
        const cta  = modal.querySelector('[data-downsell-submit]');
        if (form && cta) {
            form.addEventListener('submit', function () {
                cta.dataset.loading = 'true';
                cta.disabled = true;
            });
        }

        // Auto-abre com delay leve (deixa página assentar)
        setTimeout(open, 250);
    });

})();
