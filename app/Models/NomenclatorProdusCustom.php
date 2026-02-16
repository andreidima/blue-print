<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class NomenclatorProdusCustom extends Model
{
    use HasFactory;

    protected $table = 'nomenclator_produse_custom';

    protected $fillable = [
        'denumire',
        'lookup_key',
        'canonical_key',
        'canonical_id',
        'is_canonical',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_canonical' => 'boolean',
        ];
    }

    public function scopeCanonical($query)
    {
        return $query->where('is_canonical', true);
    }

    public function canonical(): BelongsTo
    {
        return $this->belongsTo(self::class, 'canonical_id');
    }

    public function aliases(): HasMany
    {
        return $this->hasMany(self::class, 'canonical_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function makeLookupKey(string $value): string
    {
        $tokens = self::tokenize($value);
        return implode(' ', $tokens);
    }

    public static function makeCanonicalKey(string $value): string
    {
        $tokens = self::tokenize($value);
        sort($tokens);
        return implode(' ', $tokens);
    }

    private static function tokenize(string $value): array
    {
        $ascii = Str::of($value)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->squish()
            ->value();

        if ($ascii === '') {
            return [];
        }

        preg_match_all('/[a-z]+|\d+/', $ascii, $matches);
        return $matches[0] ?? [];
    }
}

