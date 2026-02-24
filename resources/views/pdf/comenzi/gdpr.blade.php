<!doctype html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <title>Acord GDPR comanda #{{ $comanda->id }}</title>
    <style>
        @page { margin: 0; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #222;
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
            padding: 38mm 14mm 24mm;
        }
        .title {
            font-size: 15px;
            font-weight: 700;
            color: #1f4e79;
            margin-bottom: 6px;
        }
        .box {
            border: 1px solid #999;
            padding: 6px;
            margin-top: 6px;
        }
        .label { font-weight: 700; }
        .row { margin: 2px 0; }
        .check {
            display: inline-block;
            width: 13px;
            height: 13px;
            border: 1px solid #555;
            text-align: center;
            line-height: 13px;
            margin-right: 4px;
            font-size: 10px;
        }
        .gdpr-text p {
            margin: 6px 0;
            line-height: 1.35;
            text-align: justify;
        }
        .signature-grid {
            width: 100%;
            border-collapse: collapse;
            margin-top: 14px;
        }
        .signature-grid td {
            width: 50%;
            vertical-align: top;
            padding: 6px;
        }
        .signature-box {
            border-top: 1px solid #444;
            margin-top: 22px;
            padding-top: 4px;
            text-align: center;
            font-size: 10px;
            min-height: 80px;
        }
        .signature-img {
            max-height: 65px;
            max-width: 100%;
            display: block;
            margin: 0 auto 4px;
        }
    </style>
</head>
<body>
@php
    $clientSnapshot = $consent->client_snapshot ?? [];
    $clientName = $clientSnapshot['nume'] ?? optional($comanda->client)->nume_complet ?? '-';
    $clientEmail = $clientSnapshot['email'] ?? optional($comanda->client)->email ?? '-';
    $clientPhone = $clientSnapshot['telefon'] ?? optional($comanda->client)->telefon ?? '-';
    $signedAt = $consent->signed_at ?? $consent->created_at;
    $signedLabel = $signedAt ? $signedAt->format('d/m/Y H:i') : '-';
    $isImplicitConsent = $consent->method === 'checkbox';
    $gdprContactEmail = config('mail.reply_to.address') ?? config('mail.from.address');
    $gdprContactEmailLabel = $gdprContactEmail ?: 'adresa oficiala de e-mail a companiei';
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

    $toFileUrl = static function (string $path): string {
        return 'file:///' . ltrim(str_replace('\\', '/', $path), '/');
    };

    $bgPage = $toFileUrl(public_path('assets/pdf-backgrounds/gdpr-p1.png'));
@endphp

<section class="page">
    <img class="page-bg" src="{{ $bgPage }}" alt="">
    <div class="content">
        <div class="title">Acord G.D.P.R. marketing &amp; promovare</div>

        <div class="box gdpr-text">
            <p>
                Subsemnatul(a), <strong>{{ $clientName }}</strong>, avand datele de contact:
                telefon <strong>{{ $clientPhone }}</strong>, e-mail <strong>{{ $clientEmail }}</strong>,
                imi exprim acordul privind prelucrarea datelor cu caracter personal, in conditiile legislatiei aplicabile.
            </p>
            <p>
                Sunt de acord ca datele mele sa fie utilizate pentru comunicari comerciale, oferte, campanii de marketing,
                notificari despre produse si servicii, prin e-mail, telefon sau alte mijloace de comunicare.
            </p>
            <p>
                Am fost informat(a) ca imi pot retrage consimtamantul in orice moment, fara a afecta legalitatea
                prelucrarii efectuate inainte de retragere.
            </p>
            <p>
                Data inregistrarii acordului: <strong>{{ $signedLabel }}</strong>.
                @if ($isImplicitConsent)
                    Pentru comenzile preluate la distanta, acordul GDPR este acceptat implicit.
                    Pentru modificare, solicitarea se transmite prin e-mail catre {{ $gdprContactEmailLabel }}.
                @endif
            </p>
        </div>

        <div class="box">
            <div class="row"><span class="check">{{ $consent->consent_marketing ? 'X' : '' }}</span> Sunt de acord sa primesc comunicari prin e-mail</div>
            <div class="row"><span class="check">{{ $consent->consent_processing ? 'X' : '' }}</span> Sunt de acord cu prelucrarea datelor personale</div>
            <div class="row"><span class="check">{{ $consent->consent_media_marketing ? 'X' : '' }}</span> Sunt de acord cu utilizarea foto/video in marketing</div>
            <div class="row"><span class="check">{{ !$consent->consent_marketing && !$consent->consent_media_marketing ? 'X' : '' }}</span> Nu doresc comunicari de marketing</div>
        </div>

        <table class="signature-grid">
            <tr>
                <td>
                    <div class="row"><span class="label">Data:</span> {{ $signedLabel }}</div>
                    <div class="signature-box">Data completarii</div>
                </td>
                <td>
                    <div class="row"><span class="label">Nume si semnatura:</span></div>
                    <div class="signature-box">
                        @if ($signatureSrc)
                            <img class="signature-img" src="{{ $signatureSrc }}" alt="Semnatura">
                        @endif
                        Semnatura persoana vizata
                    </div>
                </td>
            </tr>
        </table>
    </div>
</section>
</body>
</html>
