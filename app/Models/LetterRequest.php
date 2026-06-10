<?php

namespace App\Models;

use App\Enums\LetterStatus;
use App\Enums\LetterType;
use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LetterRequest extends Model
{
    use HasFactory;

    protected $fillable = ['phone', 'resident_id', 'type', 'purpose', 'status', 'notes'];

    protected function casts(): array
    {
        return ['type' => LetterType::class, 'status' => LetterStatus::class];
    }

    public function setPhoneAttribute(?string $value): void
    {
        $this->attributes['phone'] = PhoneNumber::normalize($value);
    }

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereIn('status', [LetterStatus::DIAJUKAN->value, LetterStatus::DISETUJUI->value]);
    }
}
