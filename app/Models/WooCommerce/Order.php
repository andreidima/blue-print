<?php

namespace App\Models\WooCommerce;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected $table = 'wc_orders';

    protected $fillable = [
        'woocommerce_id',
        'wc_customer_id',
        'status',
        'currency',
        'total',
        'subtotal',
        'total_tax',
        'shipping_total',
        'discount_total',
        'payment_method',
        'payment_method_title',
        'date_created',
        'date_modified',
        'meta',
    ];

    protected $casts = [
        'date_created' => 'datetime',
        'date_modified' => 'datetime',
        'total' => 'decimal:4',
        'subtotal' => 'decimal:4',
        'total_tax' => 'decimal:4',
        'shipping_total' => 'decimal:4',
        'discount_total' => 'decimal:4',
        'meta' => 'array',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'wc_customer_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'wc_order_id');
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(OrderAddress::class, 'wc_order_id');
    }
}
