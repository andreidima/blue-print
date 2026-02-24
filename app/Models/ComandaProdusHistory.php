<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComandaProdusHistory extends Model
{
    use HasFactory;

    protected $table = 'comanda_produs_histories';

    protected $fillable = [
        'comanda_id',
        'comanda_produs_id',
        'actor_user_id',
        'action',
        'changes',
    ];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
        ];
    }

    public function comanda(): BelongsTo
    {
        return $this->belongsTo(Comanda::class, 'comanda_id');
    }

    public function produsLinie(): BelongsTo
    {
        return $this->belongsTo(ComandaProdus::class, 'comanda_produs_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
