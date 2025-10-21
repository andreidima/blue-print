<!DOCTYPE html>
<html lang="ro">
<head>
  <meta charset="utf-8">
  <title>Comanda {{ $nr_comanda }}</title>
  <style>
    /* ensure DejaVu Sans is used everywhere for correct diacritics */
    body {
        font-family: "DejaVu Sans", sans-serif;
    }
    .header { text-align: center; margin-bottom: 2rem; }
    .logo { font-size: 1.5rem; font-weight: bold; }
    .company { font-size: 1.25rem; }
    .table { width: 100%; border-collapse: collapse; margin-bottom: 2rem; }
    .table th, .table td { border: 1px solid #333; padding: 0.5rem; }
    .table th { background: #eee; }

    .footer { margin-top: 3rem; }
    .sig { width: 45%; display: inline-block; text-align: center; }
  </style>
</head>
<body>
  <div class="header">
    <div class="logo">🏠</div>
    <div class="company">LORAND WOOD FACTORY</div>
    <div class="title"><strong>Comanda de ieșire: {{ $nr_comanda }}</strong></div>
    <div>Perioadă: {{ $data_inceput->format('d.m.Y H:i') }} – {{ $data_sfarsit->format('d.m.Y H:i') }}</div>
  </div>

  <table class="table">
    <thead>
      <tr>
        <th>#</th>
        <th>Produs</th>
        <th>Cantitate</th>
        <th>Data</th>
        <th>Utilizator</th>
      </tr>
    </thead>
    <tbody>
      @foreach($movements as $m)
      <tr>
        <td>{{ $loop->iteration }}</td>
        <td>{{ $m->produs?->nume ?? '—' }}</td>
        <td>{{ abs($m->delta) }}</td>
        <td>{{ $m->created_at->format('d.m.Y H:i') }}</td>
        <td>{{ $m->user?->name ?? '—' }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>

  <div class="footer">
    <div class="sig">
      _________________________<br>
      Semnătura operator
    </div>
    <div class="sig" style="float:right;">
      _________________________<br>
      Semnătura manager
    </div>
  </div>
</body>
</html>
