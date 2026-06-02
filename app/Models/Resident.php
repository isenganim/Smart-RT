<?php

namespace App\Models;

use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Resident extends Model
{
    use HasFactory;

    protected $fillable = [
        'household_id',
        'name',
        'phone',
        'is_active',
        'ronda_notes',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function setPhoneAttribute(?string $value): void
    {
        $this->attributes['phone'] = PhoneNumber::normalize($value);
    }

    /**
     * Phone number formatted for display in local (0-prefixed) form.
     */
    protected function displayPhone(): Attribute
    {
        return Attribute::make(
            get: fn () => PhoneNumber::forDisplay($this->phone),
        );
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }
}
