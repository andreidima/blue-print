<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Factura comanda</title>
    </head>
    <body>
        <div style="font-family: Arial, sans-serif; line-height: 1.6;">
            {!! nl2br(e($body)) !!}
            <p style="margin-top: 24px; color: #6c757d; font-size: 12px;">
                Comanda #{{ $comanda->id }}
            </p>
        </div>
    </body>
</html>
