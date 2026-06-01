<?php

use App\Models\Resident;
use App\Models\RondaSchedule;
use App\Support\Audit;
use Illuminate\Validation\Rule;
use function Livewire\Volt\{state, computed, layout, mount, title};

state(['schedule' => null, 'residentId' => null]);

layout('components.layouts.app');
title('Kelola Jadwal Ronda');

mount(function (RondaSchedule $schedule) {
    $this->schedule = $schedule;
});

$assignments = computed(fn () => $this->schedule->assignments()->with('resident.household')->get());

$availableResidents = computed(fn () => Resident::query()
    ->with('household')
    ->where('is_active', true)
    ->whereNotIn('id', $this->schedule->assignments()->pluck('resident_id'))
    ->orderBy('name')
    ->get());

$assign = function () {
    $this->validate([
        'residentId' => [
            'required',
            Rule::exists(Resident::class, 'id')->where('is_active', true),
        ],
    ]);

    $assignment = $this->schedule->assignments()->firstOrCreate(['resident_id' => $this->residentId]);

    if ($assignment->wasRecentlyCreated) {
        Audit::record(auth()->user(), 'ronda.assignment.added', 'ronda_schedule', $this->schedule->id, ['resident_id' => $this->residentId]);
    }

    $this->reset('residentId');
};

$remove = function (int $assignmentId) {
    $assignment = $this->schedule->assignments()->findOrFail($assignmentId);
    $residentId = $assignment->resident_id;
    $assignment->delete();

    Audit::record(auth()->user(), 'ronda.assignment.removed', 'ronda_schedule', $this->schedule->id, ['resident_id' => $residentId]);
};

?>

<div class="space-y-6">
        <div>
            <a href="{{ route('ronda.index') }}" class="text-sm font-semibold text-emerald-700 hover:underline">&larr; Kembali ke Jadwal Ronda</a>
            <div class="mt-2 rounded-[1.5rem] bg-white p-6 shadow-xl shadow-slate-900/5 ring-1 ring-slate-200">
                <p class="text-sm font-semibold text-emerald-700">Detail Tugas Ronda</p>
                <h1 class="mt-2 text-3xl font-bold tracking-tight text-slate-950">Ronda: {{ $schedule->date->format('l, d M Y') }}</h1>
                @if ($schedule->notes)
                    <p class="mt-2 text-slate-500 font-medium bg-slate-50 rounded-2xl px-4 py-3 border border-slate-100">{{ $schedule->notes }}</p>
                @endif
            </div>
        </div>

        <form wire:submit="assign" class="flex flex-wrap items-end gap-4 rounded-[1.5rem] bg-white p-5 shadow-lg shadow-slate-900/5 ring-1 ring-slate-200">
            <div class="flex-1 min-w-[200px]">
                <label class="block text-sm font-medium text-slate-700">Pilih Warga untuk Ditugaskan</label>
                <select wire:model="residentId" class="mt-2 w-full rounded-2xl border-slate-200 bg-slate-50 px-4 py-3 focus:border-emerald-400 focus:bg-white focus:ring-4 focus:ring-emerald-100 text-slate-950">
                    <option value="">-- Pilih Warga Aktif --</option>
                    @foreach ($this->availableResidents as $resident)
                        <option value="{{ $resident->id }}">{{ $resident->name }} (Rumah: {{ $resident->household?->house_number ?? '-' }})</option>
                    @endforeach
                </select>
                @error('residentId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <button class="w-full rounded-2xl bg-emerald-500 px-6 py-3 font-bold text-slate-950 shadow-lg shadow-emerald-500/20 transition hover:bg-emerald-400">
                    Tambah Petugas
                </button>
            </div>
        </form>

        <section class="rounded-[1.5rem] bg-white shadow-xl shadow-slate-900/5 ring-1 ring-slate-200">
            <div class="border-b border-slate-100 px-5 py-4">
                <h2 class="text-lg font-bold text-slate-950">Daftar Petugas Ronda</h2>
                <p class="mt-1 text-sm text-slate-500">Warga yang bertugas ronda pada tanggal ini beserta status kehadirannya.</p>
            </div>

            <div class="hidden overflow-hidden sm:block">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Nama</th>
                            <th class="px-4 py-3">Nomor HP</th>
                            <th class="px-4 py-3">Rumah</th>
                            <th class="px-4 py-3">Status Kehadiran</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($this->assignments as $assignment)
                            <tr>
                                <td class="px-4 py-3 font-semibold text-slate-900">{{ $assignment->resident?->name }}</td>
                                <td class="px-4 py-3 tabular-nums">{{ blank($assignment->resident?->phone) ? '-' : (str_starts_with($assignment->resident->phone, '0') || str_starts_with($assignment->resident->phone, '+') ? $assignment->resident->phone : '0'.$assignment->resident->phone) }}</td>
                                <td class="px-4 py-3">{{ $assignment->resident?->household?->house_number ?? '-' }}</td>
                                <td class="px-4 py-3">
                                    @if ($assignment->hasCheckedIn())
                                        <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                                            Hadir ({{ $assignment->checked_in_at->format('H:i') }} WIB)
                                        </span>
                                    @else
                                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-500">
                                            Belum Check-in
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <button wire:click="remove({{ $assignment->id }})" class="rounded-full bg-red-50 px-3 py-1.5 text-xs font-bold text-red-700 hover:bg-red-100 transition">Hapus</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-6 text-center text-slate-500">Belum ada warga yang ditugaskan untuk tanggal ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="divide-y divide-slate-100 sm:hidden">
                @forelse ($this->assignments as $assignment)
                    <article class="p-5">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h3 class="font-bold text-slate-950">{{ $assignment->resident?->name }}</h3>
                                <p class="mt-1 text-sm text-slate-500">HP: {{ blank($assignment->resident?->phone) ? '-' : (str_starts_with($assignment->resident->phone, '0') || str_starts_with($assignment->resident->phone, '+') ? $assignment->resident->phone : '0'.$assignment->resident->phone) }}</p>
                                <p class="text-xs text-slate-400 mt-1">Rumah: {{ $assignment->resident?->household?->house_number ?? '-' }}</p>
                            </div>
                            <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold {{ $assignment->hasCheckedIn() ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                                {{ $assignment->hasCheckedIn() ? 'Hadir ('.$assignment->checked_in_at->format('H:i').' WIB)' : 'Belum Check-in' }}
                            </span>
                        </div>
                        <div class="mt-4 flex justify-end">
                            <button wire:click="remove({{ $assignment->id }})" class="rounded-full bg-red-50 px-4 py-2 text-xs font-bold text-red-700 hover:bg-red-100">Hapus Petugas</button>
                        </div>
                    </article>
                @empty
                    <p class="p-5 text-center text-slate-500">Belum ada warga yang ditugaskan untuk tanggal ini.</p>
                @endforelse
            </div>
        </section>
</div>
