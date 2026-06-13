<?php

use App\Models\CashTransaction;
use App\Services\KasReport;
use App\Services\TransactionCorrection;
use Illuminate\Support\Carbon;
use function Livewire\Volt\{computed, layout, mount, rules, state, title};

layout('components.layouts.app');
title('Daftar Transaksi Kas');

state(['cancelId' => null, 'reason' => '', 'date' => null]);

rules(['reason' => ['required', 'string', 'min:3', 'max:500']]);

mount(function () {
    $requestedDate = (string) request()->query('date', today()->toDateString());
    $this->date = Carbon::canBeCreatedFromFormat($requestedDate, 'Y-m-d')
        ? $requestedDate
        : today()->toDateString();
});

$ref = function () {
    $date = (string) $this->date;

    return Carbon::canBeCreatedFromFormat($date, 'Y-m-d')
        ? Carbon::createFromFormat('Y-m-d', $date)->startOfDay()
        : today();
};

$transactions = computed(fn () => CashTransaction::query()
    ->with(['household', 'resident'])
    ->whereDate('date', $this->ref()->toDateString())
    ->latest()
    ->get());

$daily = computed(fn () => app(KasReport::class)->daily($this->ref()));

$startCancel = function (int $id) {
    $this->cancelId = $id;
    $this->reset('reason');
};

$cancelCancel = function () {
    $this->reset('cancelId', 'reason');
    $this->resetValidation('reason');
};

$confirmCancel = function () {
    $this->validate();

    $tx = CashTransaction::findOrFail($this->cancelId);

    try {
        app(TransactionCorrection::class)->cancel($tx, $this->reason, auth()->user());
    } catch (\InvalidArgumentException $exception) {
        $this->addError('reason', $exception->getMessage());

        return;
    }

    $this->reset('cancelId', 'reason');
};

$rupiah = fn (int $value) => 'Rp'.number_format($value, 0, ',', '.');

?>

