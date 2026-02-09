<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SmsTemplate extends Model
{
    use HasFactory;

    protected $table = 'sms_templates';

    protected $fillable = [
        'key',
        'name',
        'color',
        'body',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(SmsMessage::class, 'sms_template_id');
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
