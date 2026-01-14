<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Plata extends Model
{
    use HasFactory;

    protected $table = 'plati';

    protected $fillable = [
        'comanda_id',
        'suma',
        'metoda',
        'numar_factura',
        'platit_la',
        'note',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'suma' => 'decimal:2',
            'platit_la' => 'datetime',
        ];
    }

    public function comanda(): BelongsTo
    {
        return $this->belongsTo(Comanda::class, 'comanda_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
