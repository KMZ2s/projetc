@php
    $addr = $defaultAddress;
    $stepHasErrors = $errors->hasAny([
        'zip', 'street', 'number', 'neighborhood', 'city', 'state', 'shipping_method',
    ]);
    $valueZip = old('zip', $addr?->zipcode ?? '');
    $valueStreet = old('street', $addr?->street ?? '');
    $valueNumber = old('number', $addr?->number ?? '');
    $valueComplement = old('complement', $addr?->complement ?? '');
    $valueNeighborhood = old('neighborhood', $addr?->neighborhood ?? '');
    $valueCity = old('city', $addr?->city ?? '');
    $valueState = old('state', $addr?->state ?? '');
    $valueShipping = old('shipping_method', '');
    $shippingImages = [
        'full_free' => 'checkout-assets/img/shipping/full-gratis.png',
        'jadlog' => 'checkout-assets/img/shipping/jadlog.jfif',
        'sedex' => 'checkout-assets/img/shipping/sedex.webp',
    ];
    $estadosBR = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];
    $isComplete = !$stepHasErrors
        && filled($valueZip)
        && filled($valueStreet)
        && filled($valueNumber)
        && filled($valueNeighborhood)
        && filled($valueCity)
        && filled($valueState)
        && filled($valueShipping);
@endphp

