<div class="border rounded-3 p-3 bg-light h-100">
    <div class="fw-semibold mb-2">Placeholder-uri disponibile</div>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead>
                <tr>
                    <th>Token</th>
                    <th>Descriere</th>
                    <th>Exemplu</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($placeholders as $placeholder)
                    <tr>
                        <td><code>{{ $placeholder['token'] }}</code></td>
                        <td>{{ $placeholder['description'] }}</td>
                        <td class="text-muted">{{ $placeholder['example'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
