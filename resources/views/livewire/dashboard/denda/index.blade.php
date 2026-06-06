<?php

use App\Models\RondaAssignment;
use App\Services\DendaService;
use App\Support\Audit;
use Illuminate\Support\Carbon;
use function Livewire\Volt\{computed, layout, mount, state, title};

layout('components.layouts.app');
title('Review Denda Ronda');

state(['date' => null]);

mount(function (?string $date = null) {
    $this->date = $date ?? request()->query('date', today()->subDay()->toDateString());
});

$candidates = computed(fn () => app(DendaService::class)->candidates(Carbon::parse($this->date)));

$fine = function (int $assignmentId) {
    $assignment = RondaAssignment::with('resident', 'rondaSchedule')->findOrFail($assignmentId);
    $tx = app(DendaService::class)->fine($assignment, auth()->user());

    Audit::record(auth()->user(), 'kas.denda.created', 'cash_transaction', $tx->id, [
        'resident_id' => $assignment->resident_id,
        'date' => $this->date,
    ]);
};

?>

<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold text-white sm:text-slate-900">Review Denda Ronda</h1>
        <p class="mt-1 text-sm text-slate-300 sm:text-slate-600">Warga terjadwal yang belum check-in. Tinjau dulu sebelum menetapkan denda Rp5.000.</p>
    </div>

    <div class="flex items-end gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
        <div>
            <label class="block text-sm font-medium text-slate-700">Tanggal</label>
            <input wire:model.live="date" type="date" class="mt-1 rounded-lg border-slate-300">
        </div>
    </div>

    <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-slate-500">
                <tr>
                    <th class="px-4 py-2">Nama</th>
                    <th class="px-4 py-2">Rumah</th>
                    <th class="px-4 py-2 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($this->candidates as $assignment)
                    <tr>
                        <td class="px-4 py-2">{{ $assignment->resident?->name }}</td>
                        <td class="px-4 py-2">{{ $assignment->resident?->household?->house_number }}</td>
                        <td class="px-4 py-2 text-right">
                            <button wire:click="fine({{ $assignment->id }})" wire:confirm="Tetapkan denda Rp5.000 untuk warga ini?" class="rounded-lg bg-red-600 px-3 py-1 text-white hover:bg-red-700">
                                Denda Rp5.000
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-4 py-6 text-center text-slate-400">Tidak ada calon denda untuk tanggal ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
