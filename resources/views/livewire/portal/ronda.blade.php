<?php

use App\Models\RondaSchedule;
use function Livewire\Volt\{computed, layout, title};

layout('components.layouts.public');
title('Jadwal Ronda');

$schedules = computed(fn () => RondaSchedule::query()
    ->with('assignments.resident')
    ->whereDate('date', '>=', today())
    ->orderBy('date')
    ->take(14)
    ->get());

?>

<div class="space-y-6">
        <div class="rounded-2xl bg-slate-900 border border-white/5 p-6 shadow-xl">
            <h1 class="text-xl font-bold text-white">Jadwal Ronda</h1>
            <p class="mt-2 text-sm text-slate-400">
                Berikut adalah jadwal ronda warga untuk 14 hari ke depan. Silakan hubungi pengurus RT jika ada perubahan.
            </p>
        </div>

        @if ($this->schedules->isNotEmpty())
            <div class="hidden overflow-hidden rounded-2xl border border-white/5 bg-slate-950/40 shadow-xl sm:block">
                <table class="min-w-full divide-y divide-white/5 text-sm">
                    <thead class="bg-slate-900/80 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
                        <tr>
                            <th class="px-5 py-4">Tanggal</th>
                            <th class="px-5 py-4">Catatan</th>
                            <th class="px-5 py-4">Petugas</th>
                            <th class="px-5 py-4 text-right">Kehadiran</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        @foreach ($this->schedules as $schedule)
                            @php
                                $checkedInCount = $schedule->assignments->filter->hasCheckedIn()->count();
                            @endphp
                            <tr wire:key="ronda-schedule-{{ $schedule->id }}" class="align-top transition-colors hover:bg-white/[0.03]">
                                <td class="whitespace-nowrap px-5 py-4">
                                    <p class="font-bold text-emerald-400">{{ $schedule->date->translatedFormat('l') }}</p>
                                    <p class="mt-1 text-xs text-slate-300">{{ $schedule->date->translatedFormat('d M Y') }}</p>
                                </td>
                                <td class="max-w-56 px-5 py-4 text-slate-300">
                                    {{ $schedule->notes ?: '-' }}
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex flex-wrap gap-2">
                                        @forelse ($schedule->assignments as $assignment)
                                            <span class="rounded-xl px-3 py-1.5 text-xs font-semibold {{ $assignment->hasCheckedIn() ? 'bg-emerald-500/10 text-emerald-400 ring-1 ring-emerald-500/20' : 'bg-slate-900 text-slate-300 ring-1 ring-white/5' }}">
                                                {{ $assignment->resident?->name }}
                                                @if ($assignment->hasCheckedIn())
                                                    <span class="ml-1" title="Sudah Check-in">&check;</span>
                                                @endif
                                            </span>
                                        @empty
                                            <span class="text-xs italic text-slate-500">Belum ada warga yang ditugaskan.</span>
                                        @endforelse
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 text-right">
                                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $checkedInCount > 0 ? 'bg-emerald-500/10 text-emerald-400' : 'bg-slate-900 text-slate-400' }}">
                                        {{ $checkedInCount }} / {{ $schedule->assignments->count() }} Hadir
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="space-y-4 sm:hidden">
                @foreach ($this->schedules as $schedule)
                    <div wire:key="ronda-schedule-card-{{ $schedule->id }}" class="rounded-2xl bg-slate-950/40 border border-white/5 p-5 shadow-sm hover:border-emerald-500/10 transition-colors duration-300">
                        <p class="font-bold text-emerald-400">{{ $schedule->date->translatedFormat('l, d M Y') }}</p>
                        @if ($schedule->notes)
                            <p class="text-xs text-slate-400 mt-1 italic">{{ $schedule->notes }}</p>
                        @endif

                        <ul class="mt-3 flex flex-wrap gap-2">
                            @forelse ($schedule->assignments as $assignment)
                                <li class="rounded-xl px-3 py-1.5 text-xs font-semibold flex items-center gap-1.5 transition-all duration-300 {{ $assignment->hasCheckedIn() ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-slate-900 text-slate-300 border border-white/5' }}">
                                    <span>{{ $assignment->resident?->name }}</span>
                                    @if ($assignment->hasCheckedIn())
                                        <span class="text-emerald-400 text-sm leading-none" title="Sudah Check-in">&check;</span>
                                    @endif
                                </li>
                            @empty
                                <li class="text-xs text-slate-500 italic">Belum ada warga yang ditugaskan.</li>
                            @endforelse
                        </ul>
                    </div>
                @endforeach
            </div>
        @else
            <div class="rounded-2xl bg-slate-950/20 border border-white/5 p-6 text-center text-slate-500">
                Belum ada jadwal ronda yang terdaftar.
            </div>
        @endif

        <div class="flex flex-col gap-3 items-center">
            <a href="{{ route('portal.checkin') }}" class="w-full rounded-2xl bg-emerald-500 py-3.5 font-bold text-slate-950 shadow-lg shadow-emerald-500/20 hover:bg-emerald-400 active:scale-[0.98] text-center transition-all duration-150">
                Check-in Ronda Hari Ini
            </a>
            <a href="{{ route('portal.home') }}" class="text-sm font-semibold text-emerald-400 hover:text-emerald-300 hover:underline transition-colors mt-2">&larr; Kembali ke portal</a>
        </div>
</div>
