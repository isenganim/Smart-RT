<?php

namespace App\Services;

use App\Models\VoteBallot;

class BallotResult
{
    public function __construct(
        public readonly bool $ok,
        public readonly ?VoteBallot $ballot = null,
        public readonly ?string $message = null,
    ) {}

    public static function done(VoteBallot $ballot): self
    {
        return new self(true, $ballot);
    }

    public static function fail(string $message): self
    {
        return new self(false, message: $message);
    }

    public function success(): bool
    {
        return $this->ok;
    }
}
