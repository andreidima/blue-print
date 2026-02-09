<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $templates = [
            [
                'key' => 'cerere_oferta_preluata',
                'name' => 'Cerere de oferta preluata',
                'body' => "Buna ziua!\nCererea dvs. de oferta a fost preluata si are nr. {comanda_id}.\nRevenim in curand.\nMultumim ca ati ales Tipografia blu.e-print!\nwww.blue-print.ro",
            ],
            [
                'key' => 'oferta_pret_trimisa',
                'name' => 'Oferta de pret trimisa',
                'body' => "Buna ziua!\nOferta solicitata a fost trimisa si are nr. {comanda_id}.\nAsteptam cu interes raspunsul dvs.\nMultumim!\nTipografia blu.e-print\nwww.blue-print.ro",
            ],
            [
                'key' => 'comanda_preluata',
                'name' => 'Comanda preluata',
                'body' => "Buna ziua!\nComanda nr. {comanda_id} a fost preluata.\nRevenim cu informatii.\nMultumim ca ati ales Tipografia blu.e-print!",
            ],
            [
                'key' => 'info_comanda_preluata',
                'name' => 'INFO comanda preluata',
                'body' => "Buna ziua!\nINFO\nNumar : {comanda_id} / {data}\nStatus : {status}\nValoare : {total} lei\nTermen finalizare estimat: {livrare}\nTipografia blu.e-print",
            ],
            [
                'key' => 'info_comanda_intarziata_tehnic',
                'name' => 'INFO comanda intarziata motive tehnice',
                'body' => "Buna ziua!\nDin motive tehnice, termenul comenzii a fost depasit.\nNe cerem scuze si va multumim pentru intelegere.\nTipografia blu.e-print",
            ],
            [
                'key' => 'info_comanda_intarziata_lipsa_raspuns',
                'name' => 'INFO comanda intarziata lipsa raspuns',
                'body' => "Buna ziua!\nNU uita sa ne confirmi grafica/mock-up/TEST/BUN DE TIPAR.\nTermenul de finalizare a comenzii a fost decalat.\nTipografia blu.e-print",
            ],
            [
                'key' => 'info_comanda_finalizata',
                'name' => 'INFO comanda finalizata',
                'body' => "Buna ziua!\nComanda dvs. a fost finalizata.\nNumar : {comanda_id} / {data}\nRest plata : {rest_plata} lei\nTipografia blu.e-print",
            ],
            [
                'key' => 'info_comanda_ridicare',
                'name' => 'INFO comanda ridicare de la sediu',
                'body' => "Buna ziua!\nComanda dvs. se poate ridica de la sediul tipografiei.\nNC : {comanda_id} / {data}\nMultumim ca ati ales Tipografia blu.e-print!",
            ],
            [
                'key' => 'info_comanda_expediata',
                'name' => 'INFO comanda expediata prin curierat',
                'body' => "Buna ziua!\nComanda dvs. a fost expediata.\nNC : {comanda_id} / {data}\nLivrator : {livrator}\nAWB : {awb}\nTipografia blu.e-print",
            ],
            [
                'key' => 'info_comanda_grafica',
                'name' => 'INFO comanda grafica',
                'body' => "Buna ziua!\nGrafica comenzii dvs. a fost finalizata.\nNumar comanda : {comanda_id} / {data}\nVa rugam confirmati grafica.\nTipografia blu.e-print",
            ],
            [
                'key' => 'info_comanda_mockup',
                'name' => 'INFO comanda mock-up',
                'body' => "Buna ziua!\nMockup-ul comenzii dvs. a fost finalizat.\nNumar comanda : {comanda_id} / {data}\nVa rugam confirmati mockup-ul.\nTipografia blu.e-print",
            ],
            [
                'key' => 'info_comanda_test',
                'name' => 'INFO comanda exemplar TEST',
                'body' => "Buna ziua!\nExemplarul de TEST este gata si poate fi ridicat sau livrat.\nAsteptam parerea dvs.!\nNumar comanda : {comanda_id} / {data}\nTipografia blu.e-print",
            ],
            [
                'key' => 'info_comanda_test_ridicare',
                'name' => 'INFO comanda exemplar TEST - ridicare de la sediu',
                'body' => "Buna ziua!\nExemplarul de TEST se poate ridica de la sediul tipografiei.\nNC : {comanda_id} / {data}\nMultumim ca ati ales Tipografia blu.e-print!",
            ],
            [
                'key' => 'info_comanda_test_expediata',
                'name' => 'INFO comanda exemplar TEST - expediata prin curierat',
                'body' => "Buna ziua!\nExemplarul de TEST a fost expediat.\nNC : {comanda_id} / {data}\nLivrator : {livrator}\nAWB : {awb}\nTipografia blu.e-print",
            ],
            [
                'key' => 'info_bun_de_tipar',
                'name' => 'INFO BUN DE TIPAR',
                'body' => "Buna ziua!\nVa rugam sa ne confirmati BUNUL DE TIPAR pentru a continua cu executia comenzii.\nNumar comanda : {comanda_id} / {data}\nTipografia blu.e-print",
            ],
        ];

        foreach ($templates as $template) {
            DB::table('sms_templates')->updateOrInsert(
                ['key' => $template['key']],
                [
                    'name' => $template['name'],
                    'body' => $template['body'],
                    'active' => true,
                    'color' => '#0d6efd',
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        $keys = [
            'cerere_oferta_preluata',
            'oferta_pret_trimisa',
            'comanda_preluata',
            'info_comanda_preluata',
            'info_comanda_intarziata_tehnic',
            'info_comanda_intarziata_lipsa_raspuns',
            'info_comanda_finalizata',
            'info_comanda_ridicare',
            'info_comanda_expediata',
            'info_comanda_grafica',
            'info_comanda_mockup',
            'info_comanda_test',
            'info_comanda_test_ridicare',
            'info_comanda_test_expediata',
            'info_bun_de_tipar',
        ];

        DB::table('sms_templates')->whereIn('key', $keys)->delete();
    }
};
