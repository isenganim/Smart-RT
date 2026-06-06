<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\CashTransaction;
use App\Models\RondaAssignment;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class DendaService
{
    public const AMOUNT = 5000;

    public function candidates(CarbonInterface $date): Collection
    {
        return RondaAssignment::query()
            ->with('resident.household')
            ->whereNull('checked_in_at')
            ->whereHas('rondaSchedule', fn ($query) => $query->whereDate('date', $date->toDateString()))
            ->get();
    }

    public function fine(RondaAssignment $assignment, ?User $actor = null): CashTransaction
    {
        if ($assignment->hasCheckedIn()) {
            throw new \InvalidArgumentException('Tidak bisa mendenda warga yang sudah check-in.');
        }

        $assignment->loadMissing('resident', 'rondaSchedule');
        $date = $assignment->rondaSchedule->date->toDateString();

        $existing = CashTransaction::query()
            ->denda()
            ->where('resident_id', $assignment->resident_id)
            ->whereDate('date', $date)
            ->first();

        if ($existing) {
            return $existing;
        }

        return CashTransaction::create([
            'date' => $date,
            'household_id' => $assignment->resident?->household_id,
            'resident_id' => $assignment->resident_id,
            'type' => TransactionType::DENDA,
            'amount' => self::AMOUNT,
            'status' => 'lunas',
            'source' => 'denda_review',
            'recorded_by' => $actor?->id,
        ]);
    }
}
