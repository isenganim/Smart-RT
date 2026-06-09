<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\CashTransaction;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;

class TransactionCorrection
{
    public function cancel(CashTransaction $transaction, string $reason, User $actor): CashTransaction
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new \InvalidArgumentException('Alasan koreksi wajib diisi.');
        }

        return DB::transaction(function () use ($transaction, $reason, $actor) {
            $locked = CashTransaction::query()
                ->whereKey($transaction->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->isCancelled()) {
                throw new \InvalidArgumentException('Transaksi ini sudah dibatalkan.');
            }

            if ($locked->type === TransactionType::KOREKSI) {
                throw new \InvalidArgumentException('Transaksi koreksi tidak bisa dibatalkan.');
            }

            $locked->update([
                'cancelled_at' => now(),
                'cancelled_by' => $actor->id,
                'reason' => $reason,
            ]);

            $koreksi = CashTransaction::create([
                'date' => $locked->date->toDateString(),
                'household_id' => $locked->household_id,
                'resident_id' => $locked->resident_id,
                'ronda_scan_session_id' => $locked->ronda_scan_session_id,
                'reverses_id' => $locked->id,
                'type' => TransactionType::KOREKSI,
                'amount' => -1 * $locked->amount,
                'status' => 'koreksi',
                'source' => 'koreksi',
                'recorded_by' => $actor->id,
                'reason' => $reason,
            ]);

            Audit::record($actor, 'kas.transaction.cancelled', 'cash_transaction', $locked->id, [
                'koreksi_id' => $koreksi->id,
                'reason' => $reason,
            ]);

            return $koreksi;
        });
    }
}
