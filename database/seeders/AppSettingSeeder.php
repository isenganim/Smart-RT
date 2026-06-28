<?php

namespace Database\Seeders;

use App\Models\AppSetting;
use Illuminate\Database\Seeder;

class AppSettingSeeder extends Seeder
{
    public function run(): void
    {
        AppSetting::firstOrCreate(['key' => 'iuran_amount'], ['value' => '500']);
        AppSetting::firstOrCreate(['key' => 'denda_amount'], ['value' => '5000']);
        AppSetting::firstOrCreate(['key' => 'kas_opening_balance'], ['value' => '250000']);
        AppSetting::firstOrCreate(['key' => 'kas_opening_date'], ['value' => '']);
    }
}
