<?php

namespace App\Support;

use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class Setting
{
    public const KEYS = [
        'iuran_amount',
        'denda_amount',
        'kas_opening_balance',
        'kas_opening_date',
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        self::assertKnownKey($key);

        return Cache::remember(
            "setting.{$key}",
            3600,
            fn () => AppSetting::query()->where('key', $key)->value('value') ?? $default,
        );
    }

    public static function set(string $key, mixed $value, ?int $updatedBy = null): void
    {
        self::assertKnownKey($key);

        $previous = AppSetting::query()->where('key', $key)->value('value');

        $setting = AppSetting::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'updated_by' => $updatedBy],
        );

        Cache::forget("setting.{$key}");

        Audit::record(
            $updatedBy ? User::find($updatedBy) : null,
            'setting.updated',
            'app_setting',
            $setting->id,
            ['key' => $key, 'from' => $previous, 'to' => $value],
        );
    }

    private static function assertKnownKey(string $key): void
    {
        if (! in_array($key, self::KEYS, true)) {
            throw new \InvalidArgumentException("Unknown setting key [{$key}]");
        }
    }

    public static function flush(): void
    {
        foreach (self::KEYS as $key) {
            Cache::forget("setting.{$key}");
        }
    }
}
