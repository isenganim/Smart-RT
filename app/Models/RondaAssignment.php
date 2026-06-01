<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RondaAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'ronda_schedule_id',
        'resident_id',
        'checked_in_at',
    ];

    protected function casts(): array
    {
        return ['checked_in_at' => 'datetime'];
    }

    public function rondaSchedule(): BelongsTo
    {
        return $this->belongsTo(RondaSchedule::class);
    }

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    public function hasCheckedIn(): bool
    {
        return $this->checked_in_at !== null;
    }
}
