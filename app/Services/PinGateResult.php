<?php

namespace App\Services;

use App\Models\RondaScanSession;

class PinGateResult
{
    public function __construct(
        public readonly ?RondaScanSession $session = null,
        public readonly ?string $message = null,
    ) {}

    public static function open(RondaScanSession $session): self
    {
        return new self(session: $session);
    }

    public static function deny(string $message): self
    {
        return new self(message: $message);
    }

    public function ok(): bool
    {
        return $this->session !== null;
    }
}
