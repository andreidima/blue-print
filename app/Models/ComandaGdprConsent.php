<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComandaGdprConsent extends Model
{
    use HasFactory;

    protected $table = 'comanda_gdpr_consents';

    protected $fillable = [
        'comanda_id',
        'method',
        'consent_processing',
        'consent_marketing',
        'signature_path',
        'signed_at',
        'client_snapshot',
        'created_by',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'consent_processing' => 'boolean',
            'consent_marketing' => 'boolean',
            'signed_at' => 'datetime',
            'client_snapshot' => 'array',
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
