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

        $resident = Resident::query()
            ->where('is_active', true)
            ->where('phone', $normalized)
            ->first();

        return $resident
            ? PhoneLookupResult::hit($resident)
            : PhoneLookupResult::miss(self::NOT_REGISTERED);
    }
}
