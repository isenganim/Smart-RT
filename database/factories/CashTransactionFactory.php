<?php

namespace Database\Factories;

use App\Enums\TransactionType;
use App\Models\CashTransaction;
use App\Models\Household;
use Illuminate\Database\Eloquent\Factories\Factory;

class CashTransactionFactory extends Factory
{
    protected $model = CashTransaction::class;

    public function definition(): array
    {
        return [
            'date' => today()->toDateString(),
            'household_id' => Household::factory(),
            'resident_id' => null,
            'ronda_scan_session_id' => null,
            'type' => TransactionType::IURAN_HARIAN,
            'amount' => 500,
            'status' => 'lunas',
            'source' => 'scan',
            'recorded_by' => null,
            'reason' => null,
        ];
    }
}
