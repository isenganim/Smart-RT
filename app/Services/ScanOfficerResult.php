<?php

namespace App\Services;

use App\Models\RondaAssignment;

class ScanOfficerResult
{
    public function __construct(
        public readonly bool $allowed,
        public readonly ?RondaAssignment $assignment = null,
        public readonly ?string $message = null,
    ) {}

    public static function allow(RondaAssignment $assignment): self
    {
        return new self(allowed: true, assignment: $assignment);
    }

    public static function deny(string $message): self
    {
        return new self(allowed: false, message: $message);
    }

    public function ok(): bool
    {
        return $this->allowed;
    }
}
