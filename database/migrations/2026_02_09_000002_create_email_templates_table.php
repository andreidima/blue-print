<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->string('name', 150);
            $table->string('subject', 255);
            $table->text('body_html');
            $table->string('color', 20)->default('#0d6efd');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        $now = now();
        $templates = [
            'cerere_oferta_preluata' => [
                'name' => 'Cerere de oferta preluata',
                'subject' => 'Confirmare preluare Cerere de oferta - {produse}',
                'body_html' => trim(<<<'HTML'
<p>Buna ziua, {client}</p>
<p>Va multumim pentru interesul acordat produselor si serviciilor noastre. Cererea dumneavoastra a fost preluata si va fi procesata in cel mai scurt timp posibil.</p>
<p>Numar cerere de oferta : {comanda_id}<br>Data : {data}<br>Oferta este valabila pana la {valabil_pana}.</p>
HTML
                ),
            ],
            'oferta_trimisa' => [
                'name' => 'Oferta de pret trimisa',
                'subject' => 'Oferta de pret - {produse}',
                'body_html' => trim(<<<'HTML'
<p>Buna ziua, {client}</p>
<p>Va multumim pentru interesul acordat produselor si serviciilor noastre. In urma cererii dumneavoastra {comanda_id} din {data}, avem placerea sa va transmitem oferta de pret pentru produsele solicitate.</p>
<p>Numar oferta : {comanda_id}<br>Data : {data}<br>Oferta este valabila pana la {valabil_pana}.<br>Pentru orice intrebari sau clarificari, ne puteti contacta la {telefon} sau {email}.</p>
<p>In speranta ca oferta prezinta interes, asteptam cu interes raspunsul dumneavoastra.</p>
HTML
                ),
            ],
            'comanda_preluata' => [
                'name' => 'Comanda preluata',
                'subject' => 'Confirmare preluare Comanda - {produse}',
                'body_html' => trim(<<<'HTML'
<p>Buna ziua, {client}</p>
<p>Va confirmam ca am preluat comanda dumneavoastra, iar aceasta este in curs de procesare. Revenim in cel mai scurt timp cu detalii.</p>
<p>Numar comanda : {comanda_id}<br>Data : {data}</p>
HTML
                ),
            ],
            'info_comanda_preluata' => [
                'name' => 'INFO comanda preluata',
                'subject' => 'INFO Comanda - {produse}',
                'body_html' => trim(<<<'HTML'
<p>Buna ziua, {client}</p>
<p>Comanda dumneavoastra a fost procesata si este data in lucru.</p>
<p>Numar comanda : {comanda_id} / {data}<br>Status : {status}<br>Valoare : {total} lei<br>Termen finalizare estimat : {livrare}</p>
<ul>
    <li>Va rugam respectuos sa verificati informarile transmise pe cale electronica (mail, SMS) in vederea confirmarii fiecarei etape de realizare a produselor: INFO grafica / INFO mock-up / INFO TEST / INFO BUN DE TIPAR (dupa caz). In lipsa confirmarii acestor etape produsele dumneavoastra nu vor fi date in productie.</li>
    <li>Verificati sectiunea SPAM din casuta de mail in cazul in care nu ati primit informarea.</li>
    <li>Confirmarile se vor realiza doar in scris pe mail sau pe numarul de WhatsApp a tipografiei. In vederea evitarii unor situatii neplacute nu se accepta confirmari telefonice.</li>
    <li>Termenul de finalizare estimat poate suferi modificari in cazul in care nu se primesc la timp raspunsurile dumneavoastra la informarile trimise.</li>
</ul>
HTML
                ),
            ],
            'info_comanda_intarziata' => [
                'name' => 'INFO comanda intarziata motive tehnice',
                'subject' => 'INFO Comanda intarziata - {produse}',
                'body_html' => trim(<<<'HTML'
<p>Buna ziua, {client}</p>
<p>Va informam ca termenul de executie al comenzii dvs. a fost depasit din motive tehnice, iar finalizarea executiei acesteia va intarzia. Ne cerem scuze pentru inconvenient si va multumim pentru intelegere. Pentru orice intrebari va rugam sa ne contactati.</p>
<p>Numar comanda : {comanda_id} / {data}<br>Status : {status}<br>Termen finalizare estimat : {livrare}</p>
HTML
                ),
            ],
            'info_comanda_intarziata_lipsa_raspuns' => [
                'name' => 'INFO comanda intarziata lipsa raspuns',
                'subject' => 'INFO Comanda - AI UITAT? - {produse}',
                'body_html' => trim(<<<'HTML'
<p>Buna ziua, {client}</p>
<p>Dorim sa te informam ca nu ai raspuns la mail-ul anterior prin care ti-am solicitat acceptul la grafica / mock-up / TEST / BUN DE TIPAR.</p>
<p>Pentru a continua executia comenzii tale te rugam sa ne comunici acceptul tau.</p>
<p>Numar comanda : {comanda_id} / {data}<br>Status : {status}<br>Termen finalizare estimat : {livrare}</p>
HTML
                ),
            ],
            'info_comanda_finalizata' => [
                'name' => 'INFO comanda finalizata',
                'subject' => 'INFO Comanda FINALIZATA - {produse}',
                'body_html' => trim(<<<'HTML'
<p>Buna ziua, {client}</p>
<p>Comanda dumneavoastra este finalizata.</p>
<p>Numar comanda : {comanda_id} / {data}<br>Status : {status}<br>Valoare : {total} lei<br>Rest de plata : {rest_plata} lei</p>
<p>Draga {client},</p>
<p>Iti multumim din suflet ca ai ales blu.e-print! Ne-am bucurat foarte mult sa te avem ca si client si ne dorim sa stim daca ai fost multumit de serviciile noastre.</p>
<p>Daca experienta ta a fost pe masura asteptarilor, te rugam sa ne lasi o recenzie pe Google sau pe pagina noastra web, pentru ca si alti clienti sa descopere cum putem sa-i ajutam. O recomandare din partea ta ne-ar incuraja foarte mult si ar contribui la imbunatatirea constanta a serviciilor noastre.</p>
<p>Daca totul a fost la superlativ pentru tine, nu ezita sa ne oferi 5 stele, astfel incat sa ne ajutam reciproc sa evoluam si sa oferim o experienta tot mai buna.</p>
<p>Click pe link-ul de mai jos pentru a lasa recenzia:</p>
<p><a href="{review_link}">{review_link}</a></p>
<p>Iti multumim inca o data pentru increderea acordata! Asteptam cu nerabdare sa te avem din nou printre clientii nostri.</p>
<p>Tipografia blu.e-print!</p>
HTML
                ),
            ],
            'info_comanda_ridicare_sediu' => [
                'name' => 'INFO comanda ridicare de la sediu',
                'subject' => 'INFO Comanda se preia de la sediu - {produse}',
                'body_html' => trim(<<<'HTML'
<p>Buna ziua, {client}</p>
<p>Comanda dumneavoastra se poate ridica de la sediul tipografiei.</p>
<p>Numar comanda : {comanda_id} / {data}</p>
<p>Multumim ca ati ales Tipografia blu.e-print!</p>
HTML
                ),
            ],
            'info_comanda_expediata' => [
                'name' => 'INFO comanda expediata prin curierat',
                'subject' => 'INFO Comanda expediata - {produse}',
                'body_html' => trim(<<<'HTML'
<p>Buna ziua, {client}</p>
<p>Comanda dumneavoastra a fost expediata.</p>
<p>Numar comanda : {comanda_id} / {data}<br>Livrator : {livrator}<br>AWB : {awb}</p>
<p>Multumim ca ati ales Tipografia blu.e-print!</p>
HTML
                ),
            ],
            'info_comanda_grafica' => [
                'name' => 'INFO comanda grafica',
                'subject' => 'INFO Comanda - GRAFICA FINALIZATA - {produse}',
                'body_html' => trim(<<<'HTML'
<p>Buna ziua, {client}</p>
<p>Grafica aferenta produselor dumneavoastra este finalizata. Te rugam sa ne confirmi daca aceasta este conform dorintei dumneavoastra sau daca necesita ajustari.</p>
<p>Pentru a continua executia comenzii tale este necesar acceptul tau.</p>
<p>Numar comanda : {comanda_id} / {data}<br>Status : {status}<br>Termen finalizare estimat : {livrare}</p>
HTML
                ),
            ],
            'info_comanda_mockup' => [
                'name' => 'INFO comanda mock-up',
                'subject' => 'INFO Comanda - MOCK-UP FINALIZAT - {produse}',
                'body_html' => trim(<<<'HTML'
<p>Buna ziua, {client}</p>
<p>Mockup-ul aferent produselor dumneavoastra este finalizat. Te rugam sa ne confirmi daca acesta este conform dorintei dumneavoastra sau daca necesita ajustari.</p>
<p>Pentru a continua executia comenzii tale este necesar acceptul tau.</p>
<p>Numar comanda : {comanda_id} / {data}<br>Status : {status}<br>Termen finalizare estimat : {livrare}</p>
HTML
                ),
            ],
            'info_comanda_test' => [
                'name' => 'INFO comanda exemplar TEST',
                'subject' => 'INFO Comanda - EXEMPLAR DE TEST FINALIZAT - {produse}',
                'body_html' => trim(<<<'HTML'
<p>Buna ziua, {client}</p>
<p>Exemplarul de TEST a produselor dumneavoastra este finalizat. Te rugam sa ne confirmi daca acesta este conform dorintei dumneavoastra sau daca necesita ajustari.</p>
<p>Pentru a continua executia comenzii tale este necesar acceptul tau.</p>
<p>Numar comanda : {comanda_id} / {data}<br>Status : {status}<br>Termen finalizare estimat : {livrare}</p>
HTML
                ),
            ],
            'info_comanda_test_ridicare' => [
                'name' => 'INFO comanda exemplar TEST - ridicare de la sediu',
                'subject' => 'INFO Comanda - EXEMPLARUL DE TEST se preia de la sediu - {produse}',
                'body_html' => trim(<<<'HTML'
<p>Buna ziua, {client}</p>
<p>Exemplarul de TEST aferent comenzii dumneavoastra se poate ridica de la sediul tipografiei.</p>
<p>Numar comanda : {comanda_id} / {data}</p>
<p>Multumim ca ati ales Tipografia blu.e-print!</p>
HTML
                ),
            ],
            'info_comanda_test_expediata' => [
                'name' => 'INFO comanda exemplar TEST - expediata prin curierat',
                'subject' => 'INFO Comanda - EXEMPLAR DE TEST expediat - {produse}',
                'body_html' => trim(<<<'HTML'
<p>Buna ziua, {client}</p>
<p>Exemplarul de TEST aferent comenzii dumneavoastra a fost expediat.</p>
<p>Numar comanda : {comanda_id} / {data}<br>Livrator : {livrator}<br>AWB : {awb}</p>
<p>Multumim ca ati ales Tipografia blu.e-print!</p>
HTML
                ),
            ],
            'info_bun_de_tipar' => [
                'name' => 'INFO BUN DE TIPAR',
                'subject' => 'INFO Comanda - CONFIRMARE BUN DE TIPAR - {produse}',
                'body_html' => trim(<<<'HTML'
<p>Buna ziua, {client}</p>
<p>Va rugam sa ne confirmati BUNUL DE TIPAR pentru produsele aferente comenzii dumneavoastra.</p>
<p>Pentru a continua executia comenzii tale este necesar acceptul tau.</p>
<p><strong>Atentionare privind acceptul BUN DE TIPAR</strong></p>
<p>Prin acordarea acceptului BUN DE TIPAR, clientul declara ca a verificat si aprobat forma finala a materialelor grafice, incluzand, fara a se limita la: continutul textelor, elementele grafice, dimensiunile, culorile si pozitionarea acestora.</p>
<p>Acceptul BUN DE TIPAR reprezinta acordul expres pentru intrarea materialelor in productie. In acest moment, responsabilitatea pentru corectitudinea graficii revine in totalitate clientului, iar tipografia este exonerata de orice raspundere pentru eventualele erori existente in materialele aprobate.</p>
<p>Orice modificari solicitate ulterior acordarii acceptului BUN DE TIPAR vor fi considerate comenzi suplimentare si vor fi realizate exclusiv pe cheltuiala clientului, putand implica costuri si termene de livrare aditionale.</p>
<p>Va rugam sa verificati cu maxima atentie materialele inainte de confirmarea finala.</p>
<p>Numar comanda : {comanda_id} / {data}<br>Status : {status}<br>Termen finalizare estimat : {livrare}</p>
HTML
                ),
            ],
        ];

        $rows = [];
        foreach ($templates as $key => $template) {
            $rows[] = [
                'key' => $key,
                'name' => $template['name'],
                'subject' => $template['subject'],
                'body_html' => $template['body_html'],
                'color' => $template['color'] ?? '#0d6efd',
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('email_templates')->insert($rows);
    }

    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }
};
