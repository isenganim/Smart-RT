<?php

namespace App\Support;

use App\Models\FeatureSetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class Feature
{
    public const OPTIONAL_FEATURES = [
        'ronda',
        'kas',
        'announcements',
        'reports',
        'letters',
        'voting',
        'inventory',
    ];

    public static function enabled(string $key): bool
    {
        return Cache::remember(
            "feature.{$key}",
            3600,
            fn () => FeatureSetting::query()->where('key', $key)->value('is_enabled') ?? true,
        );
    }

    public static function all(): Collection
    {
        return Cache::remember(
            'features.all',
            3600,
            fn () => FeatureSetting::all()->keyBy('key'),
        );
    }

    public static function flush(): void
    {
        Cache::forget('features.all');

        foreach (self::OPTIONAL_FEATURES as $key) {
            Cache::forget("feature.{$key}");
        }
    }
}
