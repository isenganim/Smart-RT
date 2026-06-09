<?php

namespace App\Models;

use App\Enums\ReportStatus;
use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends Model
{
    use HasFactory;

    protected $fillable = ['phone', 'resident_id', 'category', 'description', 'status', 'notes'];

    protected function casts(): array
    {
        return ['status' => ReportStatus::class];
    }

    public function setPhoneAttribute(?string $value): void
    {
        $this->attributes['phone'] = PhoneNumber::normalize($value);
    }

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [ReportStatus::BARU->value, ReportStatus::DIPROSES->value]);
    }
}
