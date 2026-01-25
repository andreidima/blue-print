<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComandaFactura extends Model
{
    use HasFactory;

    protected $table = 'comanda_facturi';

    protected $fillable = [
        'comanda_id',
        'uploaded_by',
        'original_name',
        'path',
        'mime',
        'size',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    public function comanda(): BelongsTo
    {
        return $this->belongsTo(Comanda::class, 'comanda_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function fileUrl(): string
    {
        return route('comenzi.facturi.view', ['comanda' => $this->comanda_id, 'factura' => $this->id]);
    }

    public function downloadUrl(): string
    {
        return route('comenzi.facturi.download', ['comanda' => $this->comanda_id, 'factura' => $this->id]);
    }

    public function destroyUrl(): string
    {
        return route('comenzi.facturi.destroy', ['comanda' => $this->comanda_id, 'factura' => $this->id]);
    }
}
