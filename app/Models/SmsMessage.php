<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmsMessage extends Model
{
    use HasFactory;

    protected $table = 'sms_messages';

    protected $fillable = [
        'comanda_id',
        'sms_template_id',
        'sent_by',
        'recipient',
        'message',
        'message_sent',
        'status',
        'provider',
        'gateway_level',
        'gateway_code',
        'gateway_message',
        'gateway_message_id',
        'gateway_raw',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function comanda(): BelongsTo
    {
        return $this->belongsTo(Comanda::class, 'comanda_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(SmsTemplate::class, 'sms_template_id');
    }

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}