<section class="checkout-step {{ $isComplete ? 'checkout-step--complete' : 'checkout-step--pending' }}"
         data-checkout-step="delivery"
         data-step-fields="zip,city,state,neighborhood,street,number,shipping_method"
         aria-labelledby="checkout-step-delivery-title">
    <div class="checkout-step__head">
        <span class="checkout-step__num" aria-hidden="true">2</span>
        <h2 class="checkout-step__title" id="checkout-step-delivery-title">Endereço de Entrega</h2>
        <button type="button" class="checkout-step__edit" data-step-edit aria-label="Editar entrega">
            Editar
        </button>
    </div>

    <div class="checkout-step__summary">
        <div class="checkout-step__summary-row">
            <span class="checkout-step__summary-value">
                <span data-summary-street>{{ $valueStreet }}</span>,
                <span data-summary-number>{{ $valueNumber }}</span>
            </span>
        </div>
        <div class="checkout-step__summary-row checkout-step__summary-row--muted">
            <span data-summary-city>{{ $valueCity }}</span>/<span data-summary-state>{{ $valueState }}</span>
            <span class="checkout-step__summary-sep">·</span>
            CEP <span data-summary-zip>{{ $valueZip }}</span>
        </div>
    </div>

    <div class="checkout-step__body">
        <div class="checkout-field checkout-field--cep">
            <label class="checkout-field__label" for="checkout-zip">CEP</label>
            <div class="checkout-field__input-wrap">
                <input id="checkout-zip" name="zip" type="text" value="{{ $valueZip }}"
                       class="checkout-field__input @error('zip') checkout-field__input--invalid @enderror"
                       required inputmode="numeric" autocomplete="postal-code" data-mask="zip"
                       data-viacep-source data-summary-target="zip" placeholder="00000-000" maxlength="9">
                <span class="checkout-field__input-spinner" data-viacep-loading aria-hidden="true"></span>
            </div>
            @error('zip')
                <span class="checkout-field__error" role="alert">{{ $message }}</span>
            @else
                <span class="checkout-field__hint" data-cep-hint>Digite seu CEP para continuar</span>
            @enderror
        </div>

        <div class="checkout-address-fields" data-address-fields @if(strlen(preg_replace('/\D/', '', $valueZip)) !== 8) hidden @endif>
            <div class="checkout-field-row checkout-field-row--city">
                <div class="checkout-field">
                    <label class="checkout-field__label checkout-field__label--address" for="checkout-city">Cidade</label>
                    <input id="checkout-city" name="city" type="text" value="{{ $valueCity }}"
                           class="checkout-field__input @error('city') checkout-field__input--invalid @enderror"
                           required maxlength="255" autocomplete="address-level2"
                           data-viacep-target="city" data-summary-target="city" placeholder="Cidade">
                    @error('city')<span class="checkout-field__error" role="alert">{{ $message }}</span>@enderror
                </div>
                <div class="checkout-field checkout-field--uf">
                    <label class="checkout-field__label checkout-field__label--address" for="checkout-state">UF</label>
                    <select id="checkout-state" name="state"
                            class="checkout-field__input checkout-field__select @error('state') checkout-field__input--invalid @enderror"
                            required autocomplete="address-level1" data-viacep-target="state" data-summary-target="state">
                        <option value="">UF</option>
                        @foreach ($estadosBR as $uf)
                            <option value="{{ $uf }}" @selected($valueState === $uf)>{{ $uf }}</option>
                        @endforeach
                    </select>
                    @error('state')<span class="checkout-field__error" role="alert">{{ $message }}</span>@enderror
                </div>
            </div>

            <div class="checkout-field">
                <label class="checkout-field__label checkout-field__label--address" for="checkout-neighborhood">Bairro</label>
                <input id="checkout-neighborhood" name="neighborhood" type="text" value="{{ $valueNeighborhood }}"
                       class="checkout-field__input @error('neighborhood') checkout-field__input--invalid @enderror"
                       required maxlength="255" data-viacep-target="neighborhood"
                       data-summary-target="neighborhood" placeholder="Bairro">
                @error('neighborhood')<span class="checkout-field__error" role="alert">{{ $message }}</span>@enderror
            </div>

            <div class="checkout-field-row checkout-field-row--street">
                <div class="checkout-field">
                    <label class="checkout-field__label checkout-field__label--address" for="checkout-street">Endereço</label>
                    <input id="checkout-street" name="street" type="text" value="{{ $valueStreet }}"
                           class="checkout-field__input @error('street') checkout-field__input--invalid @enderror"
                           required maxlength="255" autocomplete="street-address" data-viacep-target="street"
                           data-summary-target="street" placeholder="Endereço">
                    @error('street')<span class="checkout-field__error" role="alert">{{ $message }}</span>@enderror
                </div>
                <div class="checkout-field checkout-field--number">
                    <label class="checkout-field__label checkout-field__label--address" for="checkout-number">Nº</label>
                    <input id="checkout-number" name="number" type="text" value="{{ $valueNumber }}"
                           class="checkout-field__input @error('number') checkout-field__input--invalid @enderror"
                           required maxlength="20" data-summary-target="number" placeholder="Nº">
                    @error('number')<span class="checkout-field__error" role="alert">{{ $message }}</span>@enderror
                </div>
            </div>

            <div class="checkout-field">
                <label class="checkout-field__label checkout-field__label--address" for="checkout-complement">
                    Complemento <span class="checkout-field__optional">(opcional)</span>
                </label>
                <input id="checkout-complement" name="complement" type="text" value="{{ $valueComplement }}"
                       class="checkout-field__input" maxlength="255" data-summary-target="complement"
                       placeholder="Complemento (opcional)">
            </div>

            <fieldset class="checkout-shipping">
                <legend>
                    <svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none">
                        <path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2M15 18H9M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.62L18.3 8.38A1 1 0 0 0 17.52 8H14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                        <circle cx="17" cy="18" r="2" stroke="currentColor" stroke-width="1.8"/>
                        <circle cx="7" cy="18" r="2" stroke="currentColor" stroke-width="1.8"/>
                    </svg>
                    <span>Frete</span>
                </legend>
                @foreach ($shippingOptions as $key => $option)
                    <label class="checkout-shipping__option">
                        <input type="radio" name="shipping_method" value="{{ $key }}"
                               data-shipping-price="{{ number_format($option['price'], 2, '.', '') }}"
                               data-shipping-label="{{ $option['label'] }}"
                               @checked($valueShipping === $key) required>
                        <span class="checkout-shipping__radio" aria-hidden="true"></span>
                        <img class="checkout-shipping__logo"
                             src="{{ asset($shippingImages[$key]) }}"
                             alt="{{ $option['label'] }}"
                             width="40" height="40">
                        <span class="checkout-shipping__content">
                            <strong>{{ $option['label'] }}</strong>
                            <small>{{ $option['description'] }}</small>
                        </span>
                        <strong class="checkout-shipping__price">
                            {{ $option['price'] > 0 ? 'R$ '.number_format($option['price'], 2, ',', '.') : 'Grátis' }}
                        </strong>
                    </label>
                @endforeach
                @error('shipping_method')<span class="checkout-field__error" role="alert">{{ $message }}</span>@enderror
            </fieldset>
        </div>

        <button type="button" class="checkout-step__continue" data-step-continue>
            <span>Ir para Pagamento</span>
        </button>
    </div>
</section>
