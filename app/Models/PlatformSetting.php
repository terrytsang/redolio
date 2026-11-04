<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;

class PlatformSetting extends Model
{
    protected $fillable = ['key', 'value'];

    public static function get(string $key, ?string $default = null): ?string
    {
        if (! Schema::hasTable('platform_settings')) {
            return $default;
        }

        // The cache holds the value exactly as stored (encrypted, for keys), so a readable key is never written to the cache.
        $stored = Cache::rememberForever("platform_setting:{$key}", fn () => static::where('key', $key)->value('value') ?? false);

        return $stored === false ? $default : static::reveal($key, (string) $stored);
    }

    public static function set(string $key, ?string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => static::isSecret($key) && $value !== null && $value !== '' ? Crypt::encryptString($value) : $value]);
        Cache::forget("platform_setting:{$key}");
    }

    /** API keys (anything ending in _api_key) are stored encrypted with the app key, never as readable text in the database. */
    public static function isSecret(string $key): bool
    {
        return str_ends_with($key, '_api_key');
    }

    private static function reveal(string $key, string $stored): string
    {
        if (! static::isSecret($key) || $stored === '') {
            return $stored;
        }
        try {
            return Crypt::decryptString($stored);
        } catch (\Throwable) {
            return $stored; // saved before keys were encrypted: still readable, re-encrypted the next time it is saved
        }
    }
}
