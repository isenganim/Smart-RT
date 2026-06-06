<?php

use App\Models\RondaAssignment;
use App\Services\DendaService;
use Illuminate\Support\Carbon;
use function Livewire\Volt\{computed, layout, mount, state, title};

layout('components.layouts.app');
title('Review Denda Ronda');

state([
    'date' => null,
    'pendingFineId' => null,
]);

mount(function (?string $date = null) {
    $this->date = $date ?? request()->query('date', today()->subDay()->toDateString());
});

$ref = function () {
    $date = (string) $this->date;

    return Carbon::hasFormat($date, 'Y-m-d')
        ? Carbon::createFromFormat('Y-m-d', $date)->startOfDay()
        : today()->subDay();
};

$candidates = computed(fn () => app(DendaService::class)->candidates($this->ref()));
$pendingFine = computed(fn () => $this->pendingFineId
    ? RondaAssignment::with('resident.household')->find($this->pendingFineId)
    : null);

$confirmFine = function (int $assignmentId) {
    $this->pendingFineId = $assignmentId;
};

$cancelFine = function () {
    $this->pendingFineId = null;
};

$fine = function (?int $assignmentId = null) {
    $assignment = RondaAssignment::with('resident', 'rondaSchedule')->findOrFail($assignmentId ?? $this->pendingFineId);

    try {
        app(DendaService::class)->fine($assignment, auth()->user());
    } catch (\InvalidArgumentException $exception) {
        $this->addError('fine', $exception->getMessage());

        return;
    }

    $this->pendingFineId = null;
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
                            <button wire:click="confirmFine({{ $assignment->id }})" class="rounded-lg bg-red-600 px-3 py-1 text-white hover:bg-red-700">
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

    @if ($this->pendingFine)
        <div class="fixed inset-0 z-50 grid place-items-center bg-slate-950/60 p-4" role="dialog" aria-modal="true" aria-labelledby="fine-modal-title">
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl ring-1 ring-slate-200">
                <h2 id="fine-modal-title" class="text-lg font-semibold text-slate-900">Tetapkan Denda Ronda?</h2>
                <p class="mt-2 text-sm text-slate-600">
                    Konfirmasi denda Rp5.000 untuk
                    <span class="font-medium text-slate-900">{{ $this->pendingFine->resident?->name }}</span>
                    dari rumah
                    <span class="font-medium text-slate-900">{{ $this->pendingFine->resident?->household?->house_number }}</span>.
                </p>
                <p class="mt-3 rounded-lg bg-amber-50 p-3 text-sm text-amber-700">Pastikan warga ini benar-benar tidak check-in sebelum mencatat denda.</p>
                @error('fine') <p class="mt-3 rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ $message }}</p> @enderror

                <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button wire:click="cancelFine" class="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100">Batal</button>
                    <button wire:click="fine" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">Ya, Tetapkan Denda</button>
                </div>
            </div>
        </div>
    @endif
</div>
