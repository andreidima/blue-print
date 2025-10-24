<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductSkuAlias extends Model
{
    use HasFactory;

    protected $table = 'product_sku_aliases';

    protected $fillable = [
        'produs_id',
        'sku',
    ];

    public function produs(): BelongsTo
    {
        return $this->belongsTo(Produs::class, 'produs_id');
    }
}
