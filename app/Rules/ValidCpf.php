<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class ValidCpf implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $cpf = preg_replace('/\D/', '', (string) $value) ?? '';

        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf) === 1) {
            $fail('Informe um CPF válido.');

            return;
        }

        for ($digit = 9; $digit < 11; $digit++) {
            $sum = 0;

            for ($position = 0; $position < $digit; $position++) {
                $sum += ((int) $cpf[$position]) * (($digit + 1) - $position);
            }

            $verifier = (10 * $sum) % 11;
            $verifier = $verifier === 10 ? 0 : $verifier;

            if ((int) $cpf[$digit] !== $verifier) {
                $fail('Informe um CPF válido.');

                return;
            }
        }
    }
}
