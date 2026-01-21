<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComandaProdus extends Model
{
    use HasFactory;

    protected $table = 'comanda_produse';

    protected $fillable = [
        'comanda_id',
        'produs_id',
        'custom_denumire',
        'cantitate',
        'pret_unitar',
        'total_linie',
    ];

    protected function casts(): array
    {
        return [
            'pret_unitar' => 'decimal:2',
            'total_linie' => 'decimal:2',
        ];
    }

    public function comanda(): BelongsTo
    {
        return $this->belongsTo(Comanda::class, 'comanda_id');
    }

    public function produs(): BelongsTo
    {
        return $this->belongsTo(Produs::class, 'produs_id');
    }
}
