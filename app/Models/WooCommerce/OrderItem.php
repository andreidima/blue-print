<?php

namespace App\Models\WooCommerce;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    protected $table = 'wc_order_items';

    protected $fillable = [
        'wc_order_id',
        'woocommerce_item_id',
        'product_id',
        'variation_id',
        'name',
        'sku',
        'quantity',
        'price',
        'subtotal',
        'subtotal_tax',
        'total',
        'total_tax',
        'taxes',
        'meta',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'price' => 'decimal:4',
        'subtotal' => 'decimal:4',
        'subtotal_tax' => 'decimal:4',
        'total' => 'decimal:4',
        'total_tax' => 'decimal:4',
        'taxes' => 'array',
        'meta' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'wc_order_id');
    }
}
