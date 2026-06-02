<?php

namespace App\Services;

use App\Models\Resident;

class PhoneLookupResult
{
    public function __construct(
        public readonly ?Resident $resident = null,
        public readonly ?string $message = null,
    ) {}

    public static function hit(Resident $resident): self
    {
        return new self(resident: $resident);
    }

    public static function miss(string $message): self
    {
        return new self(message: $message);
    }

    public function found(): bool
    {
        return $this->resident !== null;
    }
}
