<!doctype html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <title>Acord GDPR comandă #{{ $comanda->id }}</title>
    <style>
        @page { margin: 0; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #27358f;
            margin: 0;
            padding: 0;
        }
        .page {
            position: relative;
            width: 210mm;
            min-height: 297mm;
        }
        .page-bg {
            position: absolute;
            top: 0;
            left: 0;
            width: 210mm;
            height: 297mm;
            z-index: 0;
        }
        .content {
            position: relative;
            z-index: 1;
            padding: 45mm 16mm 24mm;
        }
        .declaration-title {
            margin: 0 0 1mm;
            text-align: center;
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 0.2px;
        }
        .gdpr-text {
            font-size: 6.6px;
            line-height: 1.16;
            text-align: justify;
            color: #27358f;
        }
        .gdpr-text p {
            margin: 0 0 0.8mm;
        }
        .operator-row {
            margin-bottom: 0.45mm;
            font-size: 7.2px;
        }
        .label { font-weight: 700; }
        .check-row {
            margin: 0 0 0.42mm;
            page-break-inside: avoid;
        }
        .check {
            display: inline-block;
            width: 3.2mm;
            text-align: center;
            font-weight: 700;
            margin-right: 0.7mm;
        }
        .section-title {
            margin: 0.9mm 0 0.5mm;
            font-weight: 700;
        }
        .rights-list {
            margin: 0 0 0.55mm 0;
            padding: 0;
            list-style: none;
        }
        .rights-list li {
            margin: 0 0 0.38mm;
        }
        .rights-list li::before {
            content: "√ ";
            font-weight: 700;
        }
        .rights-sublist {
            margin: 0.3mm 0 0.6mm 3.1mm;
            padding: 0;
            list-style: none;
        }
        .rights-sublist li {
            margin: 0 0 0.28mm;
        }
        .rights-sublist li::before {
            content: "■ ";
            font-weight: 700;
        }
        .signature-wrap {
            margin-top: 0.7mm;
            width: 70mm;
            font-size: 8px;
        }
        .signature-row {
            margin: 0 0 1.1mm;
        }
        .signature-box {
            margin-top: 0.5mm;
            width: 62mm;
            height: 13mm;
            border: 1px solid #27358f;
            padding: 0.5mm;
            box-sizing: border-box;
            overflow: hidden;
        }
        .signature-img {
            width: 100%;
            height: 100%;
            max-width: none;
            max-height: none;
            display: block;
            margin: 0;
        }
    </style>
</head>
<body>
@php
    $orderNumber = str_pad((string) $comanda->id, 6, '0', STR_PAD_LEFT);
    $clientSnapshot = $consent->client_snapshot ?? [];
    $clientName = $clientSnapshot['nume'] ?? optional($comanda->client)->nume_complet ?? '-';
    $clientEmail = $clientSnapshot['email'] ?? optional($comanda->client)->email ?? '-';
    $clientPhone = $clientSnapshot['telefon'] ?? optional($comanda->client)->telefon ?? '-';
    $signedAt = $consent->signed_at ?? $consent->created_at;
    $signedLabel = $signedAt ? $signedAt->format('d.m.Y H:i') : '-';
    $documentDate = $signedAt ? $signedAt->format('d.m.Y') : now()->format('d.m.Y');
    $orderDate = optional($comanda->data_solicitarii)->format('d.m.Y') ?? now()->format('d.m.Y');
    $isImplicitConsent = $consent->method === 'checkbox';
    $gdprContactEmail = config('mail.reply_to.address') ?? config('mail.from.address');
    $gdprContactEmailLabel = $gdprContactEmail ?: 'office@blue-print.ro';
    $signatureSrc = '';
    if ($consent->signature_path) {
        $signaturePath = \Illuminate\Support\Facades\Storage::disk('public')->path($consent->signature_path);
        if (is_file($signaturePath)) {
            $signatureBinary = file_get_contents($signaturePath);
            if ($signatureBinary !== false) {
                $signatureSrc = 'data:image/png;base64,' . base64_encode($signatureBinary);
            }
        }
    }
    $bgPage = \App\Support\PdfAsset::fromPublic('assets/pdf-backgrounds/gdpr-p1.png');
@endphp

