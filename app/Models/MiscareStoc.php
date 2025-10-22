<?php

namespace App\Models;

use App\Models\WooCommerce\OrderItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MiscareStoc extends Model
{
    protected $table = 'miscari_stoc';
    public $timestamps = false;    // we only have created_at
    protected $fillable = ['produs_id','user_id','delta', 'nr_comanda', 'wc_order_item_id'];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function produs(): BelongsTo
    {
        return $this->belongsTo(Produs::class, 'produs_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'wc_order_item_id');
    }
}
