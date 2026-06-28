<?php

use App\Enums\UserRole;
use App\Models\FeatureSetting;
use App\Support\Audit;
use App\Support\Feature;
use App\Support\Setting;
use Illuminate\Support\Facades\DB;
use function Livewire\Volt\{layout, mount, state, title};

layout('components.layouts.app');
title('Pengaturan');

state(['flags' => [], 'iuran_amount' => 500, 'denda_amount' => 5000, 'kas_opening_balance' => 0, 'kas_opening_date' => '', 'showConfirm' => false]);

mount(function () {
    abort_unless(auth()->user()->role === UserRole::ADMIN_RT, 403);

    $stored = FeatureSetting::query()
        ->whereIn('key', Feature::OPTIONAL_FEATURES)
        ->pluck('is_enabled', 'key')
        ->toArray();

    $this->flags = collect(Feature::OPTIONAL_FEATURES)
        ->mapWithKeys(fn ($key) => [$key => $stored[$key] ?? true])
        ->toArray();

    $this->iuran_amount = (int) Setting::get('iuran_amount', 500);
    $this->denda_amount = (int) Setting::get('denda_amount', 5000);
    $this->kas_opening_balance = (int) Setting::get('kas_opening_balance', 0);
    $this->kas_opening_date = (string) Setting::get('kas_opening_date', '');
});

$toggle = function (string $key) {
    if (in_array($key, Feature::OPTIONAL_FEATURES, true)) {
        $this->flags[$key] = ! ($this->flags[$key] ?? true);
    }
};

$requestSave = function () {
    $this->validate([
        'iuran_amount' => ['required', 'integer', 'min:1', 'max:1000000'],
        'denda_amount' => ['required', 'integer', 'min:1', 'max:1000000'],
        'kas_opening_balance' => ['required', 'integer', 'min:0', 'max:1000000000'],
        'kas_opening_date' => ['nullable', 'date'],
    ]);

    $this->showConfirm = true;
};

$cancelSave = function () {
    $this->showConfirm = false;
};

$save = function () {
    abort_unless(auth()->user()->role === UserRole::ADMIN_RT, 403);

    $this->validate([
        'iuran_amount' => ['required', 'integer', 'min:1', 'max:1000000'],
        'denda_amount' => ['required', 'integer', 'min:1', 'max:1000000'],
        'kas_opening_balance' => ['required', 'integer', 'min:0', 'max:1000000000'],
        'kas_opening_date' => ['nullable', 'date'],
    ]);

    DB::transaction(function () {
        foreach (Feature::OPTIONAL_FEATURES as $key) {
            $setting = FeatureSetting::firstOrCreate(['key' => $key], ['is_enabled' => true]);
            $target = (bool) ($this->flags[$key] ?? true);

            if ($setting->is_enabled !== $target) {
                $previous = $setting->is_enabled;
                $setting->update(['is_enabled' => $target, 'updated_by' => auth()->id()]);

                Audit::record(
                    auth()->user(),
                    $target ? 'feature.enabled' : 'feature.disabled',
                    'feature_setting',
                    $setting->id,
                    ['key' => $key, 'from' => $previous, 'to' => $target],
                );
            }
        }

        foreach (['iuran_amount', 'denda_amount'] as $key) {
            $previous = (int) Setting::get($key, $key === 'iuran_amount' ? 500 : 5000);
            $value = (int) $this->{$key};

            if ($previous !== $value) {
                Setting::set($key, (string) $value, auth()->id());
            }
        }

        $previousBalance = (int) Setting::get('kas_opening_balance', 0);
        if ($previousBalance !== (int) $this->kas_opening_balance) {
            Setting::set('kas_opening_balance', (string) (int) $this->kas_opening_balance, auth()->id());
        }

        $previousDate = (string) Setting::get('kas_opening_date', '');
        $targetDate = (string) $this->kas_opening_date;
        if ($previousDate !== $targetDate) {
            Setting::set('kas_opening_date', $targetDate, auth()->id());
        }
    });

    Feature::flush();
    $this->showConfirm = false;
    session()->flash('success', 'Pengaturan berhasil disimpan.');
};

