<?php

use App\Models\CashTransaction;
use App\Services\TransactionCorrection;
use function Livewire\Volt\{computed, layout, rules, state, title};

layout('components.layouts.app');
title('Daftar Transaksi Kas');

state(['cancelId' => null, 'reason' => '']);

rules(['reason' => ['required', 'string', 'min:3', 'max:500']]);

$transactions = computed(fn () => CashTransaction::query()
    ->with(['household', 'resident'])
    ->latest()
    ->take(100)
    ->get());

$startCancel = function (int $id) {
    $this->cancelId = $id;
    $this->reset('reason');
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
        description="Riwayat 100 transaksi terbaru beserta status dan tindakan koreksinya."
    >
        <x-slot:actions>
            <x-admin.button variant="secondary" href="{{ route('kas.index') }}">Rekap Kas</x-admin.button>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.panel :padding="false" aria-labelledby="transactions-title">
        <div class="border-b border-hairline px-5 py-4 sm:px-6">
            <h2 id="transactions-title" class="text-lg font-medium text-ink">Transaksi terbaru</h2>
            <p class="mt-1 text-sm text-ink-mute">Pembatalan membuat transaksi koreksi dan tidak menghapus catatan asli.</p>
        </div>

        @if ($this->transactions->isEmpty())
            <x-admin.empty-state
                title="Belum ada transaksi"
                description="Transaksi iuran, denda, dan koreksi akan muncul di sini."
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
        <x-admin.panel class="border-ruby/30" aria-labelledby="cancel-transaction-title">
            <div class="max-w-2xl">
                <h2 id="cancel-transaction-title" class="text-lg font-medium text-ink">Batalkan Transaksi #{{ $cancelId }}</h2>
                <p class="mt-1 text-sm leading-6 text-ink-mute">Transaksi tidak dihapus. Sistem akan mencatat koreksi dengan alasan yang dapat diaudit.</p>
                <div class="mt-5">
                    <label for="cancellation-reason" class="block text-xs font-medium uppercase tracking-[0.08em] text-ink-mute">Alasan pembatalan</label>
                    <textarea
                        id="cancellation-reason"
                        wire:model="reason"
                        rows="3"
                        class="mt-2 w-full rounded-sm border border-hairline-input bg-white px-4 py-3 text-base text-ink transition focus:border-primary focus:ring-1 focus:ring-primary"
                    ></textarea>
                    @error('reason') <p class="mt-2 text-sm font-medium text-ruby">{{ $message }}</p> @enderror
                </div>
                <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row">
                    <x-admin.button variant="secondary" wire:click="$set('cancelId', null)">Batal</x-admin.button>
                    <x-admin.button variant="danger" wire:click="confirmCancel">Konfirmasi Pembatalan</x-admin.button>
                </div>
            </div>
        </x-admin.panel>
    @endif
</div>
