<?php

use App\Models\RondaSchedule;
use App\Support\Audit;
use function Livewire\Volt\{state, rules, computed, layout, title};

state(['date' => '', 'notes' => '']);

layout('components.layouts.app');
title('Jadwal Ronda');

rules([
    'date' => ['required', 'date', 'unique:ronda_schedules,date'],
    'notes' => ['nullable', 'string', 'max:500'],
]);

$schedules = computed(fn () => RondaSchedule::query()
    ->withCount(['assignments', 'assignments as checked_in_count' => fn ($q) => $q->whereNotNull('checked_in_at')])
    ->orderByDesc('date')
    ->get());

$save = function () {
    if ($this->date) {
        $this->date = \Illuminate\Support\Carbon::parse($this->date)->startOfDay()->toDateTimeString();
    }

    $data = $this->validate();
    $data['created_by'] = auth()->id();

    $schedule = RondaSchedule::create($data);
    Audit::record(auth()->user(), 'ronda.schedule.created', 'ronda_schedule', $schedule->id, ['date' => $schedule->date->toDateString()]);

    $this->reset('date', 'notes');
};

?>

<div class="space-y-6">
        <div class="rounded-[1.5rem] bg-white p-6 shadow-xl shadow-slate-900/5 ring-1 ring-slate-200 sm:rounded-[1.75rem]">
            <p class="text-sm font-semibold text-emerald-700">Manajemen operasional</p>
            <h1 class="mt-2 text-3xl font-bold tracking-tight text-slate-950">Jadwal Ronda</h1>
            <p class="mt-2 text-slate-500">Kelola jadwal ronda per tanggal, atur warga yang bertugas, dan pantau kehadiran ronda mandiri.</p>
        </div>

        <form wire:submit="save" class="grid gap-4 rounded-[1.5rem] bg-white p-5 shadow-lg shadow-slate-900/5 ring-1 ring-slate-200 sm:grid-cols-4">
            <div class="sm:col-span-1">
                <label class="block text-sm font-medium text-slate-700">Tanggal Ronda</label>
                <input wire:model="date" type="date" class="mt-2 w-full rounded-2xl border-slate-200 bg-slate-50 px-4 py-3 focus:border-emerald-400 focus:bg-white focus:ring-4 focus:ring-emerald-100 text-slate-950">
                @error('date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-slate-700">Catatan / Keterangan</label>
                <input wire:model="notes" type="text" placeholder="Contoh: Libur Lebaran, Pengamanan Ekstra" class="mt-2 w-full rounded-2xl border-slate-200 bg-slate-50 px-4 py-3 focus:border-emerald-400 focus:bg-white focus:ring-4 focus:ring-emerald-100 text-slate-950">
                @error('notes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="flex items-end">
                <button class="w-full rounded-2xl bg-emerald-500 px-5 py-3 font-bold text-slate-950 shadow-lg shadow-emerald-500/20 transition hover:bg-emerald-400">
                    Tambah Jadwal
                </button>
            </div>
        </form>

        <section class="rounded-[1.5rem] bg-white shadow-xl shadow-slate-900/5 ring-1 ring-slate-200">
            <div class="border-b border-slate-100 px-5 py-4">
                <h2 class="text-lg font-bold text-slate-950">Daftar Tanggal Ronda</h2>
                <p class="mt-1 text-sm text-slate-500">Klik 'Kelola' pada salah satu tanggal untuk menambahkan warga yang bertugas.</p>
            </div>

            <div class="hidden overflow-hidden sm:block">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Tanggal</th>
                            <th class="px-4 py-3">Petugas Ronda</th>
                            <th class="px-4 py-3">Kehadiran</th>
                            <th class="px-4 py-3">Keterangan</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($this->schedules as $schedule)
                            <tr>
                                <td class="px-4 py-3 font-semibold text-slate-900">{{ $schedule->date->format('d M Y') }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $schedule->assignments_count }} warga bertugas</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $schedule->checked_in_count > 0 ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                                        {{ $schedule->checked_in_count }} / {{ $schedule->assignments_count }} Hadir
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-slate-500 max-w-xs truncate">{{ $schedule->notes ?? '-' }}</td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('ronda.show', $schedule) }}" class="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-200 transition">Kelola</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="divide-y divide-slate-100 sm:hidden">
                @foreach ($this->schedules as $schedule)
                    <article class="p-5">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h3 class="font-bold text-slate-950">{{ $schedule->date->format('d M Y') }}</h3>
                                <p class="mt-1 text-sm text-slate-500">{{ $schedule->assignments_count }} warga bertugas</p>
                            </div>
                            <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold {{ $schedule->checked_in_count > 0 ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                                {{ $schedule->checked_in_count }} / {{ $schedule->assignments_count }} Hadir
                            </span>
                        </div>
                        @if ($schedule->notes)
                            <p class="mt-3 rounded-2xl bg-slate-50 px-3 py-2 text-sm text-slate-600">{{ $schedule->notes }}</p>
                        @endif
                        <div class="mt-4">
                            <a href="{{ route('ronda.show', $schedule) }}" class="inline-block rounded-full bg-slate-100 px-4 py-2 text-xs font-bold text-slate-700">Kelola Jadwal &rarr;</a>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
</div>