<div class="space-y-7">
    <x-admin.page-header
        title="Daftar Transaksi Kas"
        :description="'Transaksi kas untuk '.$this->ref()->translatedFormat('d F Y').'.'"
    >
        <x-slot:actions>
            <x-admin.button variant="secondary" href="{{ route('kas.index') }}">Rekap Kas</x-admin.button>
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
                <label for="transaksi-date-display" class="block text-xs font-medium uppercase tracking-[0.08em] text-ink-mute">Tanggal</label>
                <div class="relative mt-2">
                    <input
                        id="transaksi-date-display"
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
                        aria-label="Pilih tanggal transaksi"
                        class="pointer-events-none absolute h-px w-px opacity-0"
                    >
                    <button
                        type="button"
                        aria-label="Buka kalender tanggal transaksi"
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
                x-bind:href="'{{ route('kas.transactions') }}?date=' + raw"
            >Tampilkan</x-admin.button>
        </div>
    </x-admin.panel>

    <section aria-label="Ringkasan transaksi harian" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-admin.metric label="Total Harian" :value="$this->rupiah($this->daily['total'])" description="Transaksi aktif pada tanggal ini" />
        <x-admin.metric label="Iuran" :value="$this->rupiah($this->daily['iuran'])" description="Iuran harian" />
        <x-admin.metric label="Denda" :value="$this->rupiah($this->daily['denda'])" description="Denda ronda" />
        <x-admin.metric label="Koreksi" :value="$this->rupiah($this->daily['koreksi'])" description="Pembatalan/koreksi" />
    </section>

    <x-admin.panel :padding="false" aria-labelledby="transactions-title">
        <div class="border-b border-hairline px-5 py-4 sm:px-6">
            <h2 id="transactions-title" class="text-lg font-medium text-ink">Transaksi {{ $this->ref()->translatedFormat('d F Y') }}</h2>
            <p class="mt-1 text-sm text-ink-mute">Pembatalan membuat transaksi koreksi dan tidak menghapus catatan asli.</p>
        </div>

        @if ($this->transactions->isEmpty())
            <x-admin.empty-state
                title="Belum ada transaksi"
                description="Tidak ada transaksi iuran, denda, atau koreksi pada tanggal ini."
            />
        @else
            <div class="hidden md:block">
                <table class="min-w-full text-sm">
                    <thead class="border-b border-hairline bg-canvas-soft text-left text-xs font-medium uppercase tracking-[0.08em] text-ink-mute">
                        <tr>
                            <th class="px-5 py-3 sm:px-6">Tanggal</th>
                            <th class="px-4 py-3">Jenis</th>
                            <th class="px-4 py-3">Rumah/Warga</th>
                            <th class="px-4 py-3 text-right">Nominal</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-5 py-3 text-right sm:px-6">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-hairline">
                        @foreach ($this->transactions as $tx)
                            <tr wire:key="transaction-desktop-{{ $tx->id }}" @class(['text-ink', 'bg-canvas-soft/60 text-ink-mute' => $tx->isCancelled()])>
                                <td class="tnum whitespace-nowrap px-5 py-4 sm:px-6">{{ $tx->date->format('d/m/Y') }}</td>
                                <td class="px-4 py-4 font-medium">{{ $tx->type->label() }}</td>
                                <td class="px-4 py-4">{{ $tx->household?->house_number ?? $tx->resident?->name ?? '-' }}</td>
                                <td class="tnum whitespace-nowrap px-4 py-4 text-right font-medium">{{ $this->rupiah($tx->amount) }}</td>
                                <td class="px-4 py-4">
                                    @if ($tx->isCancelled())
                                        <x-admin.status-badge tone="neutral">Dibatalkan</x-admin.status-badge>
                                    @elseif ($tx->type->value === 'koreksi')
                                        <x-admin.status-badge tone="info">Koreksi</x-admin.status-badge>
                                    @else
                                        <x-admin.status-badge tone="success">{{ ucfirst($tx->status) }}</x-admin.status-badge>
                                    @endif
                                </td>
                                <td class="px-5 py-4 text-right sm:px-6">
                                    @if (! $tx->isCancelled() && $tx->type->value !== 'koreksi')
                                        <x-admin.button variant="danger" wire:click="startCancel({{ $tx->id }})">Batalkan</x-admin.button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="divide-y divide-hairline md:hidden">
                @foreach ($this->transactions as $tx)
                    <article wire:key="transaction-mobile-{{ $tx->id }}" class="px-5 py-4">
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-ink">{{ $tx->type->label() }}</p>
                                <p class="mt-1 text-xs text-ink-mute">{{ $tx->household?->house_number ?? $tx->resident?->name ?? '-' }}</p>
                            </div>
                            <p class="tnum shrink-0 text-sm font-medium text-ink">{{ $this->rupiah($tx->amount) }}</p>
                        </div>
                        <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                            <div class="flex items-center gap-2">
                                <time class="tnum text-xs text-ink-mute" datetime="{{ $tx->date->toDateString() }}">{{ $tx->date->format('d/m/Y') }}</time>
                                @if ($tx->isCancelled())
                                    <x-admin.status-badge tone="neutral">Dibatalkan</x-admin.status-badge>
                                @elseif ($tx->type->value === 'koreksi')
                                    <x-admin.status-badge tone="info">Koreksi</x-admin.status-badge>
                                @else
                                    <x-admin.status-badge tone="success">{{ ucfirst($tx->status) }}</x-admin.status-badge>
                                @endif
                            </div>
                            @if (! $tx->isCancelled() && $tx->type->value !== 'koreksi')
                                <x-admin.button variant="danger" wire:click="startCancel({{ $tx->id }})">Batalkan</x-admin.button>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </x-admin.panel>

    @if ($cancelId)
        <div
            wire:keydown.escape.window="cancelCancel"
            class="fixed inset-0 z-50 flex items-center justify-center px-4 py-6 sm:px-6"
            role="dialog"
            aria-modal="true"
            aria-labelledby="cancel-transaction-title"
            aria-describedby="cancel-transaction-description"
            x-data="{
                trapFocus(event) {
                    const focusable = [...this.$refs.dialog.querySelectorAll('textarea, button:not([disabled])')];
                    const first = focusable[0];
                    const last = focusable[focusable.length - 1];

                    if (event.shiftKey && document.activeElement === first) {
                        event.preventDefault();
                        last.focus();
                    } else if (!event.shiftKey && document.activeElement === last) {
                        event.preventDefault();
                        first.focus();
                    }
                }
            }"
            x-init="$nextTick(() => $refs.reason.focus())"
        >
            <button
                type="button"
                wire:click="cancelCancel"
                class="absolute inset-0 cursor-default bg-ink/45 backdrop-blur-sm"
                aria-label="Tutup konfirmasi pembatalan"
            ></button>

            <section
                x-ref="dialog"
                @keydown.tab="trapFocus($event)"
                class="relative z-10 w-full max-w-lg rounded-xl border border-hairline bg-white p-6 shadow-level2"
            >
                <h2 id="cancel-transaction-title" class="text-lg font-medium text-ink">Batalkan Transaksi #{{ $cancelId }}</h2>
                <p id="cancel-transaction-description" class="mt-2 text-sm leading-6 text-ink-mute">Transaksi tidak dihapus. Sistem akan mencatat koreksi dengan alasan yang dapat diaudit.</p>
                <div class="mt-5">
                    <label for="cancellation-reason" class="block text-xs font-medium uppercase tracking-[0.08em] text-ink-mute">Alasan pembatalan</label>
                    <textarea
                        id="cancellation-reason"
                        x-ref="reason"
                        wire:model="reason"
                        rows="3"
                        class="mt-2 w-full rounded-sm border border-hairline-input bg-white px-4 py-3 text-base text-ink transition focus:border-primary focus:ring-1 focus:ring-primary"
                    ></textarea>
                    @error('reason') <p class="mt-2 text-sm font-medium text-ruby">{{ $message }}</p> @enderror
                </div>
                <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <x-admin.button type="button" variant="secondary" wire:click="cancelCancel">Batal</x-admin.button>
                    <x-admin.button type="button" variant="danger" wire:click="confirmCancel">Konfirmasi Pembatalan</x-admin.button>
                </div>
            </section>
        </div>
    @endif
</div>
