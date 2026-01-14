<?php

namespace App\Enums;

enum MetodaPlata: string
{
    case Cash = 'cash';
    case Card = 'card';
    case Transfer = 'transfer';
    case Altceva = 'altceva';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::Card => 'Card',
            self::Transfer => 'Transfer',
            self::Altceva => 'Altceva',
        };
    }

    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
