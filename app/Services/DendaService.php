<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\CashTransaction;
use App\Models\RondaAssignment;
use App\Models\User;
use App\Support\Audit;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class DendaService
{
    public const AMOUNT = 5000;

    public function candidates(CarbonInterface $date): Collection
    {
        $finedResidentIds = CashTransaction::query()
            ->active()
            ->denda()
            ->whereDate('date', $date->toDateString())
            ->pluck('resident_id');

        return RondaAssignment::query()
            ->with('resident.household')
            ->whereNull('checked_in_at')
            ->whereNotIn('resident_id', $finedResidentIds)
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
            ->active()
            ->denda()
            ->where('resident_id', $assignment->resident_id)
            ->whereDate('date', $date)
            ->first();

        if ($existing) {
            Audit::record($actor, 'kas.denda.skipped_existing', 'cash_transaction', $existing->id, [
                'resident_id' => $assignment->resident_id,
                'household_id' => $assignment->resident?->household_id,
                'ronda_assignment_id' => $assignment->id,
                'date' => $date,
                'amount' => self::AMOUNT,
            ]);

            return $existing;
        }

        $transaction = CashTransaction::create([
            'date' => $date,
            'household_id' => $assignment->resident?->household_id,
            'resident_id' => $assignment->resident_id,
            'type' => TransactionType::DENDA,
            'amount' => self::AMOUNT,
            'status' => 'lunas',
            'source' => 'denda_review',
            'recorded_by' => $actor?->id,
        ]);

        $action = $transaction->wasRecentlyCreated ? 'kas.denda.created' : 'kas.denda.skipped_existing';

        Audit::record($actor, $action, 'cash_transaction', $transaction->id, [
            'resident_id' => $assignment->resident_id,
            'household_id' => $assignment->resident?->household_id,
            'ronda_assignment_id' => $assignment->id,
            'date' => $date,
            'amount' => self::AMOUNT,
        ]);

        return $transaction;
    }
}
