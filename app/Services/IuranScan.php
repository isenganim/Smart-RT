<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\CashTransaction;
use App\Models\Household;
use App\Models\RondaScanSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class IuranScan
{
    public const AMOUNT = 500;

    public function record(RondaScanSession $session, ?string $token, ?User $actor = null): IuranResult
    {
        $token = trim((string) $token);

        if ($token === '') {
            return IuranResult::error('QR rumah tidak dikenali.');
        }

        $household = Household::query()
            ->where('qr_token', $token)
            ->where('is_active', true)
            ->first();

        if (! $household) {
            return IuranResult::error('QR rumah tidak dikenali.');
        }

        return DB::transaction(function () use ($session, $household, $actor) {
            $existing = CashTransaction::query()
                ->iuranHarian()
                ->where('household_id', $household->id)
                ->whereDate('date', $session->date->toDateString())
                ->where('status', 'lunas')
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return IuranResult::alreadyPaid($household, $existing);
            }

            $transaction = CashTransaction::query()->create([
                'date' => $session->date->toDateString(),
                'household_id' => $household->id,
                'ronda_scan_session_id' => $session->id,
                'type' => TransactionType::IURAN_HARIAN,
                'amount' => self::AMOUNT,
                'status' => 'lunas',
                'source' => 'scan',
                'recorded_by' => $actor?->id,
            ]);

            return IuranResult::recorded($household, $transaction);
        });
    }
}
