<?php

namespace App\Services;

use App\Models\RondaAssignment;
use App\Models\RondaSchedule;
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

        $assignment->update(['checked_in_at' => now()]);

        return CheckinResult::done($assignment);
    }
}
