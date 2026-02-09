<!doctype html>
<html lang="ro">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Acord GDPR</title>
    </head>
    <body style="margin:0; padding:0; background-color:#eff1f0;">
        <div style="margin:0 auto; width:100%; background-color:#eff1f0;">
            <div style="margin:0 auto; max-width:800px; background-color:#ffffff;">
                @include('emailuri.headerFooter.header')

                <div style="padding:20px; max-width:760px; margin:0 auto; font-size:16px; color:#111827; line-height:1.6;">
                    {!! $bodyHtml ?? '' !!}
                    @include('emails.partials.download-button', ['downloadUrl' => $downloadUrl ?? null])
                    @include('emails.partials.signature')
                </div>
            </div>

            @include('emailuri.headerFooter.footer')
        </div>
    </body>
</html>
