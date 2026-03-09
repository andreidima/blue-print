<?php

namespace App\Support;

use App\Models\Comanda;
use App\Models\ComandaGdprConsent;
use Barryvdh\DomPDF\Facade\Pdf;

class ComandaPdfFactory
{
    public static function oferta(Comanda $comanda)
    {
        $comanda->load(['client', 'produse.produs', 'solicitari.createdBy']);

        return Pdf::loadView('pdf.comenzi.oferta', [
            'comanda' => $comanda,
        ]);
    }

    public static function ofertaFilename(Comanda $comanda): string
    {
        return "oferta-comerciala-{$comanda->id}.pdf";
    }

    public static function gdpr(Comanda $comanda, ComandaGdprConsent $consent)
    {
        $comanda->load(['client']);

        return Pdf::loadView('pdf.comenzi.gdpr', [
            'comanda' => $comanda,
            'consent' => $consent,
        ]);
    }

    public static function gdprFilename(Comanda $comanda): string
    {
        return "gdpr-comanda-{$comanda->id}.pdf";
    }
}
