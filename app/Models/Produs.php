<?php

namespace App\Models;

use App\Services\WooCommerce\ProductInventoryService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Produs extends Model
{
    use HasFactory;

    protected $table = 'produse';
    protected $guarded = [];

    protected $casts = [
        'data_procesare' => 'date',
        'cantitate' => 'integer',
        'prag_minim' => 'integer',
        'lungime' => 'float',
        'latime' => 'float',
        'grosime' => 'float',
        'pret' => 'float',
    ];

    public function path($action = 'show')
    {
        return match ($action) {
            'edit' => route('produse.edit', $this->id),
            'destroy' => route('produse.destroy', $this->id),
            default => route('produse.show', $this->id),
        };
    }

    public function categorie()
    {
        return $this->belongsTo(Categorie::class, 'categorie_id');
    }


    /** 1️⃣ Define the one-to-many relationship to stock-movements */
    public function miscariStoc(): HasMany
    {
        return $this->hasMany(MiscareStoc::class, 'produs_id');
    }

    public function skuAliases(): HasMany
    {
        return $this->hasMany(ProductSkuAlias::class, 'produs_id');
    }

    public function syncSkuAliases(array $aliases): void
    {
        $normalized = collect($aliases)
            ->map(fn ($sku) => trim((string) $sku))
            ->filter()
            ->unique()
            ->values();

        if ($normalized->isEmpty()) {
            if ($this->skuAliases()->exists()) {
                $this->skuAliases()->delete();
            }

            return;
        }

        $normalizedValues = $normalized->all();

        $this->skuAliases()
            ->whereNotIn('sku', $normalizedValues)
            ->delete();

        foreach ($normalizedValues as $sku) {
            $this->skuAliases()->updateOrCreate(
                ['sku' => $sku],
                ['sku' => $sku]
            );
        }
    }
    /**
     * 2️⃣ On “deleting”, clean up all related movements in code
     *    (instead of DB-cascade) so you can inject any extra logic if needed.
     */
    protected static function booted()
    {
        static::deleting(function (Produs $produs) {
            // delete all movements before the product is removed
            $produs->miscariStoc()->delete();
        });

        static::saved(function (Produs $produs) {
            if (! $produs->wasChanged('cantitate')) {
                return;
            }

            app(ProductInventoryService::class)->syncStock($produs);
        });
    }
}
