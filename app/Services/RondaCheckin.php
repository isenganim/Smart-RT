<?php

namespace App\Services;

use App\Models\RondaAssignment;
use App\Models\RondaSchedule;
use App\Support\Audit;
use Carbon\CarbonInterface;

class RondaCheckin
{
    public function __construct(
        protected ResidentLookup $lookup,
    ) {}

    public function checkIn(?string $rawPhone, CarbonInterface $date): CheckinResult
    {
        $lookup = $this->lookup->resolve($rawPhone);

        if (! $lookup->found()) {
            return CheckinResult::fail($lookup->message);
        }

        $schedule = RondaSchedule::query()
            ->whereDate('date', $date->toDateString())
            ->first();

        if (! $schedule) {
            return CheckinResult::fail('Belum ada jadwal ronda untuk tanggal ini.');
        }

        $assignment = RondaAssignment::query()
            ->where('ronda_schedule_id', $schedule->id)
            ->where('resident_id', $lookup->resident->id)
            ->first();

        if (! $assignment) {
            return CheckinResult::fail('Nomor HP tidak terjadwal ronda hari ini.');
        }

        if ($assignment->hasCheckedIn()) {
            return CheckinResult::fail('Anda sudah check-in untuk tanggal ini.');
        }

        $checkedInAt = now();

        $updated = RondaAssignment::query()
            ->whereKey($assignment->id)
            ->whereNull('checked_in_at')
            ->update(['checked_in_at' => $checkedInAt]);

        if ($updated !== 1) {
            return CheckinResult::fail('Anda sudah check-in untuk tanggal ini.');
        }

        $assignment->refresh();

        Audit::record(auth()->user(), 'ronda.assignment.checked_in', 'ronda_assignment', $assignment->id, [
            'ronda_schedule_id' => $schedule->id,
            'resident_id' => $lookup->resident->id,
            'checked_in_at' => $checkedInAt->toIso8601String(),
        ]);

        return CheckinResult::done($assignment);
    }
}
