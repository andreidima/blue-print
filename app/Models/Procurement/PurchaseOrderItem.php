<?php

namespace App\Models\Procurement;

use App\Models\Produs;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    protected $table = 'procurement_purchase_order_items';

    protected $guarded = ['*'];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'line_total' => 'decimal:2',
        'received_quantity' => 'decimal:2',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function produs(): BelongsTo
    {
        return $this->belongsTo(Produs::class, 'produs_id');
    }
}
