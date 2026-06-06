<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\CashTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TransactionCorrection
{
    public function cancel(CashTransaction $transaction, string $reason, User $actor): CashTransaction
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new \InvalidArgumentException('Alasan koreksi wajib diisi.');
        }

        if ($transaction->isCancelled()) {
            throw new \InvalidArgumentException('Transaksi ini sudah dibatalkan.');
        }

        return DB::transaction(function () use ($transaction, $reason, $actor) {
            $transaction->update([
                'cancelled_at' => now(),
                'cancelled_by' => $actor->id,
            ]);

            return CashTransaction::create([
                'date' => $transaction->date->toDateString(),
                'household_id' => $transaction->household_id,
                'resident_id' => $transaction->resident_id,
                'ronda_scan_session_id' => $transaction->ronda_scan_session_id,
                'reverses_id' => $transaction->id,
                'type' => TransactionType::KOREKSI,
                'amount' => -1 * $transaction->amount,
                'status' => 'koreksi',
                'source' => 'koreksi',
                'recorded_by' => $actor->id,
                'reason' => $reason,
            ]);
        });
    }
}
