@php
    $stepHasErrors = $errors->hasAny(['name', 'email', 'phone', 'cpf_cnpj']);
    $valueName = old('name', $user?->display_name ?? '');
    $valueEmail = old('email', $user?->email ?? '');
    $valuePhone = old('phone', $user?->phone ?? '');
    $valueCpfCnpj = old('cpf_cnpj', $user?->cpf_cnpj ?? '');
    $isComplete = !$stepHasErrors
        && filled($valueName)
        && filled($valueEmail)
        && filled($valuePhone)
        && filled($valueCpfCnpj);
    $countries = [
        ['BR', 'Brasil', '+55'], ['US', 'Estados Unidos', '+1'], ['PT', 'Portugal', '+351'],
        ['ES', 'Espanha', '+34'], ['AR', 'Argentina', '+54'], ['CL', 'Chile', '+56'],
        ['CO', 'Colômbia', '+57'], ['MX', 'México', '+52'], ['PE', 'Peru', '+51'],
        ['UY', 'Uruguai', '+598'], ['PY', 'Paraguai', '+595'], ['BO', 'Bolívia', '+591'],
        ['EC', 'Equador', '+593'], ['VE', 'Venezuela', '+58'], ['FR', 'França', '+33'],
        ['DE', 'Alemanha', '+49'], ['IT', 'Itália', '+39'], ['GB', 'Reino Unido', '+44'],
        ['CA', 'Canadá', '+1'], ['AU', 'Austrália', '+61'], ['JP', 'Japão', '+81'],
        ['CN', 'China', '+86'], ['IN', 'Índia', '+91'], ['ZA', 'África do Sul', '+27'],
        ['AE', 'Emirados Árabes', '+971'], ['IL', 'Israel', '+972'], ['RU', 'Rússia', '+7'],
        ['KR', 'Coreia do Sul', '+82'], ['NL', 'Holanda', '+31'], ['BE', 'Bélgica', '+32'],
        ['CH', 'Suíça', '+41'], ['AT', 'Áustria', '+43'], ['PL', 'Polônia', '+48'],
        ['SE', 'Suécia', '+46'], ['NO', 'Noruega', '+47'], ['DK', 'Dinamarca', '+45'],
        ['FI', 'Finlândia', '+358'], ['IE', 'Irlanda', '+353'], ['NZ', 'Nova Zelândia', '+64'],
        ['SG', 'Singapura', '+65'],
    ];
@endphp

<section class="checkout-step {{ $isComplete ? 'checkout-step--complete' : 'checkout-step--active' }}"
         data-checkout-step="identification"
         data-step-fields="name,email,cpf_cnpj,phone"
         aria-labelledby="checkout-step-identification-title">
    <div class="checkout-step__head">
        <span class="checkout-step__num" aria-hidden="true">1</span>
        <h2 class="checkout-step__title" id="checkout-step-identification-title">Identificação</h2>
        <button type="button" class="checkout-step__edit" data-step-edit aria-label="Editar identificação">
            Editar
        </button>
    </div>

    <div class="checkout-step__summary">
        <div class="checkout-step__summary-row">
            <span class="checkout-step__summary-value" data-summary-name>{{ $valueName }}</span>
        </div>
        <div class="checkout-step__summary-row checkout-step__summary-row--muted">
            <span data-summary-email>{{ $valueEmail }}</span>
            <span class="checkout-step__summary-sep">·</span>
            <span data-summary-phone>{{ $valuePhone }}</span>
        </div>
    </div>

    <div class="checkout-step__body">
        <p class="checkout-step__helper">
            Utilizaremos seu e-mail para: identificar seu perfil, histórico de compra,
            notificação de pedidos e carrinho de compras.
        </p>

        <div class="checkout-field">
            <label class="checkout-field__label" for="checkout-name">Nome completo</label>
            <input id="checkout-name" name="name" type="text" value="{{ $valueName }}"
                   class="checkout-field__input @error('name') checkout-field__input--invalid @enderror"
                   required minlength="3" maxlength="255" autocomplete="name"
                   placeholder="ex.: Maria de Almeida Cruz" data-summary-target="name">
            @error('name')<span class="checkout-field__error" role="alert">{{ $message }}</span>@enderror
        </div>

        <div class="checkout-field">
            <label class="checkout-field__label" for="checkout-email">E-mail</label>
            <input id="checkout-email" name="email" type="email" value="{{ $valueEmail }}"
                   class="checkout-field__input @error('email') checkout-field__input--invalid @enderror"
                   required maxlength="255" autocomplete="email"
                   placeholder="ex.: maria@gmail.com" data-summary-target="email">
            @error('email')<span class="checkout-field__error" role="alert">{{ $message }}</span>@enderror
        </div>

        <div class="checkout-field">
            <label class="checkout-field__label" for="checkout-cpf-cnpj">CPF</label>
            <input id="checkout-cpf-cnpj" name="cpf_cnpj" type="text" value="{{ $valueCpfCnpj }}"
                   class="checkout-field__input @error('cpf_cnpj') checkout-field__input--invalid @enderror"
                   required inputmode="numeric" data-mask="cpf_cnpj"
                   data-summary-target="cpf_cnpj" placeholder="000.000.000-00">
            @error('cpf_cnpj')<span class="checkout-field__error" role="alert">{{ $message }}</span>@enderror
        </div>

        <div class="checkout-field">
            <label class="checkout-field__label" for="checkout-phone">Celular / WhatsApp</label>
            <div class="checkout-phone">
                <span class="checkout-phone__country">
                    <span class="checkout-phone__country-current">
                        <img src="{{ asset('checkout-assets/img/br.svg') }}" alt="Brasil"
                             width="24" height="16" data-country-flag>
                        <span data-country-dial>+55</span>
                    </span>
                    <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none">
                        <path d="m6 9 6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <select name="country_code" class="checkout-phone__country-select"
                            data-country-select aria-label="País">
                        @foreach ($countries as [$code, $country, $dial])
                            <option value="{{ $code }}"
                                    data-country-name="{{ $country }}"
                                    data-country-dial="{{ $dial }}"
                                    data-country-flag="{{ strtolower($code) }}"
                                    @selected($code === 'BR')>{{ $country }}{{ $dial }}</option>
                        @endforeach
                    </select>
                </span>
                <input id="checkout-phone" name="phone" type="tel" value="{{ $valuePhone }}"
                       class="checkout-field__input @error('phone') checkout-field__input--invalid @enderror"
                       required autocomplete="tel" inputmode="numeric" data-mask="phone"
                       data-summary-target="phone" placeholder="(00) 00000-0000">
            </div>
            @error('phone')<span class="checkout-field__error" role="alert">{{ $message }}</span>@enderror
        </div>

        <button type="button" class="checkout-step__continue" data-step-continue>
            <span>Ir para Entrega</span>
        </button>
    </div>
</section>
