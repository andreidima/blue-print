<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    use HasFactory;

    protected $table = 'clienti';

    protected $fillable = [
        'type',
        'nume',
        'adresa',
        'telefon',
        'telefon_secundar',
        'email',
        'cnp',
        'sex',
        'reg_com',
        'cui',
        'iban',
        'banca',
        'reprezentant',
        'reprezentant_functie',
    ];

    protected function casts(): array
    {
        return [];
    }

    public function comenzi(): HasMany
    {
        return $this->hasMany(Comanda::class, 'client_id');
    }

    public function getNumeCompletAttribute(): string
    {
        return trim((string) ($this->nume ?? ''));
    }
}
