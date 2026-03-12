<!doctype html>
<html lang="ro">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Asignare comanda</title>
    </head>
    <body style="margin:0; padding:0; background-color:#eff1f0;">
        <div style="margin:0 auto; width:100%; background-color:#eff1f0;">
            <div style="margin:0 auto; max-width:800px; background-color:#ffffff;">
                @include('emailuri.headerFooter.header')

                <div style="padding:20px; max-width:760px; margin:0 auto; font-size:16px; color:#111827; line-height:1.6;">
                    <p style="margin-top:0;">Buna {{ $recipient->name }},</p>

                    <p>
                        Ai fost asignat la comanda <strong>#{{ $comanda->id }}</strong>
                        @if ($comanda->client?->nume_complet)
                            pentru {{ $comanda->client->nume_complet }}
                        @endif.
                    </p>

                    @if (!empty($stages))
                        <p style="margin-bottom:8px;"><strong>Etape alocate:</strong></p>
                        <ul style="margin-top:0; padding-left:20px;">
                            @foreach ($stages as $stage)
                                <li>{{ $stage }}</li>
                            @endforeach
                        </ul>
                    @endif

                    @if ($assignedBy)
                        <p><strong>Asignat de:</strong> {{ $assignedBy->name }}</p>
                    @endif

                    <p>Poti deschide comanda din butonul de mai jos.</p>

                    <div style="margin-top:20px; margin-bottom:20px;">
                        <a href="{{ $orderUrl }}"
                            style="display:inline-block; padding:8px 14px; background-color:#0d6efd; color:#ffffff; text-decoration:none; border-radius:5px; font-size:13px; line-height:1.2;">
                            Deschide comanda
                        </a>
                    </div>

                    <p style="margin-bottom:0;">{{ $appName }}</p>
                    @include('emails.partials.signature')
                </div>
            </div>

            @include('emailuri.headerFooter.footer')
        </div>
    </body>
</html>
