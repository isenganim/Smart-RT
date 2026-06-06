<?php

namespace App\Services;

use App\Models\CashTransaction;
use App\Models\Household;

class IuranResult
{
    public function __construct(
        public readonly string $status,
        public readonly ?Household $household = null,
        public readonly ?CashTransaction $transaction = null,
        public readonly ?string $message = null,
    ) {}

    public static function recorded(Household $household, CashTransaction $transaction): self
    {
        return new self(status: 'paid', household: $household, transaction: $transaction);
    }

    public static function alreadyPaid(Household $household, CashTransaction $transaction): self
    {
        return new self(
            status: 'already_paid',
            household: $household,
            transaction: $transaction,
            message: 'Iuran rumah ini sudah tercatat hari ini.',
        );
    }

    public static function error(string $message): self
    {
        return new self(status: 'error', message: $message);
    }

    public function paid(): bool
    {
        return $this->status === 'paid';
    }
}
