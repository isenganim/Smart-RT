<?php

namespace App\Services;

use App\Models\Resident;
use App\Support\PhoneNumber;

class ResidentLookup
{
    public const NOT_REGISTERED = 'Nomor HP belum terdaftar. Silakan hubungi pengurus RT.';

    public function resolve(?string $rawPhone): PhoneLookupResult
    {
        $normalized = PhoneNumber::normalize($rawPhone);

        if ($normalized === '') {
            return PhoneLookupResult::miss(self::NOT_REGISTERED);
        }

        $residents = Resident::query()
            ->where('is_active', true)
            ->where('phone', $normalized)
            ->limit(2)
            ->get();

        return $residents->count() === 1
            ? PhoneLookupResult::hit($residents->first())
            : PhoneLookupResult::miss(self::NOT_REGISTERED);
    }
}
