@php
    // Old values (form re-renderizado após erro de validação)
    $oldNumber = old('card_number', '');
    $oldHolder = old('card_holder_name', '');
    $oldMonth  = old('card_expiry_month', '');
    $oldYear   = old('card_expiry_year', '');
    $oldCvv    = old('card_cvv', '');
    $oldInstallments = (int) old('installments', 1);

    // Calcula opções de parcelamento no PHP (decisão #4)
    $cartTotal       = $cart['total'];
    $maxInstallments = $checkoutSettings->installments_max;
    $noInterestMax   = $checkoutSettings->installments_no_interest_max;

    $installmentOptions = [];
    for ($i = 1; $i <= $maxInstallments; $i++) {
        $perInstallment = round($cartTotal / $i, 2);
        $hasInterest    = $i > $noInterestMax;

        $installmentOptions[] = [
            'value'        => $i,
            'amount'       => $perInstallment,
            'has_interest' => $hasInterest,
            'label'        => $i === 1
                ? sprintf('1x de R$ %s à vista', number_format($perInstallment, 2, ',', '.'))
                : ($hasInterest
                    ? sprintf('%dx de R$ %s (juros do banco)', $i, number_format($perInstallment, 2, ',', '.'))
                    : sprintf('%dx de R$ %s sem juros',        $i, number_format($perInstallment, 2, ',', '.'))),
        ];
    }
@endphp

