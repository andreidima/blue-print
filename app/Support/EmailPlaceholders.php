<?php

namespace App\Support;

use App\Enums\StatusComanda;
use App\Enums\SursaComanda;
use App\Enums\TipComanda;
use App\Models\Comanda;

class EmailPlaceholders
{
    public static function forComanda(Comanda $comanda): array
    {
        $client = $comanda->client;
        $tipuri = TipComanda::options();
        $surse = SursaComanda::options();
        $statusuri = StatusComanda::options();

        $produse = $comanda->produse
            ->map(function ($linie) {
                $nume = $linie->custom_denumire ?: optional($linie->produs)->denumire;
                $nume = trim((string) $nume);
                if ($nume === '') {
                    $nume = 'Produs';
                }

                $cantitate = (int) $linie->cantitate;
                if ($cantitate <= 0) {
                    $cantitate = 1;
                }

                return "{$nume} x {$cantitate}";
            })
            ->filter()
            ->values()
            ->implode(', ');
        if ($produse === '') {
            $produse = 'Produse tipografie';
        }

        $dataSolicitarii = $comanda->data_solicitarii?->format('d.m.Y') ?? '';
        $livrare = $comanda->timp_estimat_livrare?->format('d.m.Y') ?? '';
        $valabilPana = $comanda->data_solicitarii
            ? $comanda->data_solicitarii->copy()->addDays(30)->format('d.m.Y')
            : '';

        $total = (float) $comanda->total;
        $totalPlatit = (float) $comanda->total_platit;
        $restPlata = max(0, $total - $totalPlatit);

        return [
            '{app}' => config('app.name'),
            '{comanda_id}' => (string) $comanda->id,
            '{client}' => $client?->nume_complet ?? '',
            '{telefon}' => $client?->telefon ?? '',
            '{telefon_secundar}' => $client?->telefon_secundar ?? '',
            '{email}' => $client?->email ?? '',
            '{total}' => number_format($total, 2),
            '{total_platit}' => number_format($totalPlatit, 2),
            '{rest_plata}' => number_format($restPlata, 2),
            '{data}' => $dataSolicitarii,
            '{data_solicitarii}' => $dataSolicitarii,
            '{livrare}' => $livrare,
            '{status}' => $statusuri[$comanda->status] ?? $comanda->status,
            '{tip}' => $tipuri[$comanda->tip] ?? $comanda->tip,
            '{sursa}' => $surse[$comanda->sursa] ?? $comanda->sursa,
            '{awb}' => $comanda->awb ?? '',
            '{livrator}' => '',
            '{produse}' => $produse,
            '{valabil_pana}' => $valabilPana,
            '{review_link}' => '',
        ];
    }

    public static function reference(): array
    {
        return [
            [
                'token' => '{app}',
                'description' => 'Numele aplicatiei',
                'example' => config('app.name'),
            ],
            [
                'token' => '{comanda_id}',
                'description' => 'ID comanda',
                'example' => '000001',
            ],
            [
                'token' => '{client}',
                'description' => 'Numele clientului',
                'example' => 'Popescu Ion',
            ],
            [
                'token' => '{telefon}',
                'description' => 'Telefon client',
                'example' => '0722 123 456',
            ],
            [
                'token' => '{telefon_secundar}',
                'description' => 'Telefon secundar client',
                'example' => '0733 456 789',
            ],
            [
                'token' => '{email}',
                'description' => 'Email client',
                'example' => 'ion.popescu@example.com',
            ],
            [
                'token' => '{total}',
                'description' => 'Total comanda',
                'example' => '250.00',
            ],
            [
                'token' => '{rest_plata}',
                'description' => 'Rest de plata',
                'example' => '120.00',
            ],
            [
                'token' => '{data}',
                'description' => 'Data solicitarii',
                'example' => '01.12.2026',
            ],
            [
                'token' => '{livrare}',
                'description' => 'Termen finalizare estimat',
                'example' => '12.12.2026',
            ],
            [
                'token' => '{status}',
                'description' => 'Status comanda',
                'example' => 'In lucru',
            ],
            [
                'token' => '{tip}',
                'description' => 'Tip comanda',
                'example' => 'Tipar',
            ],
            [
                'token' => '{sursa}',
                'description' => 'Sursa comanda',
                'example' => 'Online',
            ],
            [
                'token' => '{awb}',
                'description' => 'AWB comanda',
                'example' => 'AWB123456789',
            ],
            [
                'token' => '{livrator}',
                'description' => 'Curier/Livrator',
                'example' => 'Fan Courier',
            ],
            [
                'token' => '{produse}',
                'description' => 'Lista produse',
                'example' => 'Flyere x 100, Afise x 50',
            ],
            [
                'token' => '{valabil_pana}',
                'description' => 'Data valabilitate oferta (30 zile)',
                'example' => '31.12.2026',
            ],
            [
                'token' => '{review_link}',
                'description' => 'Link review',
                'example' => 'https://g.page/r/example',
            ],
        ];
    }
}
