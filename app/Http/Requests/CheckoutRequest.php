<?php

namespace App\Http\Requests;

use App\Models\CheckoutSetting;
use App\Rules\ValidCpfCnpj;
use App\Rules\ValidLuhn;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    /**
     * Métodos de pagamento que disparam validação de cartão.
     */
    protected const CARD_METHODS = ['credit_card', 'debit_card'];

    /**
     * Campos que NÃO são reflashados em session quando a validação falha.
     *
     * O Laravel default só protege password/password_confirmation. Aqui
     * estendemos pra incluir os campos de cartão — sem isso, um erro de
     * validação dispararia redirect()->back()->withInput() que joga
     * TODOS os inputs no session flash, fazendo PAN e CVV persistirem
     * em disco (arquivo de sessão ou tabela sessions) por minutos.
     *
     * O usuário precisa redigitar os dados do cartão no retry — é o
     * comportamento correto pra PCI DSS, não é UX ruim.
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
        'card_number',
        'card_cvv',
        'card_holder_name',
        'card_expiry_month',
        'card_expiry_year',
    ];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Sanitiza dados ANTES da validação para que as rules vejam input já normalizado.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'email'       => mb_strtolower(trim((string) $this->input('email', ''))),
            'cpf_cnpj'    => preg_replace('/\D/', '', (string) $this->input('cpf_cnpj', '')),
            'zip'         => preg_replace('/\D/', '', (string) $this->input('zip', '')),
            'phone'       => preg_replace('/\D/', '', (string) $this->input('phone', '')),
            'state'       => strtoupper((string) $this->input('state', '')),
            'card_number' => preg_replace('/\D/', '', (string) $this->input('card_number', '')),
            'card_cvv'    => preg_replace('/\D/', '', (string) $this->input('card_cvv', '')),
        ]);
    }

    public function rules(): array
    {
        $settings = CheckoutSetting::current();
        $maxInstallments = $settings->installments_max;
        $cardRequired    = 'required_if:payment_method,' . implode(',', self::CARD_METHODS);
        $paymentMethods = array_values(array_filter([
            $settings->pix_enabled ? 'pix' : null,
            $settings->credit_card_enabled ? 'credit_card' : null,
        ]));

        return [
            // -- Identificação
            'name'           => ['required', 'string', 'min:3', 'max:255'],
            'email'          => ['required', 'email:rfc', 'max:255'],
            'phone'          => ['required', 'string', 'min:10', 'max:20'],
            'cpf_cnpj'       => ['required', 'string', new ValidCpfCnpj()],

            // -- Endereço
            'zip'            => ['required', 'string', 'size:8'],
            'street'         => ['required', 'string', 'max:255'],
            'number'         => ['required', 'string', 'max:20'],
            'complement'     => ['nullable', 'string', 'max:255'],
            'neighborhood'   => ['required', 'string', 'max:255'],
            'city'           => ['required', 'string', 'max:255'],
            'state'          => ['required', 'string', 'size:2'],
            'shipping_method' => ['required', 'in:jadlog,sedex,full_free'],

            // -- Pagamento
            'payment_method' => ['required', Rule::in($paymentMethods)],
            'customer_note'  => ['nullable', 'string', 'max:1000'],

            // -- Cartão (condicionais)
            'card_number'       => [$cardRequired, 'string', new ValidLuhn()],
            'card_holder_name'  => [$cardRequired, 'string', 'min:3', 'max:100'],
            'card_expiry_month' => [$cardRequired, 'string', 'regex:/^(0[1-9]|1[0-2])$/'],
            'card_expiry_year'  => [$cardRequired, 'string', 'regex:/^[0-9]{4}$/'],
            'card_cvv'          => [$cardRequired, 'string', 'digits_between:3,4'],
            'installments'      => ['nullable', 'integer', 'min:1', "max:{$maxInstallments}"],

            // -- Device data (3DS) — opcionais
            'device_language'        => ['nullable', 'string', 'max:10'],
            'device_color_depth'     => ['nullable', 'integer'],
            'device_screen_height'   => ['nullable', 'integer'],
            'device_screen_width'    => ['nullable', 'integer'],
            'device_time_difference' => ['nullable', 'integer'],
            'device_java_enabled'    => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'                 => 'Informe seu nome completo.',
            'name.min'                      => 'O nome deve ter pelo menos 3 caracteres.',
            'email.required'                => 'Informe seu e-mail.',
            'email.email'                   => 'Informe um e-mail válido.',
            'phone.required'                => 'Informe seu telefone.',
            'phone.min'                     => 'Telefone inválido.',
            'cpf_cnpj.required'             => 'Informe seu CPF ou CNPJ.',
            'zip.required'                  => 'Informe o CEP.',
            'zip.size'                      => 'O CEP deve ter 8 dígitos.',
            'street.required'               => 'Informe a rua.',
            'number.required'               => 'Informe o número do endereço.',
            'neighborhood.required'         => 'Informe o bairro.',
            'city.required'                 => 'Informe a cidade.',
            'state.required'                => 'Informe o estado (UF).',
            'state.size'                    => 'O estado deve ter 2 letras (UF).',
            'shipping_method.required'      => 'Escolha uma forma de entrega.',
            'shipping_method.in'            => 'Forma de entrega inválida.',
            'payment_method.required'       => 'Escolha uma forma de pagamento.',
            'payment_method.in'             => 'Forma de pagamento inválida.',
            'card_number.required_if'       => 'Informe o número do cartão.',
            'card_holder_name.required_if'  => 'Informe o nome impresso no cartão.',
            'card_expiry_month.required_if' => 'Informe o mês de validade.',
            'card_expiry_month.regex'       => 'Mês de validade inválido (use 01–12).',
            'card_expiry_year.required_if'  => 'Informe o ano de validade.',
            'card_expiry_year.regex'        => 'Ano de validade inválido (use 4 dígitos).',
            'card_cvv.required_if'          => 'Informe o código de segurança.',
            'card_cvv.digits_between'       => 'O CVV deve ter 3 ou 4 dígitos.',
            'installments.max'              => 'Número de parcelas acima do permitido.',
        ];
    }

    /**
     * Validação cruzada: data de expiração no passado.
     * Roda só após as regras básicas passarem, pra não duplicar erros
     * quando month/year vierem malformados.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (!$this->isCardPayment()) {
                return;
            }

            // Pula se month ou year já falharam nas regras estruturais.
            if ($validator->errors()->hasAny(['card_expiry_month', 'card_expiry_year'])) {
                return;
            }

            $month = $this->input('card_expiry_month');
            $year  = $this->input('card_expiry_year');

            if (!$month || !$year) {
                return;
            }

            try {
                $expiry = Carbon::createFromDate((int) $year, (int) $month, 1)->endOfMonth();
            } catch (\Throwable $e) {
                $validator->errors()->add('card_expiry_year', 'Data de validade inválida.');
                return;
            }

            if ($expiry->isPast()) {
                $validator->errors()->add('card_expiry_year', 'Cartão expirado.');
            }
        });
    }

    // =========================================================================
    // Helpers consumidos pelo CheckoutController
    // =========================================================================

    public function isCardPayment(): bool
    {
        return in_array($this->input('payment_method'), self::CARD_METHODS, true);
    }

    /*public function cCardData(): array
    {
        return [
            'number'       => $this->input('card_number'),
            'holder_name'  => $this->input('card_holder_name'),
            'expiry_month' => $this->input('card_expiry_month'),
            'expiry_year'  => $this->input('card_expiry_year'),
            'cvv'          => $this->input('card_cvv'),
        ];
    }*/

    public function cardData(): array
    {
        return [
            'number'       => $this->input('card_number'),
            'holder_name'  => $this->input('card_holder_name'),
            'expiry_month' => $this->input('card_expiry_month'),
            'expiry_year'  => $this->input('card_expiry_year'),
            'cvv'          => $this->input('card_cvv'),
            'installments' => $this->integer('installments') ?: 1,
        ];
    }

    public function deviceData(): array
    {
        return [
            'browser_language'   => $this->input('device_language'),
            'color_depth'        => $this->integer('device_color_depth') ?: null,
            'screen_height'      => $this->integer('device_screen_height') ?: null,
            'screen_width'       => $this->integer('device_screen_width') ?: null,
            'time_difference'    => $this->integer('device_time_difference') ?: null,
            'java_enabled'       => (bool) $this->input('device_java_enabled'),
            'javascript_enabled' => true,
            'user_agent'         => $this->userAgent(),
            'ip_address'         => $this->ip(),
        ];
    }

    public function addressData(): array
    {
        return [
            'address_type' => 'shipping',
            'zipcode'      => $this->input('zip'),
            'street'       => $this->input('street'),
            'number'       => $this->input('number'),
            'complement'   => $this->input('complement'),
            'neighborhood' => $this->input('neighborhood'),
            'city'         => $this->input('city'),
            'state'        => $this->input('state'),
            'country'      => 'BR',
            'is_default'   => false,
        ];
    }

    public function customerPayload(): array
    {
        return [
            'name'     => $this->input('name'),
            'email'    => $this->input('email'),
            'phone'    => $this->input('phone'),
            'cpf_cnpj' => $this->input('cpf_cnpj'),
        ];
    }
}
