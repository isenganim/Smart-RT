<?php

namespace App\Support;

use Illuminate\Support\Str;

class PhoneNumber
{
    public static function normalize(?string $value): string
    {
        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';

        if (Str::startsWith($digits, '62')) {
            $digits = substr($digits, 2);
        }

        return ltrim($digits, '0');
    }
}
