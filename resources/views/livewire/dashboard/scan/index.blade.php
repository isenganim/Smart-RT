<?php

use App\Models\RondaScanSession;
use App\Support\Audit;
use function Livewire\Volt\{computed, layout, rules, state, title};

layout('components.layouts.app');
title('Sesi Scan Ronda');

state([
    'date' => '',
    'starts_at' => '',
    'ends_at' => '',
]);

rules(fn () => [
    'date' => ['required', 'date', 'unique:ronda_scan_sessions,date'],
    'starts_at' => ['required', 'date'],
    'ends_at' => ['required', 'date', 'after:starts_at'],
]);

$sessions = computed(fn () => RondaScanSession::query()
    ->withCount('transactions')
    ->withSum('transactions', 'amount')
    ->orderByDesc('date')
    ->get());

$save = function () {
    $data = $this->validate();

    if (RondaScanSession::query()->whereDate('date', $data['date'])->exists()) {
        $this->addError('date', 'Tanggal sesi sudah ada.');

        return;
    }

    $data['created_by'] = auth()->id();

    $session = RondaScanSession::create($data);
    Audit::record(auth()->user(), 'ronda.scan_session.created', 'ronda_scan_session', $session->id, ['date' => $session->date->toDateString()]);

    $this->reset('date', 'starts_at', 'ends_at');
};

$regenerate = function (int $id) {
    $session = RondaScanSession::findOrFail($id);
    $session->update(['pin' => RondaScanSession::generatePin()]);
    Audit::record(auth()->user(), 'ronda.scan_session.pin_regenerated', 'ronda_scan_session', $session->id, []);
};

?>

<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold text-white sm:text-slate-900">Sesi Scan Ronda</h1>
        <p class="mt-1 text-sm text-slate-300 sm:text-slate-600">Buat sesi harian untuk membuka mode scan iuran Rp500. Bagikan PIN ke regu ronda lewat WhatsApp.</p>
    </div>

    <form wire:submit="save" class="grid gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200 sm:grid-cols-4">
        <div>
            <label class="block text-sm font-medium text-slate-700">Tanggal</label>
            <input wire:model="date" type="date" class="mt-1 w-full rounded-lg border-slate-300">
            @error('date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">Mulai</label>
            <input wire:model="starts_at" type="datetime-local" class="mt-1 w-full rounded-lg border-slate-300">
            @error('starts_at') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">Selesai</label>
            <input wire:model="ends_at" type="datetime-local" class="mt-1 w-full rounded-lg border-slate-300">
            @error('ends_at') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div class="flex items-end">
            <button class="rounded-lg bg-emerald-600 px-4 py-2 font-medium text-white hover:bg-emerald-700">Buat Sesi</button>
        </div>
    </form>

    <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-slate-500">
                <tr>
                    <th class="px-4 py-2">Tanggal</th>
                    <th class="px-4 py-2">PIN</th>
                    <th class="px-4 py-2">Jendela Waktu</th>
                    <th class="px-4 py-2">Status</th>
                    <th class="px-4 py-2">Terkumpul</th>
                    <th class="px-4 py-2 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($this->sessions as $session)
                    @php
                        $isActive = $session->isActive();
                        $isUpcoming = now()->lt($session->starts_at);
                        $statusLabel = $isActive ? 'Aktif' : ($isUpcoming ? 'Belum Mulai' : 'Kedaluwarsa');
                        $statusClasses = $isActive
                            ? 'bg-emerald-100 text-emerald-700'
                            : ($isUpcoming ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-500');
                    @endphp
                    <tr>
                        <td class="px-4 py-2">{{ $session->date->format('d M Y') }}</td>
                        <td class="px-4 py-2 font-mono text-base tracking-widest text-emerald-700">{{ $session->pin }}</td>
                        <td class="px-4 py-2 text-slate-500">{{ $session->starts_at->format('d/m H:i') }} - {{ $session->ends_at->format('d/m H:i') }}</td>
                        <td class="px-4 py-2">
                            <span class="rounded-full px-2 py-0.5 text-xs {{ $statusClasses }}">
                                {{ $statusLabel }}
                            </span>
                        </td>
                        <td class="px-4 py-2">Rp{{ number_format((int) $session->transactions_sum_amount, 0, ',', '.') }} ({{ $session->transactions_count }})</td>
                        <td class="px-4 py-2 text-right">
                            <button wire:click="regenerate({{ $session->id }})" class="text-slate-600 hover:underline">PIN Baru</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-6 text-center text-slate-400">Belum ada sesi scan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
