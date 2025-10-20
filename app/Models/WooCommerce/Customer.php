<?php

namespace App\Models\WooCommerce;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory;

    protected $table = 'wc_customers';

    protected $fillable = [
        'woocommerce_id',
        'email',
        'first_name',
        'last_name',
        'company',
        'phone',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'wc_customer_id');
    }
}
