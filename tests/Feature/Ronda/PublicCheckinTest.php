<?php

use App\Models\Household;
use App\Models\Resident;
use App\Models\RondaAssignment;
use App\Models\RondaSchedule;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Volt\Volt;

beforeEach(function () {
    RateLimiter::clear('portal-checkin:127.0.0.1');
    $this->household = Household::factory()->create();
    $this->schedule = RondaSchedule::factory()->create(['date' => today()->toDateString()]);
});

it('shows the public ronda schedule without login', function () {
    $this->get('/jadwal-ronda')
        ->assertOk()
        ->assertSee('Jadwal Ronda');
});

it('checks in a scheduled resident from the portal', function () {
    $resident = Resident::factory()->for($this->household)->create([
        'name' => 'Andi',
        'phone' => '81234567890',
        'is_active' => true,
    ]);
    RondaAssignment::factory()->for($this->schedule)->for($resident)->create();

    Volt::test('portal.checkin')
        ->set('phone', '0812-3456-7890')
        ->call('submit')
        ->assertSee('Check-in Berhasil');

    expect(RondaAssignment::query()->whereNotNull('checked_in_at')->count())->toBe(1);
});

it('rejects a resident not scheduled today', function () {
    Resident::factory()->for($this->household)->create([
        'phone' => '81234567890',
        'is_active' => true,
    ]);

    Volt::test('portal.checkin')
        ->set('phone', '81234567890')
        ->call('submit')
        ->assertSee('tidak terjadwal');
});

it('rate limits repeated check-in attempts', function () {
    $component = Volt::test('portal.checkin');

    foreach (range(1, 6) as $ignored) {
        $component->set('phone', '0899-0000-0000')->call('submit');
    }

    $component->assertSee('Terlalu banyak percobaan');
});
