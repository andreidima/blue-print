<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComandaEtapaUser extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';

    protected $table = 'comanda_etapa_user';

    protected $fillable = [
        'comanda_id',
        'etapa_id',
        'user_id',
        'status',
    ];

    public function comanda(): BelongsTo
    {
        return $this->belongsTo(Comanda::class, 'comanda_id');
    }

    public function etapa(): BelongsTo
    {
        return $this->belongsTo(Etapa::class, 'etapa_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
