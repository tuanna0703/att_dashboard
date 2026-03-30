<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class AppSetting extends Model
{
    protected $primaryKey = 'key';
    protected $keyType    = 'string';
    public $incrementing  = false;

    protected $fillable = ['key', 'value'];

    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::rememberForever("app_setting:{$key}", function () use ($key, $default) {
            $row = static::find($key);
            return $row ? $row->value : $default;
        });
    }

    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget("app_setting:{$key}");
    }

    public static function getGroup(string $prefix): array
    {
        return static::where('key', 'like', $prefix . '.%')
            ->pluck('value', 'key')
            ->mapWithKeys(fn ($v, $k) => [str_replace($prefix . '.', '', $k) => $v])
            ->toArray();
    }

    public static function setGroup(string $prefix, array $data): void
    {
        foreach ($data as $key => $value) {
            static::set("{$prefix}.{$key}", $value);
        }
    }
}