?>

@php
    $meta = [
        'ronda' => ['label' => 'Ronda', 'desc' => 'Jadwal ronda, sesi scan, dan denda.'],
        'kas' => ['label' => 'Kas', 'desc' => 'Rekap dan transaksi iuran kas harian.'],
        'announcements' => ['label' => 'Pengumuman', 'desc' => 'Pengumuman RT di dashboard dan portal.'],
        'reports' => ['label' => 'Laporan Warga', 'desc' => 'Laporan dari warga.'],
        'letters' => ['label' => 'Surat Pengantar', 'desc' => 'Pengajuan surat pengantar RT.'],
        'voting' => ['label' => 'Voting', 'desc' => 'Pemungutan suara warga.'],
        'inventory' => ['label' => 'Inventaris', 'desc' => 'Data dan peminjaman inventaris RT.'],
    ];
@endphp

<div class="space-y-8">
    <div>
        <h1 class="text-xl font-semibold text-ink">Pengaturan</h1>
        <p class="mt-1 text-sm text-ink-mute">Konfigurasi nominal dan modul yang digunakan di RT ini.</p>
    </div>

    @if (session('success'))
        <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800" role="alert">
            {{ session('success') }}
        </div>
    @endif

    <form wire:submit="requestSave" class="space-y-8">

        {{-- Nominal --}}
        <div class="space-y-3">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-ink-mute">Nominal</h2>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="rounded-lg border border-hairline bg-white px-5 py-4">
                    <label for="iuran_amount" class="block text-sm font-medium text-ink">Iuran Harian (Rp)</label>
                    <p class="mt-0.5 text-xs text-ink-mute">Nominal kas yang dipungut per rumah setiap hari.</p>
                    <div class="mt-3">
                        <input
                            type="number"
                            id="iuran_amount"
                            wire:model="iuran_amount"
                            min="1"
                            max="1000000"
                            class="block w-full rounded-md border border-hairline-input px-3 py-2 text-sm text-ink focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                        >
                        @error('iuran_amount') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="rounded-lg border border-hairline bg-white px-5 py-4">
                    <label for="denda_amount" class="block text-sm font-medium text-ink">Denda Absen Ronda (Rp)</label>
                    <p class="mt-0.5 text-xs text-ink-mute">Nominal denda bagi warga yang tidak hadir ronda.</p>
                    <div class="mt-3">
                        <input
                            type="number"
                            id="denda_amount"
                            wire:model="denda_amount"
                            min="1"
                            max="1000000"
                            class="block w-full rounded-md border border-hairline-input px-3 py-2 text-sm text-ink focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                        >
                        @error('denda_amount') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="rounded-lg border border-hairline bg-white px-5 py-4">
                    <label for="kas_opening_balance" class="block text-sm font-medium text-ink">Saldo Awal Kas (Rp)</label>
                    <p class="mt-0.5 text-xs text-ink-mute">Saldo kas yang sudah ada sebelum dicatat di sistem.</p>
                    <div class="mt-3">
                        <input
                            type="number"
                            id="kas_opening_balance"
                            wire:model="kas_opening_balance"
                            min="0"
                            max="1000000000"
                            class="block w-full rounded-md border border-hairline-input px-3 py-2 text-sm text-ink focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                        >
                        @error('kas_opening_balance') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="rounded-lg border border-hairline bg-white px-5 py-4">
                    <label for="kas_opening_date" class="block text-sm font-medium text-ink">Tanggal Saldo Awal</label>
                    <p class="mt-0.5 text-xs text-ink-mute">Tanggal berlaku saldo awal. Kosongkan untuk menghitung dari awal.</p>
                    <div class="mt-3">
                        <input
                            type="date"
                            id="kas_opening_date"
                            wire:model="kas_opening_date"
                            class="block w-full rounded-md border border-hairline-input px-3 py-2 text-sm text-ink focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                        >
                        @error('kas_opening_date') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- Fitur --}}
        <div class="space-y-3">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-ink-mute">Modul Aktif</h2>
            <p class="text-xs text-ink-mute">Modul nonaktif disembunyikan dari menu dan portal warga.</p>

            <div class="space-y-3">
                @foreach (\App\Support\Feature::OPTIONAL_FEATURES as $feature)
                    @php $isOn = (bool) ($flags[$feature] ?? true); @endphp
                    <div class="flex items-center justify-between gap-4 rounded-lg border border-hairline bg-white px-5 py-4">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-ink">{{ $meta[$feature]['label'] }}</p>
                            <p class="mt-0.5 text-xs text-ink-mute">{{ $meta[$feature]['desc'] }}</p>
                        </div>
                        <button
                            type="button"
                            wire:click="toggle('{{ $feature }}')"
                            role="switch"
                            aria-checked="{{ $isOn ? 'true' : 'false' }}"
                            aria-label="{{ $meta[$feature]['label'] }}"
                            class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 {{ $isOn ? 'bg-primary' : 'bg-gray-300' }}"
                        >
                            <span
                                aria-hidden="true"
                                class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out {{ $isOn ? 'translate-x-5' : 'translate-x-0' }}"
                            ></span>
                        </button>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="flex justify-end">
            <x-admin.button type="submit" variant="primary">Simpan Pengaturan</x-admin.button>
        </div>
    </form>

    {{-- Confirm save modal --}}
    @if ($showConfirm)
        <div
            wire:keydown.escape.window="cancelSave"
            class="fixed inset-0 z-50 flex items-center justify-center px-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="confirm-save-title"
            aria-describedby="confirm-save-desc"
        >
            <button
                type="button"
                wire:click="cancelSave"
                class="absolute inset-0 cursor-default bg-ink/45 backdrop-blur-sm"
                aria-label="Batal"
            ></button>

            <section
                x-data
                x-init="$nextTick(() => $refs.confirmBtn.focus())"
                class="relative z-10 w-full max-w-sm rounded-xl border border-hairline bg-white p-6 shadow-level2"
            >
                <h2 id="confirm-save-title" class="text-base font-semibold text-ink">Simpan perubahan?</h2>
                <p id="confirm-save-desc" class="mt-2 text-sm text-ink-mute">Perubahan berikut akan segera diterapkan ke seluruh sistem.</p>

                <dl class="mt-4 divide-y divide-hairline rounded-lg border border-hairline text-sm">
                    <div class="flex items-center justify-between px-4 py-3">
                        <dt class="text-ink-mute">Iuran Harian</dt>
                        <dd class="font-semibold text-ink">Rp{{ number_format($iuran_amount, 0, ',', '.') }}</dd>
                    </div>
                    <div class="flex items-center justify-between px-4 py-3">
                        <dt class="text-ink-mute">Denda Absen Ronda</dt>
                        <dd class="font-semibold text-ink">Rp{{ number_format($denda_amount, 0, ',', '.') }}</dd>
                    </div>
                    <div class="flex items-center justify-between px-4 py-3">
                        <dt class="text-ink-mute">Saldo Awal Kas</dt>
                        <dd class="font-semibold text-ink">Rp{{ number_format($kas_opening_balance, 0, ',', '.') }}</dd>
                    </div>
                    <div class="flex items-center justify-between px-4 py-3">
                        <dt class="text-ink-mute">Modul aktif</dt>
                        <dd class="font-semibold text-ink">{{ collect($flags)->filter()->count() }} / {{ count($flags) }}</dd>
                    </div>
                </dl>

                <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <x-admin.button type="button" variant="secondary" wire:click="cancelSave">Batal</x-admin.button>
                    <x-admin.button x-ref="confirmBtn" type="button" variant="primary" wire:click="save">Ya, Simpan</x-admin.button>
                </div>
            </section>
        </div>
    @endif
</div>
