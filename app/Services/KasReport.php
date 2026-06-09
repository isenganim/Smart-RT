<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\CashTransaction;
use App\Models\Household;
use App\Models\RondaAssignment;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class KasReport
{
    public function daily(CarbonInterface $date): array
    {
        $rows = CashTransaction::query()
            ->active()
            ->whereDate('date', $date->toDateString())
            ->get();

        $iuran = (int) $rows->where('type', TransactionType::IURAN_HARIAN)->sum('amount');
        $denda = (int) $rows->where('type', TransactionType::DENDA)->sum('amount');
        $koreksi = (int) $rows->where('type', TransactionType::KOREKSI)->sum('amount');

        return [
            'iuran' => $iuran,
            'denda' => $denda,
            'koreksi' => $koreksi,
            'total' => (int) $rows->sum('amount'),
        ];
    }

    public function rangeTotal(CarbonInterface $from, CarbonInterface $to): int
    {
        return (int) CashTransaction::query()
            ->active()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString().' 23:59:59'])
            ->sum('amount');
    }

    public function unpaidHouseholds(CarbonInterface $date): Collection
    {
        return Household::query()
            ->where('is_active', true)
            ->whereDoesntHave('cashTransactions', function ($query) use ($date) {
                $query->whereNull('cancelled_at')
                    ->where('type', TransactionType::IURAN_HARIAN->value)
                    ->whereDate('date', $date->toDateString());
            })
            ->orderBy('house_number')
            ->get();
    }

    public function missingCheckins(CarbonInterface $date): Collection
    {
        return RondaAssignment::query()
            ->with('resident.household')
            ->whereNull('checked_in_at')
            ->whereHas('rondaSchedule', fn ($query) => $query->whereDate('date', $date->toDateString()))
            ->get();
    }
}
