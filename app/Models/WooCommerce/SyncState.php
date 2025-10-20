<?php

namespace App\Models\WooCommerce;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SyncState extends Model
{
    use HasFactory;

    protected $table = 'wc_sync_states';

    protected $fillable = [
        'key',
        'value',
    ];
}
