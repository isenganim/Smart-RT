<?php

use App\Models\CashTransaction;
use App\Models\Household;
use App\Models\Resident;
use App\Models\RondaScanSession;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Volt\Volt;

beforeEach(function () {
    RateLimiter::clear('scan-unlock:127.0.0.1');
    $this->session = RondaScanSession::factory()->active()->create(['date' => today(), 'pin' => '654321']);
    $this->household = Household::factory()->create(['qr_token' => 'HOUSE-TOKEN-1', 'head_name' => 'Budi', 'is_active' => true]);
    $this->resident = Resident::factory()->create(['phone' => '81234567890', 'is_active' => true]);
});

it('serves the scan page without login', function () {
    $this->get('/scan-iuran')
        ->assertOk()
        ->assertSee('Scan Iuran')
        ->assertSee('type="password"', false)
        ->assertSee('inputmode="numeric"', false);
});

it('does not show scanner controls on the locked page', function () {
    $this->get('/scan-iuran')
        ->assertOk()
        ->assertDontSee('Mulai Kamera')
        ->assertDontSee('data-iuran-scanner', false);
});

it('unlocks the scan mode with a valid pin', function () {
    Volt::test('portal.scan')
        ->set('phone', '081234567890')
        ->set('pin', '654321')
        ->call('unlock')
        ->assertSet('unlocked', true);
});

it('shows scanner controls and manual fallback after unlock', function () {
    Volt::test('portal.scan')
        ->set('phone', '081234567890')
        ->set('pin', '654321')
        ->call('unlock')
        ->assertSee('Mulai Kamera')
        ->assertSee('data-iuran-scanner', false)
        ->assertSee('iuran-qr-reader', false)
        ->assertSee('Masukkan Kode QR Rumah Secara Manual')
        ->assertSee('iuran-token-input', false);
});

it('guards scanner startup against overlapping initialization', function () {
    $source = file_get_contents(resource_path('js/app.js'));

    expect($source)
        ->toContain('let isStarting = false;')
        ->toContain('if (scanner || isStarting) return;')
        ->toContain('isStarting = true;')
        ->toContain('isStarting = false;');
});

it('rejects an expired pin', function () {
    $this->session->update(['ends_at' => now()->subHour(), 'starts_at' => now()->subHours(3)]);

    Volt::test('portal.scan')
        ->set('phone', '081234567890')
        ->set('pin', '654321')
        ->call('unlock')
        ->assertSet('unlocked', false)
        ->assertSee('kedaluwarsa');
});

it('records iuran when a valid token is scanned via manual input', function () {
    $component = Volt::test('portal.scan')
        ->set('phone', '081234567890')
        ->set('pin', '654321')
        ->call('unlock');

    $component->set('token', 'HOUSE-TOKEN-1')->call('scan')
        ->assertSee('Budi')
        ->assertSee('Lunas');

    expect(CashTransaction::query()->iuranHarian()->count())->toBe(1);
});

it('records iuran when a token is submitted via the camera-detected action', function () {
    $component = Volt::test('portal.scan')
        ->set('phone', '081234567890')
        ->set('pin', '654321')
        ->call('unlock');

    $component->call('scanDetectedToken', 'HOUSE-TOKEN-1')
        ->assertSee('Budi')
        ->assertSee('Lunas');

    expect(CashTransaction::query()->iuranHarian()->count())->toBe(1);
});

it('reports already paid on a duplicate scan via camera action', function () {
    $component = Volt::test('portal.scan')
        ->set('phone', '081234567890')
        ->set('pin', '654321')
        ->call('unlock');

    $component->call('scanDetectedToken', 'HOUSE-TOKEN-1');
    $component->call('scanDetectedToken', 'HOUSE-TOKEN-1')
        ->assertSee('sudah tercatat');

    expect(CashTransaction::query()->iuranHarian()->count())->toBe(1);
});

it('reports already paid on a duplicate scan', function () {
    $component = Volt::test('portal.scan')
        ->set('phone', '081234567890')
        ->set('pin', '654321')
        ->call('unlock');
    $component->set('token', 'HOUSE-TOKEN-1')->call('scan');
    $component->set('token', 'HOUSE-TOKEN-1')->call('scan')
        ->assertSee('sudah tercatat');

    expect(CashTransaction::query()->iuranHarian()->count())->toBe(1);
});

it('rate limits repeated unlock attempts', function () {
    $component = Volt::test('portal.scan');

    foreach (range(1, 6) as $ignored) {
        $component->set('phone', '081234567890')
            ->set('pin', '000000')
            ->call('unlock');
    }

    $component->assertSee('Terlalu banyak percobaan');
});
