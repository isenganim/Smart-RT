<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\CashTransaction;
use App\Models\User;
use App\Support\Audit;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class ExpenseService
{
    /**
     * Suggested expense categories. Free text is also accepted.
     */
    public const CATEGORIES = [
        'Kebersihan',
        'Keamanan',
        'Konsumsi Ronda',
        'Perbaikan',
        'Lainnya',
    ];

    public function record(
        CarbonInterface $date,
        int $amount,
        string $description,
        ?string $category,
        ?User $actor = null,
    ): CashTransaction {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Nominal pengeluaran harus lebih dari nol.');
        }

        if (trim($description) === '') {
            throw new \InvalidArgumentException('Keterangan pengeluaran wajib diisi.');
        }

        $category = $category !== null && trim($category) !== '' ? trim($category) : null;

        return DB::transaction(function () use ($date, $amount, $description, $category, $actor) {
            $transaction = CashTransaction::create([
                'date' => $date->toDateString(),
                'type' => TransactionType::PENGELUARAN,
                'amount' => -1 * $amount,
                'status' => 'keluar',
                'source' => 'manual',
                'category' => $category,
                'recorded_by' => $actor?->id,
                'reason' => trim($description),
            ]);

            Audit::record($actor, 'kas.expense_recorded', 'cash_transaction', $transaction->id, [
                'date' => $date->toDateString(),
                'amount' => $amount,
                'category' => $category,
                'description' => trim($description),
            ]);

            return $transaction;
        });
    }
}
