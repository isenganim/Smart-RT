<?php

use App\Models\CashTransaction;
use App\Services\TransactionCorrection;
use App\Support\Audit;
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
    $koreksi = app(TransactionCorrection::class)->cancel($tx, $this->reason, auth()->user());

    Audit::record(auth()->user(), 'kas.transaction.cancelled', 'cash_transaction', $tx->id, [
        'koreksi_id' => $koreksi->id,
        'reason' => $this->reason,
    ]);

    $this->reset('cancelId', 'reason');
};

$rupiah = fn (int $value) => 'Rp'.number_format($value, 0, ',', '.');

?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-white sm:text-slate-900">Daftar Transaksi Kas</h1>
        <a href="{{ route('kas.index') }}" class="text-sm text-emerald-300 hover:underline sm:text-emerald-700">Rekap Kas</a>
    </div>

    <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-slate-500">
                <tr>
                    <th class="px-4 py-2">Tanggal</th>
                    <th class="px-4 py-2">Jenis</th>
                    <th class="px-4 py-2">Rumah/Warga</th>
                    <th class="px-4 py-2 text-right">Nominal</th>
                    <th class="px-4 py-2">Status</th>
                    <th class="px-4 py-2 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($this->transactions as $tx)
                    <tr class="{{ $tx->isCancelled() ? 'bg-slate-50 text-slate-400' : '' }}">
                        <td class="px-4 py-2">{{ $tx->date->format('d/m/Y') }}</td>
                        <td class="px-4 py-2">{{ $tx->type->label() }}</td>
                        <td class="px-4 py-2">{{ $tx->household?->house_number ?? $tx->resident?->name ?? '-' }}</td>
                        <td class="px-4 py-2 text-right">{{ $this->rupiah($tx->amount) }}</td>
                        <td class="px-4 py-2">
                            @if ($tx->isCancelled())
                                <span class="rounded-full bg-slate-200 px-2 py-0.5 text-xs">Dibatalkan</span>
                            @else
                                <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs text-emerald-700">{{ $tx->status }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-right">
                            @if (! $tx->isCancelled() && $tx->type->value !== 'koreksi')
                                <button wire:click="startCancel({{ $tx->id }})" class="text-red-600 hover:underline">Batalkan</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-6 text-center text-slate-400">Belum ada transaksi.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($cancelId)
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-red-200">
            <h2 class="font-medium text-slate-900">Batalkan Transaksi #{{ $cancelId }}</h2>
            <p class="text-sm text-slate-600">Transaksi tidak dihapus. Sistem mencatat koreksi dengan alasan.</p>
            <div class="mt-3">
                <label class="block text-sm font-medium text-slate-700">Alasan</label>
                <textarea wire:model="reason" rows="2" class="mt-1 w-full rounded-lg border-slate-300"></textarea>
                @error('reason') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="mt-3 flex gap-2">
                <button wire:click="confirmCancel" class="rounded-lg bg-red-600 px-4 py-2 font-medium text-white hover:bg-red-700">Konfirmasi Pembatalan</button>
                <button wire:click="$set('cancelId', null)" class="rounded-lg px-3 py-2 text-slate-600 hover:text-slate-900">Batal</button>
            </div>
        </div>
    @endif
</div>
