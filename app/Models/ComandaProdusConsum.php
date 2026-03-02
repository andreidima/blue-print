<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComandaProdusConsum extends Model
{
    use HasFactory;

    protected $table = 'comanda_produs_consumuri';

    protected $fillable = [
        'comanda_produs_id',
        'material_id',
        'material_denumire',
        'cantitate_per_unitate',
        'unitate_masura',
        'cantitate_totala',
        'cantitate_rebutata',
        'echipament_id',
        'echipament_denumire',
        'observatii',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'cantitate_per_unitate' => 'decimal:4',
            'cantitate_totala' => 'decimal:4',
            'cantitate_rebutata' => 'decimal:4',
        ];
    }

    public function comandaProdus(): BelongsTo
    {
        return $this->belongsTo(ComandaProdus::class, 'comanda_produs_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(NomenclatorMaterial::class, 'material_id');
    }

    public function echipament(): BelongsTo
    {
        return $this->belongsTo(NomenclatorEchipament::class, 'echipament_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function materialLabel(): string
    {
        return $this->material_denumire ?: ($this->material?->denumire ?? '-');
    }

    public function echipamentLabel(): string
    {
        return $this->echipament_denumire ?: ($this->echipament?->denumire ?? '-');
    }

    public function totalRebut(): float
    {
        return (float) $this->cantitate_rebutata;
    }

    public function totalConsumCuRebut(): float
    {
        return (float) $this->cantitate_totala + (float) $this->cantitate_rebutata;
    }
}
