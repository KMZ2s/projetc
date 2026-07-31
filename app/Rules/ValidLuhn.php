<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidLuhn implements ValidationRule
{
    /**
     * Valida um número de cartão usando o algoritmo Luhn (Mod-10).
     *
     * IMPORTANTE: o algoritmo Luhn valida APENAS o formato/checksum do número.
     * NÃO valida se o cartão existe, está ativo, dentro do prazo de validade,
     * tem saldo, ou foi reportado como roubado/bloqueado. Essa autorização real
     * é responsabilidade do gateway (BlackcatPay) e só vem na resposta de
     * createCardPayment().
     *
     * Esta rule existe para evitar typos óbvios e impedir que números obviamente
     * inválidos cheguem ao gateway, melhorando UX.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $raw    = is_scalar($value) ? (string) $value : '';
        $digits = preg_replace('/\D/', '', $raw);
        $length = strlen($digits);

        // Range real de cartões: Maestro (13) até alguns proprietários (19).
        if ($length < 13 || $length > 19) {
            $fail('O número do cartão informado é inválido.');
            return;
        }

        if (!$this->luhn($digits)) {
            $fail('O número do cartão informado é inválido.');
        }
    }

    private function luhn(string $digits): bool
    {
        $sum    = 0;
        $length = strlen($digits);

        // Da direita pra esquerda: dobra dígitos em posições alternadas (i ímpar)
        for ($i = 0; $i < $length; $i++) {
            $digit = (int) $digits[$length - 1 - $i];

            if ($i % 2 === 1) {
                $digit *= 2;
                if ($digit > 9) {
                    // Equivalente a somar os dígitos: 16 → 1+6=7, e 16-9=7
                    $digit -= 9;
                }
            }

            $sum += $digit;
        }

        return $sum % 10 === 0;
    }
}