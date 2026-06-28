<?php

namespace Database\Seeders;

use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Models\CashTransaction;
use App\Models\Household;
use App\Models\Resident;
use App\Models\RondaSchedule;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->firstOrCreate(
            ['email' => 'admin@smartrt.test'],
            ['name' => 'Admin RT', 'password' => Hash::make('password'), 'role' => UserRole::ADMIN_RT],
        );

        User::query()->firstOrCreate(
            ['email' => 'bendahara@smartrt.test'],
            ['name' => 'Bendahara', 'password' => Hash::make('password'), 'role' => UserRole::BENDAHARA],
        );

        Household::factory()
            ->count(5)
            ->create()
            ->each(fn (Household $household) => Resident::factory()->count(2)->for($household)->create());

        $residents = Resident::query()->where('is_active', true)->take(4)->get();
        if ($residents->count() >= 2) {
            $scheduleToday = RondaSchedule::query()->firstOrCreate(
                ['date' => today()->toDateString()],
                ['notes' => 'Ronda Malam Wajib']
            );
            $scheduleToday->assignments()->firstOrCreate(['resident_id' => $residents[0]->id]);
            $scheduleToday->assignments()->firstOrCreate(['resident_id' => $residents[1]->id]);

            if ($residents->count() >= 3) {
                $scheduleTomorrow = RondaSchedule::query()->firstOrCreate(
                    ['date' => today()->addDay()->toDateString()],
                    ['notes' => 'Ronda Akhir Pekan']
                );
                $scheduleTomorrow->assignments()->firstOrCreate(['resident_id' => $residents[2]->id]);
            }
        }

        $this->seedCashTransactions($residents);
    }

    private function seedCashTransactions($residents): void
    {
        if (CashTransaction::query()->exists()) {
            return;
        }

        $households = Household::query()->where('is_active', true)->get();

        if ($households->isEmpty()) {
            return;
        }

        // Iuran harian Rp500 selama 14 hari terakhir; sebagian rumah sengaja
        // dilewati per hari agar muncul daftar "rumah belum bayar".
        foreach (range(0, 13) as $dayOffset) {
            $date = today()->copy()->subDays($dayOffset)->toDateString();

            foreach ($households as $index => $household) {
                if (($dayOffset + $index) % 5 === 0) {
                    continue;
                }

                CashTransaction::create([
                    'date' => $date,
                    'household_id' => $household->id,
                    'type' => TransactionType::IURAN_HARIAN,
                    'amount' => 500,
                    'status' => 'lunas',
                    'source' => 'scan',
                ]);
            }
        }

        // Denda ronda Rp5.000 untuk beberapa warga yang absen.
        foreach ($residents->take(2) as $resident) {
            CashTransaction::create([
                'date' => today()->copy()->subDays(3)->toDateString(),
                'household_id' => $resident->household_id,
                'resident_id' => $resident->id,
                'type' => TransactionType::DENDA,
                'amount' => 5000,
                'status' => 'lunas',
                'source' => 'denda_review',
            ]);
        }

        // Pengeluaran kas untuk operasional RT.
        $expenses = [
            ['amount' => 150000, 'category' => 'Kebersihan', 'reason' => 'Honor petugas kebersihan', 'days' => 6],
            ['amount' => 85000, 'category' => 'Konsumsi Ronda', 'reason' => 'Kopi dan snack ronda', 'days' => 4],
            ['amount' => 40000, 'category' => 'Perbaikan', 'reason' => 'Ganti lampu pos ronda', 'days' => 2],
        ];

        foreach ($expenses as $expense) {
            CashTransaction::create([
                'date' => today()->copy()->subDays($expense['days'])->toDateString(),
                'type' => TransactionType::PENGELUARAN,
                'amount' => -1 * $expense['amount'],
                'status' => 'keluar',
                'source' => 'manual',
                'category' => $expense['category'],
                'reason' => $expense['reason'],
            ]);
        }
    }
}
