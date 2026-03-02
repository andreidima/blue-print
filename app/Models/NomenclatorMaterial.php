<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NomenclatorMaterial extends Model
{
    use HasFactory;

    protected $table = 'nomenclator_materiale';

    protected $fillable = [
        'denumire',
        'unitate_masura',
        'descriere',
        'activ',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'activ' => 'boolean',
        ];
    }

    public function consumuri(): HasMany
    {
        return $this->hasMany(ComandaProdusConsum::class, 'material_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
