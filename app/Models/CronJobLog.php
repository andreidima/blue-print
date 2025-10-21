<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CronJobLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'job_name',
        'ran_at',
        'status',
        'details',
    ];

    protected $casts = [
        'ran_at' => 'datetime',
    ];
}
