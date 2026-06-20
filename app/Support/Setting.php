<?php

namespace App\Support;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Cache;

class Setting
{
    public const KEYS = [
        'iuran_amount',
        'denda_amount',
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::remember(
            "setting.{$key}",
            3600,
            fn () => AppSetting::query()->where('key', $key)->value('value') ?? $default,
        );
    }

    public static function set(string $key, mixed $value, ?int $updatedBy = null): void
    {
        AppSetting::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'updated_by' => $updatedBy],
        );

        Cache::forget("setting.{$key}");
    }

    public static function flush(): void
    {
        foreach (self::KEYS as $key) {
            Cache::forget("setting.{$key}");
        }
    }
}
