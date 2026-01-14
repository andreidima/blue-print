<?php

namespace App\Enums;

enum TipComanda: string
{
    case ComandaFerma = 'comanda_ferma';
    case CerereOferta = 'cerere_oferta';

    public function label(): string
    {
        return match ($this) {
            self::ComandaFerma => 'Comanda ferma',
            self::CerereOferta => 'Cerere oferta',
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
