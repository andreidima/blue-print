<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComandaSolicitare extends Model
{
    use HasFactory;

    protected $table = 'comanda_solicitari';

    protected $fillable = [
        'comanda_id',
        'solicitare_client',
        'cantitate',
        'created_by',
        'created_by_label',
    ];

    protected function casts(): array
    {
        return [
            'cantitate' => 'integer',
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
