<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Etapa extends Model
{
    use HasFactory;

    protected $table = 'etape';

    protected $fillable = [
        'slug',
        'label',
    ];

    public function comandaAssignments(): HasMany
    {
        return $this->hasMany(ComandaEtapaUser::class, 'etapa_id');
    }
}
