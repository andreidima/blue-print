<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComandaFacturaEmail extends Model
{
    use HasFactory;

    protected $table = 'comanda_factura_emails';

    protected $fillable = [
        'comanda_id',
        'sent_by',
        'recipient',
        'subject',
        'body',
        'facturi',
    ];

    protected function casts(): array
    {
        return [
            'facturi' => 'array',
        ];
    }

    public function comanda(): BelongsTo
    {
        return $this->belongsTo(Comanda::class, 'comanda_id');
    }

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}
