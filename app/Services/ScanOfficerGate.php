<?php

namespace App\Services;

use App\Models\Resident;
use App\Models\RondaAssignment;
use App\Models\RondaScanSession;

class ScanOfficerGate
{
    public function authorize(Resident $resident, RondaScanSession $session): ScanOfficerResult
    {
        $assignment = RondaAssignment::query()
            ->where('resident_id', $resident->id)
            ->whereHas('rondaSchedule', fn ($query) => $query
                ->whereDate('date', $session->date->toDateString()))
            ->first();

        if (! $assignment) {
            return ScanOfficerResult::deny('Nomor HP tidak terjadwal ronda untuk sesi ini.');
        }

        if (! $assignment->hasCheckedIn()) {
            return ScanOfficerResult::deny('Silakan absen ronda terlebih dahulu sebelum membuka mode pindai.');
        }

        return ScanOfficerResult::allow($assignment);
    }
}