<div class="checkout-card-payment">

    {{-- ============================================================ --}}
    {{-- Card 3D Visual (gradiente cinza grafite)                       --}}
    {{-- ============================================================ --}}
    <div class="checkout-card-visual" data-card-visual aria-hidden="true">
        <div class="checkout-card-visual__inner">

            {{-- ===== FRENTE ===== --}}
            <div class="checkout-card-visual__face checkout-card-visual__face--front">

                {{-- Bandeira (slot vazio — JS B4 injeta SVG quando detecta) --}}
                <div class="checkout-card-visual__brand" data-card-brand>
                    <span class="checkout-card-visual__brand-placeholder">CARD</span>
                </div>

                {{-- Chip dourado --}}
                <div class="checkout-card-visual__chip">
                    <svg width="40" height="32" viewBox="0 0 40 32" fill="none">
                        <defs>
                            <linearGradient id="cardChipGrad" x1="0" y1="0" x2="40" y2="32">
                                <stop offset="0%"   stop-color="#fde68a"/>
                                <stop offset="50%"  stop-color="#d4a849"/>
                                <stop offset="100%" stop-color="#92760a"/>
                            </linearGradient>
                        </defs>
                        <rect x="0" y="0" width="40" height="32" rx="4" fill="url(#cardChipGrad)"/>
                        <line x1="0"  y1="10" x2="14" y2="10" stroke="#92760a" stroke-width="0.6"/>
                        <line x1="0"  y1="22" x2="14" y2="22" stroke="#92760a" stroke-width="0.6"/>
                        <line x1="26" y1="10" x2="40" y2="10" stroke="#92760a" stroke-width="0.6"/>
                        <line x1="26" y1="22" x2="40" y2="22" stroke="#92760a" stroke-width="0.6"/>
                        <rect x="14" y="6" width="12" height="20" rx="2" stroke="#92760a" stroke-width="0.6" fill="none"/>
                    </svg>
                </div>

                {{-- Número --}}
                <div class="checkout-card-visual__number" data-card-number-display>
                    •••• •••• •••• ••••
                </div>

                {{-- Footer: nome + validade --}}
                <div class="checkout-card-visual__footer">
                    <div class="checkout-card-visual__name">
                        <span class="checkout-card-visual__label">Titular</span>
                        <strong data-card-holder-display>NOME COMPLETO</strong>
                    </div>
                    <div class="checkout-card-visual__expiry">
                        <span class="checkout-card-visual__label">Validade</span>
                        <strong data-card-expiry-display>MM/AA</strong>
                    </div>
                </div>

            </div>

            {{-- ===== VERSO ===== --}}
            <div class="checkout-card-visual__face checkout-card-visual__face--back">
                <div class="checkout-card-visual__stripe"></div>
                <div class="checkout-card-visual__signature">
                    <div class="checkout-card-visual__signature-bar"></div>
                    <span class="checkout-card-visual__cvv-box" data-card-cvv-display>•••</span>
                </div>
                <div class="checkout-card-visual__back-label">CVV — 3 dígitos</div>
            </div>

        </div>
    </div>

    {{-- ============================================================ --}}
    {{-- Form de cartão                                                 --}}
    {{-- ============================================================ --}}
    <div class="checkout-card-form">

        {{-- Número do cartão --}}
        <div class="checkout-field">
            <label class="checkout-field__label" for="card-number">
                Número do cartão <span class="checkout-field__required">*</span>
            </label>
            <input id="card-number"
                   name="card_number"
                   type="text"
                   value="{{ $oldNumber }}"
                   class="checkout-field__input @error('card_number') checkout-field__input--invalid @enderror"
                   inputmode="numeric"
                   autocomplete="cc-number"
                   data-mask="card_number"
                   data-card-mirror="number"
                   placeholder="0000 0000 0000 0000"
                   maxlength="23">
            @error('card_number')
                <span class="checkout-field__error" role="alert">{{ $message }}</span>
            @enderror
        </div>

        {{-- Nome do titular --}}
        <div class="checkout-field">
            <label class="checkout-field__label" for="card-holder">
                Nome impresso no cartão <span class="checkout-field__required">*</span>
            </label>
            <input id="card-holder"
                   name="card_holder_name"
                   type="text"
                   value="{{ $oldHolder }}"
                   class="checkout-field__input checkout-field__input--uppercase @error('card_holder_name') checkout-field__input--invalid @enderror"
                   autocomplete="cc-name"
                   maxlength="100"
                   data-card-mirror="holder"
                   placeholder="EX: JOAO M SILVA">
            @error('card_holder_name')
                <span class="checkout-field__error" role="alert">{{ $message }}</span>
            @enderror
        </div>

        {{-- Linha: validade + CVV --}}
        <div class="checkout-field-row">

            {{-- Validade (2 inputs juntos visualmente) --}}
            <div class="checkout-field">
                <label class="checkout-field__label">
                    Validade <span class="checkout-field__required">*</span>
                </label>
                <div class="checkout-card-expiry">
                    <input name="card_expiry_month"
                           type="text"
                           value="{{ $oldMonth }}"
                           class="checkout-card-expiry__input checkout-card-expiry__month @error('card_expiry_month') is-invalid @enderror"
                           inputmode="numeric"
                           autocomplete="cc-exp-month"
                           data-mask="month"
                           data-card-mirror="month"
                           placeholder="MM"
                           maxlength="2"
                           aria-label="Mês de validade">
                    <span class="checkout-card-expiry__separator" aria-hidden="true">/</span>
                    <input name="card_expiry_year"
                           type="text"
                           value="{{ $oldYear }}"
                           class="checkout-card-expiry__input checkout-card-expiry__year @error('card_expiry_year') is-invalid @enderror"
                           inputmode="numeric"
                           autocomplete="cc-exp-year"
                           data-mask="year"
                           data-card-mirror="year"
                           placeholder="AAAA"
                           maxlength="4"
                           aria-label="Ano de validade">
                </div>
                @error('card_expiry_month')
                    <span class="checkout-field__error" role="alert">{{ $message }}</span>
                @enderror
                @error('card_expiry_year')
                    <span class="checkout-field__error" role="alert">{{ $message }}</span>
                @enderror
            </div>

            {{-- CVV com tooltip --}}
            <div class="checkout-field checkout-field--small">
                <label class="checkout-field__label" for="card-cvv">
                    <span>CVV <span class="checkout-field__required">*</span></span>
                    <span class="checkout-tooltip">
                        <button type="button" class="checkout-tooltip__trigger" aria-label="O que é CVV?">?</button>
                        <span class="checkout-tooltip__content" role="tooltip">
                            Os 3 dígitos no verso do cartão (4 na frente, no caso da Amex).
                        </span>
                    </span>
                </label>
                <input id="card-cvv"
                       name="card_cvv"
                       type="text"
                       value="{{ $oldCvv }}"
                       class="checkout-field__input @error('card_cvv') checkout-field__input--invalid @enderror"
                       inputmode="numeric"
                       autocomplete="cc-csc"
                       data-mask="cvv"
                       data-card-mirror="cvv"
                       data-card-flip-trigger
                       placeholder="•••"
                       maxlength="4">
                @error('card_cvv')
                    <span class="checkout-field__error" role="alert">{{ $message }}</span>
                @enderror
            </div>

        </div>

        {{-- Parcelas --}}
        <div class="checkout-field">
            <label class="checkout-field__label" for="card-installments">
                Parcelas <span class="checkout-field__required">*</span>
            </label>
            <select id="card-installments"
                    name="installments"
                    class="checkout-field__input checkout-field__select @error('installments') checkout-field__input--invalid @enderror">
                @foreach ($installmentOptions as $option)
                    <option value="{{ $option['value'] }}"
                            data-has-interest="{{ $option['has_interest'] ? '1' : '0' }}"
                            @selected($oldInstallments === $option['value'])>
                        {{ $option['label'] }}
                    </option>
                @endforeach
            </select>
            @error('installments')
                <span class="checkout-field__error" role="alert">{{ $message }}</span>
            @enderror
        </div>

    </div>
</div>