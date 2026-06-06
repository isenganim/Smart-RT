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

        $sessions = RondaScanSession::query()
            ->where('pin', $pin)
            ->latest('id')
            ->get();

        if ($sessions->isEmpty()) {
            return PinGateResult::deny('PIN tidak ditemukan.');
        }

        $session = $sessions->first(fn (RondaScanSession $session) => $session->isActive());

        if (! $session) {
            return PinGateResult::deny('PIN sudah kedaluwarsa.');
        }

        return PinGateResult::open($session);
    }
}
