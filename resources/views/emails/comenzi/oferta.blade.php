<!doctype html>
<html lang="ro">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Ofertă comandă</title>
    </head>
    <body style="margin:0; padding:0; background-color:#eff1f0;">
        <div style="margin:0 auto; width:100%; background-color:#eff1f0;">
            <div style="margin:0 auto; max-width:800px; background-color:#ffffff;">
                @include('emailuri.headerFooter.header')

                <div style="padding:20px; max-width:760px; margin:0 auto; font-size:16px; color:#111827; line-height:1.6;">
                    {!! nl2br(e($body)) !!}
                    <p style="margin-top:16px; color:#6b7280; font-size:12px;">
                        Informare GDPR: datele personale sunt prelucrate exclusiv pentru ofertare si derularea comenzii.
                    </p>
                    <p style="margin-top:24px; color:#6b7280; font-size:12px;">
                        Comandă #{{ $comanda->id }}
                    </p>
                </div>
            </div>

            @include('emailuri.headerFooter.footer')
        </div>
    </body>
</html>
