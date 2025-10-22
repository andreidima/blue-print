<?php

namespace App\Models\Procurement;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    protected $table = 'procurement_purchase_orders';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_RECEIVED = 'received';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_PENDING,
        self::STATUS_SENT,
        self::STATUS_PARTIAL,
        self::STATUS_RECEIVED,
        self::STATUS_CANCELLED,
    ];

    protected $guarded = ['*'];

    protected $casts = [
        'expected_at' => 'date',
        'received_at' => 'datetime',
        'total_value' => 'decimal:2',
    ];

    public static function openStatuses(): array
    {
        return [
            self::STATUS_DRAFT,
            self::STATUS_PENDING,
            self::STATUS_SENT,
            self::STATUS_PARTIAL,
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function markAsReceived(?Carbon $receivedAt = null): void
    {
        $this->status = self::STATUS_RECEIVED;
        $this->received_at = $receivedAt ?? Carbon::now();
    }
}
