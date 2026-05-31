<?php

namespace App\Rules;

use App\Models\Resident;
use App\Support\PhoneNumber;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class UniqueActivePhone implements ValidationRule
{
    public function __construct(
        protected ?int $ignoreResidentId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $normalized = PhoneNumber::normalize(is_string($value) ? $value : null);

        $exists = Resident::query()
            ->where('is_active', true)
            ->where('phone', $normalized)
            ->when($this->ignoreResidentId, fn ($query) => $query->whereKeyNot($this->ignoreResidentId))
            ->exists();

        if ($exists) {
            $fail('Nomor HP sudah terdaftar untuk warga aktif lain.');
        }
    }
}
