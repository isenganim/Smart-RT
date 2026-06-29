<?php

use App\Models\RondaSchedule;
use App\Support\Audit;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use function Livewire\Volt\{state, rules, computed, layout, title};

state(['date' => '', 'notes' => '']);

layout('components.layouts.app');
title('Jadwal Ronda');

rules([
    'date' => ['required', 'date'],
    'notes' => ['nullable', 'string', 'max:500'],
]);

$schedules = computed(fn () => RondaSchedule::query()
    ->withCount(['assignments', 'assignments as checked_in_count' => fn ($q) => $q->whereNotNull('checked_in_at')])
    ->orderByDesc('date')
    ->get());

$save = function () {
    $data = $this->validate();
    $data['date'] = \Illuminate\Support\Carbon::parse($data['date'])->toDateString();
    $createdBy = auth()->id();

    try {
        $schedule = RondaSchedule::make($data);
        $schedule->created_by = $createdBy;
        $schedule->save();
    } catch (QueryException $exception) {
        if (($exception->errorInfo[0] ?? null) !== '23000') {
            throw $exception;
        }

        throw ValidationException::withMessages(['date' => 'Jadwal ronda untuk tanggal ini sudah ada.']);
    }

    Audit::record(auth()->user(), 'ronda.schedule.created', 'ronda_schedule', $schedule->id, ['date' => $schedule->date->toDateString()]);

    $this->reset('date', 'notes');
};

// Single source of truth for a schedule's status badge, shared by the desktop
// and mobile lists so their labels/styles can never diverge.
$statusFor = function (RondaSchedule $schedule) {
    $meta = [
        'upcoming' => ['label' => 'Akan datang', 'classes' => 'bg-sky-100 text-sky-700'],
        'ongoing' => ['label' => 'Berlangsung', 'classes' => 'bg-amber-100 text-amber-700'],
        'done' => ['label' => 'Selesai', 'classes' => 'bg-emerald-100 text-emerald-700'],
        'missed' => ['label' => 'Terlewat', 'classes' => 'bg-rose-100 text-rose-700'],
    ];

    $key = $schedule->status();
    $absent = $schedule->absentCount();

    $label = $key === 'missed'
        ? ($schedule->assignments_count > 0 ? 'Terlewat · '.$absent.' belum check-in' : 'Terlewat · tanpa petugas')
        : $meta[$key]['label'];

    return [
        'key' => $key,
        'classes' => $meta[$key]['classes'],
        'absent' => $absent,
        'label' => $label,
    ];
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
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Keterangan</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($this->schedules as $schedule)
                            @php($status = $this->statusFor($schedule))
                            <tr>
                                <td class="px-4 py-3 font-semibold text-slate-900">{{ $schedule->date->format('d M Y') }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $schedule->assignments_count }} warga bertugas</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $schedule->checked_in_count > 0 ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                                        {{ $schedule->checked_in_count }} / {{ $schedule->assignments_count }} Hadir
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $status['classes'] }}">{{ $status['label'] }}</span>
                                </td>
                                <td class="px-4 py-3 text-slate-500 max-w-xs truncate">{{ $schedule->notes ?? '-' }}</td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        @if ($status['key'] === 'missed' && $status['absent'] > 0)
                                            <a href="{{ route('denda.index', ['date' => $schedule->date->toDateString()]) }}" class="rounded-full bg-rose-100 px-3 py-1.5 text-xs font-bold text-rose-700 hover:bg-rose-200 transition">Proses Denda</a>
                                        @endif
                                        <a href="{{ route('ronda.show', $schedule) }}" class="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-200 transition">Kelola</a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="divide-y divide-slate-100 sm:hidden">
                @foreach ($this->schedules as $schedule)
                    @php($status = $this->statusFor($schedule))
                    <article class="p-5">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h3 class="font-bold text-slate-950">{{ $schedule->date->format('d M Y') }}</h3>
                                <p class="mt-1 text-sm text-slate-500">{{ $schedule->assignments_count }} warga bertugas</p>
                            </div>
                            <div class="flex shrink-0 flex-col items-end gap-1.5">
                                <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $status['classes'] }}">{{ $status['label'] }}</span>
                                <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $schedule->checked_in_count > 0 ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                                    {{ $schedule->checked_in_count }} / {{ $schedule->assignments_count }} Hadir
                                </span>
                            </div>
                        </div>
                        @if ($schedule->notes)
                            <p class="mt-3 rounded-2xl bg-slate-50 px-3 py-2 text-sm text-slate-600">{{ $schedule->notes }}</p>
                        @endif
                        <div class="mt-4 flex flex-wrap gap-2">
                            @if ($status['key'] === 'missed' && $status['absent'] > 0)
                                <a href="{{ route('denda.index', ['date' => $schedule->date->toDateString()]) }}" class="inline-block rounded-full bg-rose-100 px-4 py-2 text-xs font-bold text-rose-700">Proses Denda</a>
                            @endif
                            <a href="{{ route('ronda.show', $schedule) }}" class="inline-block rounded-full bg-slate-100 px-4 py-2 text-xs font-bold text-slate-700">Kelola Jadwal &rarr;</a>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
</div>
