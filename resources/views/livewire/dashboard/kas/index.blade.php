<?php

use App\Services\KasReport;
use Illuminate\Support\Carbon;
use function Livewire\Volt\{computed, layout, mount, state, title};

layout('components.layouts.app');
title('Rekap Kas');

state(['date' => null]);

mount(function () {
    $requestedDate = (string) request()->query('date', today()->toDateString());
    $this->date = Carbon::canBeCreatedFromFormat($requestedDate, 'Y-m-d')
        ? $requestedDate
        : today()->toDateString();
});

$report = fn () => app(KasReport::class);
$ref = function () {
    $date = (string) $this->date;

    return Carbon::canBeCreatedFromFormat($date, 'Y-m-d')
        ? Carbon::createFromFormat('Y-m-d', $date)->startOfDay()
        : today();
};

$daily = computed(fn () => $this->report()->daily($this->ref()));
$weekly = computed(fn () => $this->report()->rangeTotal($this->ref()->copy()->startOfWeek(), $this->ref()->copy()->endOfWeek()));
$monthly = computed(fn () => $this->report()->rangeTotal($this->ref()->copy()->startOfMonth(), $this->ref()->copy()->endOfMonth()));
$unpaid = computed(fn () => $this->report()->unpaidHouseholds($this->ref()));
$missingCheckins = computed(fn () => $this->report()->missingCheckins($this->ref()));

$rupiah = fn (int $value) => 'Rp'.number_format($value, 0, ',', '.');

?>

<div class="space-y-7">
    <x-admin.page-header
        title="Rekap Kas"
        :description="'Ringkasan penerimaan dan pekerjaan kas untuk '.$this->ref()->translatedFormat('d F Y').'.'"
    >
        <x-slot:actions>
            <x-admin.button href="{{ route('kas.transactions') }}">Daftar Transaksi</x-admin.button>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.panel>
        <div
            class="flex max-w-lg flex-col gap-3 sm:flex-row sm:items-end"
            x-data="{
                raw: @js($this->date),
                get display() {
                    if (!this.raw) return '';
                    const [y, m, d] = this.raw.split('-');
                    return d + '/' + m + '/' + y;
                },
                openPicker() {
                    const picker = this.$refs.datePicker;

                    try {
                        if (picker.showPicker) {
                            picker.showPicker();
                        } else {
                            picker.click();
                        }
                    } catch (error) {
                        picker.click();
                    }
                }
            }"
        >
            <div class="min-w-0 flex-1">
                <label for="kas-reference-date-display" class="block text-xs font-medium uppercase tracking-[0.08em] text-ink-mute">Tanggal acuan</label>
                <div class="relative mt-2">
                    <input
                        id="kas-reference-date-display"
                        :value="display"
                        type="text"
                        readonly
                        class="tnum min-h-11 w-full rounded-sm border border-hairline-input bg-white py-2.5 pl-4 pr-12 text-base text-ink transition focus:border-primary focus:ring-1 focus:ring-primary"
                    >
                    <input
                        x-ref="datePicker"
                        x-model="raw"
                        type="date"
                        tabindex="-1"
                        aria-label="Pilih tanggal acuan"
                        class="pointer-events-none absolute h-px w-px opacity-0"
                    >
                    <button
                        type="button"
                        aria-label="Buka kalender tanggal acuan"
                        class="absolute inset-y-0 right-0 flex w-12 items-center justify-center rounded-r-sm text-ink-mute transition hover:bg-canvas-soft hover:text-ink focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary"
                        @click="openPicker()"
                    >
                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" class="pointer-events-none absolute left-1/2 top-1/2 size-5 -translate-x-1/2 -translate-y-1/2 text-ink-mute">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3.75 9h16.5M5.25 4.5h13.5a1.5 1.5 0 0 1 1.5 1.5v12.75a1.5 1.5 0 0 1-1.5 1.5H5.25a1.5 1.5 0 0 1-1.5-1.5V6a1.5 1.5 0 0 1 1.5-1.5Z" />
                        </svg>
                    </button>
                </div>
            </div>
            <x-admin.button
                href="#"
                x-bind:href="'{{ route('kas.index') }}?date=' + raw"
            >Tampilkan</x-admin.button>
        </div>
    </x-admin.panel>

    <section aria-label="Ringkasan kas" class="grid gap-4 sm:grid-cols-3">
        <x-admin.metric
            label="Total Harian"
            :value="$this->rupiah($this->daily['total'])"
            :description="'Iuran '.$this->rupiah($this->daily['iuran']).' · Denda '.$this->rupiah($this->daily['denda']).' · Koreksi '.$this->rupiah($this->daily['koreksi'])"
        />
        <x-admin.metric
            label="Total Mingguan"
            :value="$this->rupiah($this->weekly)"
            description="Akumulasi transaksi aktif pada minggu berjalan"
        />
        <x-admin.metric
            label="Total Bulanan"
            :value="$this->rupiah($this->monthly)"
            description="Akumulasi transaksi aktif pada bulan berjalan"
        />
    </section>

    <div class="grid gap-6 xl:grid-cols-2">
        <x-admin.panel :padding="false" aria-labelledby="unpaid-households-title">
            <div class="border-b border-hairline px-5 py-4 sm:px-6">
                <h2 id="unpaid-households-title" class="text-lg font-medium text-ink">Rumah belum bayar</h2>
                <p class="mt-1 text-sm text-ink-mute">{{ $this->ref()->translatedFormat('d F Y') }}</p>
            </div>

            @forelse ($this->unpaid as $household)
                <div class="flex min-h-16 items-center justify-between gap-4 border-b border-hairline px-5 py-3 last:border-b-0 sm:px-6">
                    <div class="min-w-0">
                        <p class="tnum text-sm font-medium text-ink">{{ $household->house_number }}</p>
                        <p class="mt-1 truncate text-xs text-ink-mute">{{ $household->head_name }}</p>
                    </div>
                    <x-admin.status-badge tone="warning">Belum bayar</x-admin.status-badge>
                </div>
            @empty
                <x-admin.empty-state
                    title="Semua rumah sudah bayar"
                    description="Tidak ada rumah aktif dengan iuran tertunda pada tanggal acuan ini."
                />
            @endforelse
        </x-admin.panel>

        <x-admin.panel :padding="false" aria-labelledby="missing-checkins-title">
            <div class="border-b border-hairline px-5 py-4 sm:px-6">
                <h2 id="missing-checkins-title" class="text-lg font-medium text-ink">Warga belum check-in</h2>
                <p class="mt-1 text-sm text-ink-mute">Petugas ronda yang belum mencatat kehadiran.</p>
            </div>

            @forelse ($this->missingCheckins as $assignment)
                <div class="flex min-h-16 items-center justify-between gap-4 border-b border-hairline px-5 py-3 last:border-b-0 sm:px-6">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-ink">{{ $assignment->resident?->name }}</p>
                        <p class="tnum mt-1 text-xs text-ink-mute">{{ $assignment->resident?->household?->house_number }}</p>
                    </div>
                    <x-admin.status-badge tone="danger">Belum check-in</x-admin.status-badge>
                </div>
            @empty
                <x-admin.empty-state
                    title="Tidak ada check-in tertunda"
                    description="Semua warga terjadwal sudah check-in atau tidak ada jadwal pada tanggal ini."
                />
            @endforelse
        </x-admin.panel>
    </div>
</div>
