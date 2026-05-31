<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Household;
use App\Models\Resident;
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
    }
}
