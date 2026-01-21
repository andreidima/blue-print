<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class CnpRule implements ValidationRule
{
    public function __construct(private bool $enabled = true)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!$this->enabled) {
            return;
        }

        if ($value === null || $value === '') {
            return;
        }

        $cnp = (string) $value;

        if (!preg_match('/^\d{13}$/', $cnp)) {
            $fail('CNP trebuie sa contina exact 13 cifre.');
            return;
        }

        $weights = [2, 7, 9, 1, 4, 6, 3, 5, 8, 2, 7, 9];
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += ((int) $cnp[$i]) * $weights[$i];
        }

        $control = $sum % 11;
        if ($control === 10) {
            $control = 1;
        }

        if ((int) $cnp[12] !== $control) {
            $fail('CNP invalid.');
        }
    }
}

