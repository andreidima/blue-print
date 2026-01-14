<?php

namespace App\Enums;

enum StatusPlata: string
{
    case Neplatit = 'neplatit';
    case Partial = 'partial';
    case Platit = 'platit';

    public function label(): string
    {
        return match ($this) {
            self::Neplatit => 'Neplatit',
            self::Partial => 'Partial',
            self::Platit => 'Platit',
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
