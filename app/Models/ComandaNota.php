<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComandaNota extends Model
{
    use HasFactory;

    protected $table = 'comanda_note';

    protected $fillable = [
        'comanda_id',
        'role',
        'nota',
        'created_by',
        'created_by_label',
    ];

    public function comanda(): BelongsTo
    {
        return $this->belongsTo(Comanda::class, 'comanda_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
