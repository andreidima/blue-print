<?php

namespace App\Models\WooCommerce;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderAddress extends Model
{
    use HasFactory;

    protected $table = 'wc_order_addresses';

    protected $fillable = [
        'wc_order_id',
        'type',
        'first_name',
        'last_name',
        'company',
        'address_1',
        'address_2',
        'city',
        'state',
        'postcode',
        'country',
        'email',
        'phone',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'wc_order_id');
    }
}
