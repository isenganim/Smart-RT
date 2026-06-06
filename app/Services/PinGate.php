<?php

namespace App\Services;

use App\Models\RondaScanSession;

class PinGate
{
    public function unlock(?string $pin): PinGateResult
    {
        $pin = trim((string) $pin);

        if ($pin === '') {
            return PinGateResult::deny('PIN wajib diisi.');
        }

        $session = RondaScanSession::query()->where('pin', $pin)->first();

        if (! $session) {
            return PinGateResult::deny('PIN tidak ditemukan.');
        }

        if (! $session->isActive()) {
            return PinGateResult::deny('PIN sudah kedaluwarsa.');
        }

        return PinGateResult::open($session);
    }
}
