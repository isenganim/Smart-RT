<?php

namespace Database\Seeders;

use App\Enums\UserRole;
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
    }
}
