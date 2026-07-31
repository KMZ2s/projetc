<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidCpfCnpj implements ValidationRule
{
    /**
     * Valida um CPF (11 dígitos) ou CNPJ (14 dígitos) usando o algoritmo Mod-11.
     *
     * - Sanitiza pontuação antes de validar — aceita "123.456.789-00" ou "12345678900".
     * - Detecta o tipo pela quantidade de dígitos após sanitização.
     * - Rejeita números com todos os dígitos iguais (ex: "111.111.111-11"), que
     *   passam no Mod-11 matematicamente mas são reconhecidamente inválidos.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $raw    = is_scalar($value) ? (string) $value : '';
        $digits = preg_replace('/\D/', '', $raw);

        $valid = match (strlen($digits)) {
            11      => $this->validateCpf($digits),
            14      => $this->validateCnpj($digits),
            default => false,
        };

        if (!$valid) {
            $fail('O CPF/CNPJ informado é inválido.');
        }
    }

    private function validateCpf(string $cpf): bool
    {
        // Rejeita "00000000000", "11111111111", etc.
        if (preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        // 1º dígito verificador (pesos 10..2 sobre os 9 primeiros dígitos)
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += (int) $cpf[$i] * (10 - $i);
        }
        $d1 = ($sum * 10) % 11;
        $d1 = $d1 >= 10 ? 0 : $d1;

        if ((int) $cpf[9] !== $d1) {
            return false;
        }

        // 2º dígito verificador (pesos 11..2 sobre os 10 primeiros dígitos)
        $sum = 0;
        for ($i = 0; $i < 10; $i++) {
            $sum += (int) $cpf[$i] * (11 - $i);
        }
        $d2 = ($sum * 10) % 11;
        $d2 = $d2 >= 10 ? 0 : $d2;

        return (int) $cpf[10] === $d2;
    }

    private function validateCnpj(string $cnpj): bool
    {
        // Rejeita "00000000000000", "11111111111111", etc.
        if (preg_match('/^(\d)\1{13}$/', $cnpj)) {
            return false;
        }

        // 1º dígito verificador
        $weights1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += (int) $cnpj[$i] * $weights1[$i];
        }
        $rest = $sum % 11;
        $d1   = $rest < 2 ? 0 : 11 - $rest;

        if ((int) $cnpj[12] !== $d1) {
            return false;
        }

        // 2º dígito verificador
        $weights2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $sum = 0;
        for ($i = 0; $i < 13; $i++) {
            $sum += (int) $cnpj[$i] * $weights2[$i];
        }
        $rest = $sum % 11;
        $d2   = $rest < 2 ? 0 : 11 - $rest;

        return (int) $cnpj[13] === $d2;
    }
}