<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Role extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'color',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->slug === 'superadmin';
    }

    public static function slugFromName(string $name): string
    {
        return Str::slug($name);
    }

    public static function normalizeIdentifierForChecks(string $identifier): string
    {
        $slug = Str::slug($identifier);

        return match ($slug) {
            'admin' => 'supervizor',
            'operator' => 'operator-front-office',
            default => $slug,
        };
    }
}

