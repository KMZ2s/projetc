<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    public function create(array $input): User
    {
        Validator::make($input, [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name'  => ['nullable', 'string', 'max:100'],
            'email'      => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            'password'   => $this->passwordRules(),
        ], [
            'first_name.required' => 'O nome é obrigatório.',
            'email.unique'        => 'Este e-mail já está cadastrado.',
        ])->validate();

        $firstName = trim($input['first_name']);
        $lastName  = trim($input['last_name'] ?? '');
        $fullName  = $lastName ? "{$firstName} {$lastName}" : $firstName;

        return User::create([
            'name'       => $fullName,
            'first_name' => $firstName,
            'last_name'  => $lastName ?: null,
            'email'      => $input['email'],
            'password'   => $input['password'],
            'is_admin'   => false,
            'status'     => 'active',
        ]);
    }
}