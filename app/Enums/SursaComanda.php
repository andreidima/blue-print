<?php

namespace App\Enums;

enum SursaComanda: string
{
    case Fizic = 'fizic';
    case Email = 'email';
    case Whatsapp = 'whatsapp';
    case Website = 'website';
    case ApelTelefonic = 'apel_telefonic';

    public function label(): string
    {
        return match ($this) {
            self::Fizic => 'Fizic',
            self::Email => 'Email',
            self::Whatsapp => 'WhatsApp',
            self::Website => 'Website',
            self::ApelTelefonic => 'Apel telefonic',
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
