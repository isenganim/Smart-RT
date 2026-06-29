<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class RondaSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'notes',
    ];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    /**
     * Lifecycle status derived from the schedule date and attendance.
     *
     * upcoming  — tanggal masih di depan
     * ongoing   — tanggal hari ini
     * done      — tanggal lewat & semua petugas check-in
     * missed    — tanggal lewat & ada petugas tidak check-in (atau tanpa petugas)
     */
    public function status(): string
    {
        $date = $this->date->copy()->startOfDay();
        $today = Carbon::today();

        if ($date->gt($today)) {
            return 'upcoming';
        }

        if ($date->equalTo($today)) {
            return 'ongoing';
        }

        $assignments = $this->resolveAssignmentsCount();
        $checkedIn = $this->resolveCheckedInCount();

        if ($assignments > 0 && $checkedIn >= $assignments) {
            return 'done';
        }

        return 'missed';
    }

    /**
     * Jumlah petugas terjadwal yang tidak pernah check-in.
     */
    public function absentCount(): int
    {
        return max(0, $this->resolveAssignmentsCount() - $this->resolveCheckedInCount());
    }

    protected function resolveAssignmentsCount(): int
    {
        return (int) ($this->assignments_count ?? $this->assignments()->count());
    }

    protected function resolveCheckedInCount(): int
    {
        return (int) ($this->checked_in_count ?? $this->assignments()->whereNotNull('checked_in_at')->count());
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(RondaAssignment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
