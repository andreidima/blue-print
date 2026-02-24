<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class AppSetting extends Model
{
    use HasFactory;

    private const CACHE_KEY = 'app_settings.values';

    public const TYPE_TEXT = 'text';
    public const TYPE_URL = 'url';
    public const TYPE_LONG_TEXT = 'long_text';

    protected $fillable = [
        'label',
        'key',
        'value',
        'type',
        'description',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => static::flushCache());
        static::deleted(fn () => static::flushCache());
    }

    public static function typeOptions(): array
    {
        return [
            self::TYPE_TEXT => 'Text',
            self::TYPE_URL => 'URL',
            self::TYPE_LONG_TEXT => 'Text lung',
        ];
    }

    public static function value(string $key, ?string $default = null): ?string
    {
        $values = static::cachedValues();

        return array_key_exists($key, $values) ? $values[$key] : $default;
    }

    public static function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private static function cachedValues(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            return static::query()
                ->pluck('value', 'key')
                ->all();
        });
    }
}
