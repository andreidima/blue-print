<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Produs extends Model
{
    use HasFactory;

    protected $table = 'produse';

    protected $fillable = [
        'denumire',
        'pret',
        'activ',
    ];

    protected function casts(): array
    {
        return [
            'pret' => 'decimal:2',
            'activ' => 'boolean',
        ];
    }

    public function comenziProduse(): HasMany
    {
        return $this->hasMany(ComandaProdus::class, 'produs_id');
    }
}
