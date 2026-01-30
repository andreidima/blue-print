<div class="row mb-3">
    <div class="col-lg-4">
        <div class="p-2 bg-light rounded-3">
            <strong>Total:</strong> {{ number_format($comanda->total, 2) }}
        </div>
    </div>
    <div class="col-lg-4">
        <div class="p-2 bg-light rounded-3">
            <strong>Total platit:</strong> {{ number_format($comanda->total_platit, 2) }}
        </div>
    </div>
    <div class="col-lg-4">
        <div class="p-2 bg-light rounded-3">
            <strong>Status plata:</strong> {{ $statusPlataOptions[$comanda->status_plata] ?? $comanda->status_plata }}
        </div>
    </div>
</div>