<section class="page">
    <img class="page-bg" src="{{ $bgPage }}" alt="">
    <div class="content">
        <div class="declaration-title">Declarație consimțământ</div>

        <div class="gdpr-text">
            <div class="operator-row"><span class="label">Operator:</span> tipografia blu.e-print</div>

            <p>
                <span class="label">G.D.P.R.:</span> Regulamentul (UE) 2016/679 al Parlamentului European și al Consiliului din
                27 aprilie 2016 privind protecția persoanelor fizice în ceea ce privește prelucrarea datelor cu caracter personal
                și privind libera circulație a acestor date. „Date cu caracter personal” și „prelucrarea datelor cu caracter personal”
                au sensurile prevăzute de legislația aplicabilă.
            </p>

            <p>
                Subsemnatul(a) <strong>{{ $clientName }}</strong>, în calitate de beneficiar al comenzii nr.
                <strong>{{ $orderNumber }}</strong> din <strong>{{ $orderDate }}</strong>, în conformitate cu articolul 7 din G.D.P.R.,
                îmi exprim consimțământul în legătură cu prelucrarea datelor mele personale de către Operator, pentru următoarele scopuri:
            </p>

            <div class="check-row"><span class="check">{{ $consent->consent_marketing ? '☑' : '☐' }}</span>marketing (direct) - newsletter în format electronic, sondaje, publicitate, loturi publicitare;</div>
            <div class="check-row"><span class="check">{{ $consent->consent_media_marketing ? '☑' : '☐' }}</span>marketing și promovare - comunicare pe canale media și rețele de socializare;</div>
            <div class="check-row"><span class="check">{{ $consent->consent_research_statistics ? '☑' : '☐' }}</span>cercetare și efectuare de statistici;</div>
            <div class="check-row"><span class="check">{{ $consent->consent_online_communications ? '☑' : '☐' }}</span>trimitere de comunicări, evaluare a comportamentului în mediile online, testare, dezvoltare și utilizare</div>

            <p>
                De asemenea, îmi exprim consimțământul pentru transferul datelor cu caracter personal între entități partenere ale Operatorului,
                precum și către alte entități asociate Operatorului din întreaga lume, situate în țări care garantează protecția datelor personale
                pe teritoriul lor, cel puțin în aceeași măsură ca pe teritoriul României, în scopul îndeplinirii cerințelor contractuale, inclusiv în scopuri de marketing.
            </p>

            <p class="section-title">Consimțământul pentru prelucrarea exprimată mai sus include următoarele categorii de date cu caracter personal:</p>
            <div class="check-row"><span class="check">■</span>numele și prenumele;</div>
            <div class="check-row"><span class="check">■</span>adresa de e-mail personală sau de serviciu;</div>
            <div class="check-row"><span class="check">■</span>numărul de telefon mobil;</div>
            <div class="check-row"><span class="check">■</span>adresa de corespondență;</div>
            <div class="check-row"><span class="check">■</span>date de trafic;</div>
            <div class="check-row"><span class="check">■</span>date de localizare;</div>
            <div class="check-row"><span class="check">■</span>alte date relevante.</div>

            <p class="section-title">Prin prezenta, afirm și recunosc că am fost informat cu privire la dreptul meu de a:</p>
            <ul class="rights-list">
                <li>accesa și rectifica datele mele personale;</li>
                <li>solicita ștergerea datelor mele personale, în următoarele situații:</li>
            </ul>
            <ul class="rights-sublist">
                <li>datele cu caracter personal nu mai sunt necesare pentru îndeplinirea scopurilor pentru care au fost colectate;</li>
                <li>în cazul în care îmi retrag consimțământul pe baza căruia are loc prelucrarea și nu există niciun alt temei juridic pentru prelucrare;</li>
                <li>când mă opun prelucrării datelor mele personale în scop de marketing direct;</li>
                <li>când datele mele personale au fost prelucrate ilegal;</li>
                <li>când datele mele personale trebuie șterse pentru respectarea unei obligații legale care revine operatorului;</li>
                <li>când datele mele personale au fost colectate în legătură cu oferirea directă a unor servicii societății informaționale unui copil.</li>
            </ul>

            <ul class="rights-list">
                <li>face o cerere justificată, în scris, pentru restricționarea prelucrării datelor mele personale, în următoarele situații:</li>
            </ul>
            <ul class="rights-sublist">
                <li>contest exactitatea datelor, pentru perioada necesară operatorului pentru verificarea exactității;</li>
                <li>prelucrarea este ilegală;</li>
                <li>operatorul nu mai are nevoie de datele mele personale, dar eu le solicit pentru constatarea, exercitarea sau apărarea unui drept în instanță;</li>
                <li>m-am opus prelucrării datelor mele personale în temeiul dreptului de opoziție, pentru perioada în care se verifică dacă drepturile legitime ale operatorului prevalează asupra drepturilor mele.</li>
            </ul>
            <ul class="rights-list">
                <li>solicita portarea datelor mele către un alt operator, în următoarele situații:</li>
            </ul>
            <ul class="rights-sublist">
                <li>prelucrarea datelor mele personale se bazează pe consimțământul meu sau pe un contract;</li>
                <li>prelucrarea datelor mele personale a fost efectuată prin mijloace automate.</li>
            </ul>
            <ul class="rights-list">
                <li>mă opun prelucrării datelor mele personale, în următoarele situații:</li>
            </ul>
            <ul class="rights-sublist">
                <li>prelucrarea se efectuează în scopul îndeplinirii unei sarcini care servește unui interes public;</li>
                <li>prelucrarea are ca scop interesele legitime urmărite de operator sau de o terță parte;</li>
                <li>prelucrarea datelor mele personale se efectuează în scopuri de marketing direct;</li>
                <li>când datele mele personale au fost colectate în legătură cu oferirea de servicii ale societății informaționale și pot exercita dreptul de a mă opune prin mijloace automate;</li>
                <li>prelucrarea datelor mele personale se efectuează în scopuri de cercetare științifică sau istorică ori în scopuri statistice, cu excepția cazului în care prelucrarea este necesară pentru îndeplinirea unei sarcini de interes public.</li>
            </ul>
            <ul class="rights-list">
                <li>nu face obiectul unei decizii bazate exclusiv pe prelucrare automată, inclusiv creare de profiluri, cu excepția cazului în care decizia este necesară pentru încheierea sau executarea unui contract între mine și operator, este autorizată de dreptul Uniunii Europene sau național, sau are la bază consimțământul meu explicit;</li>
                <li>obiecta la transferul datelor către un alt operator, țară terță sau organizație internațională;</li>
                <li>precum și faptul că furnizarea datelor mele personale este voluntară.</li>
            </ul>

            <p>
                De asemenea, afirm și recunosc că am fost informat cu privire la posibilitatea retragerii consimțământului în orice moment și că acest lucru nu afectează
                prelucrarea efectuată pe baza consimțământului înainte de retragerea acestuia. Retragerea consimțământului se poate face prin transmiterea unui e-mail la
                <strong>{{ $gdprContactEmailLabel }}</strong> sau prin bifarea/debifarea opțiunii în platforma <strong>www.blue-print.ro</strong>.
                Totodată, îmi este recunoscut dreptul de a depune o plângere în fața Autorității Naționale de Supraveghere a Prelucrării Datelor cu Caracter Personal
                - ANSPDCP (<strong>www.dataprotection.ro</strong>), în cazul în care am îngrijorări cu privire la modul în care Operatorul îmi prelucrează datele personale.
                @if ($isImplicitConsent)
                    Pentru comenzile preluate la distanță, acordul GDPR poate fi marcat implicit.
                @endif
            </p>
            <p>
                Prin acceptarea prezentului formular de consimțământ, confirm în mod expres, liber și neîngrădit că am fost informat cu privire la datele mele
                cu caracter personal care urmează să fie stocate și prelucrate și sunt de acord ca aceste date cu caracter personal să fie prelucrate și utilizate
                în limitele astfel specificate, pe durata nelimitată.
            </p>
        </div>

        <div class="signature-wrap">
            <div class="signature-row"><span class="label">Client:</span> {{ $clientName }}</div>
            <div class="signature-row"><span class="label">Data:</span> {{ $documentDate }}</div>
            <div class="signature-row">
                <span class="label">Semnătură:</span>
                <div class="signature-box">
                    @if ($signatureSrc)
                        <img class="signature-img" src="{{ $signatureSrc }}" alt="Semnătură">
                    @endif
                </div>
            </div>
        </div>
    </div>
</section>
</body>
</html>
