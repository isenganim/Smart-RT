<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\CashTransaction;
use App\Models\Household;
use App\Models\RondaAssignment;
use App\Support\Setting;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class KasReport
{
    /**
     * Full monthly financial statement: opening balance, income, expenses,
     * net movement, and closing balance for the given year/month.
     *
     * @return array{
     *     period: CarbonImmutable,
     *     opening_balance: int,
     *     income: array{iuran: int, denda: int, koreksi: int, total_in: int},
     *     expenses: Collection,
     *     total_out: int,
     *     net: int,
     *     closing_balance: int,
     *     daily: Collection
     * }
     */
    public function monthlyStatement(int $year, int $month): array
    {
        $start = CarbonImmutable::create($year, $month, 1)->startOfMonth();
        $end = $start->endOfMonth();

        $rows = CashTransaction::query()
            ->active()
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get();

        $iuran = (int) $rows->where('type', TransactionType::IURAN_HARIAN)->sum('amount');
        $denda = (int) $rows->where('type', TransactionType::DENDA)->sum('amount');
        $koreksi = (int) $rows->where('type', TransactionType::KOREKSI)->sum('amount');

        $expenseRows = $rows->where('type', TransactionType::PENGELUARAN);
        $totalOut = (int) abs($expenseRows->sum('amount'));

        $expenses = $expenseRows
            ->groupBy(fn (CashTransaction $tx) => $tx->category ?: 'Lainnya')
            ->map(fn (Collection $group, string $category) => [
                'category' => $category,
                'amount' => (int) abs($group->sum('amount')),
            ])
            ->sortByDesc('amount')
            ->values();

        $net = (int) $rows->sum('amount');
        $openingBalance = $this->balanceBefore($start);

        $daily = $rows
            ->groupBy(fn (CashTransaction $tx) => $tx->date->toDateString())
            ->map(fn (Collection $group, string $date) => [
                'date' => $date,
                'amount' => (int) $group->sum('amount'),
            ])
            ->sortKeys()
            ->values();

        return [
            'period' => $start,
            'opening_balance' => $openingBalance,
            'income' => [
                'iuran' => $iuran,
                'denda' => $denda,
                'koreksi' => $koreksi,
                'total_in' => $iuran + $denda + $koreksi,
            ],
            'expenses' => $expenses,
            'total_out' => $totalOut,
            'net' => $net,
            'closing_balance' => $openingBalance + $net,
        ] + ['daily' => $daily];
    }

    /**
     * Cash balance carried into the given date: the configured opening balance
     * plus every active transaction on/after the opening date and before $before.
     */
    public function balanceBefore(CarbonInterface $before): int
    {
        $openingBalance = (int) Setting::get('kas_opening_balance', 0);
        $openingDate = Setting::get('kas_opening_date');

        $query = CashTransaction::query()
            ->active()
            ->whereDate('date', '<', $before->toDateString());

        if (! empty($openingDate)) {
            $query->whereDate('date', '>=', $openingDate);
        }

        return $openingBalance + (int) $query->sum('amount');
    }

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
