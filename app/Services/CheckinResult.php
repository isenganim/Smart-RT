<?php

namespace App\Services;

use App\Models\RondaAssignment;

class CheckinResult
{
    public function __construct(
        public readonly bool $ok,
        public readonly ?RondaAssignment $assignment = null,
        public readonly ?string $message = null,
    ) {}

    public static function done(RondaAssignment $assignment): self
    {
        return new self(ok: true, assignment: $assignment);
    }

    public static function fail(string $message): self
    {
        return new self(ok: false, message: $message);
    }

    public function success(): bool
    {
        return $this->ok;
    }
}
