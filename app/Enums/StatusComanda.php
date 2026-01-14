<?php

namespace App\Enums;

enum StatusComanda: string
{
    case Nou = 'nou';
    case InVerificare = 'in_verificare';
    case OfertaInPregatire = 'oferta_in_pregatire';
    case OfertaTrimisa = 'oferta_trimisa';
    case OfertaAcceptata = 'oferta_acceptata';
    case OfertaRespinsa = 'oferta_respinsa';
    case InGrafica = 'in_grafica';
    case MockupTrimis = 'mockup_trimis';
    case MockupAprobat = 'mockup_aprobat';
    case MockupRespins = 'mockup_respins';
    case InExecutie = 'in_executie';
    case Finalizat = 'finalizat';
    case Livrat = 'livrat';
    case Anulat = 'anulat';

    public function label(): string
    {
        return match ($this) {
            self::Nou => 'Nou',
            self::InVerificare => 'In verificare',
            self::OfertaInPregatire => 'Oferta in pregatire',
            self::OfertaTrimisa => 'Oferta trimisa',
            self::OfertaAcceptata => 'Oferta acceptata',
            self::OfertaRespinsa => 'Oferta respinsa',
            self::InGrafica => 'In grafica',
            self::MockupTrimis => 'Mockup trimis',
            self::MockupAprobat => 'Mockup aprobat',
            self::MockupRespins => 'Mockup respins',
            self::InExecutie => 'In executie',
            self::Finalizat => 'Finalizat',
            self::Livrat => 'Livrat',
            self::Anulat => 'Anulat',
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

    public static function finalStates(): array
    {
        return [
            self::Finalizat->value,
            self::Livrat->value,
            self::Anulat->value,
            self::OfertaRespinsa->value,
        ];
    }
}
