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
    <div class="relative overflow-hidden rounded-lg border border-[#e3e8ee] bg-white p-6 shadow-level1">
        <h1 class="display-md text-[#0d253d]">Jadwal Ronda</h1>
        <p class="mt-2 text-sm text-[#64748d] leading-relaxed">
            Berikut adalah jadwal ronda warga untuk 14 hari ke depan. Silakan hubungi pengurus RT jika ada perubahan.
        </p>
    </div>

    @if ($this->schedules->isNotEmpty())
        <!-- Desktop/Tablet View -->
        <div class="hidden overflow-hidden rounded-lg border border-[#e3e8ee] bg-white shadow-level1 sm:block">
            <table class="min-w-full divide-y divide-[#e3e8ee] text-sm">
                <thead class="bg-[#f6f9fc] text-left text-xs font-semibold uppercase tracking-wider text-[#273951]">
                    <tr>
                        <th class="px-5 py-4">Tanggal</th>
                        <th class="px-5 py-4">Catatan</th>
                        <th class="px-5 py-4">Petugas</th>
                        <th class="px-5 py-4 text-right">Kehadiran</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#e3e8ee]">
                    @foreach ($this->schedules as $schedule)
                        @php
                            $checkedInCount = $schedule->assignments->filter->hasCheckedIn()->count();
                        @endphp
                        <tr wire:key="ronda-schedule-{{ $schedule->id }}" class="align-top transition-colors hover:bg-[#f6f9fc]/50">
                            <td class="whitespace-nowrap px-5 py-4">
                                <p class="font-sans font-semibold text-[#533afd] text-sm">{{ $schedule->date->translatedFormat('l') }}</p>
                                <p class="mt-1 text-xs text-[#64748d]">{{ $schedule->date->translatedFormat('d M Y') }}</p>
                            </td>
                            <td class="max-w-56 px-5 py-4 text-[#273951] leading-relaxed">
                                {{ $schedule->notes ?: '-' }}
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex flex-wrap gap-2">
                                    @forelse ($schedule->assignments as $assignment)
                                        <span class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-semibold {{ $assignment->hasCheckedIn() ? 'bg-[#ecfdf5] text-[#065f46] border-[#a7f3d0]' : 'bg-[#f6f9fc] text-[#64748d] border-[#e3e8ee]' }}">
                                            {{ $assignment->resident?->name }}
                                            @if ($assignment->hasCheckedIn())
                                                <svg class="h-3.5 w-3.5 text-[#059669]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                                    <polyline points="20 6 9 17 4 12"/>
                                                </svg>
                                            @endif
                                        </span>
                                    @empty
                                        <span class="text-xs italic text-[#64748d]">Belum ada warga yang ditugaskan.</span>
                                    @endforelse
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-5 py-4 text-right">
                                <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-semibold {{ $checkedInCount > 0 ? 'bg-[#ecfdf5] text-[#065f46] border-[#a7f3d0]' : 'bg-[#f6f9fc] text-[#64748d] border-[#e3e8ee]' }}">
                                    @if ($checkedInCount > 0)
                                        <span class="h-1.5 w-1.5 rounded-full bg-[#059669] animate-pulse"></span>
                                    @endif
                                    {{ $checkedInCount }} / {{ $schedule->assignments->count() }} Hadir
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Mobile View -->
        <div class="space-y-4 sm:hidden">
            @foreach ($this->schedules as $schedule)
                <div wire:key="ronda-schedule-card-{{ $schedule->id }}" class="rounded-lg bg-white border border-[#e3e8ee] p-5 shadow-level1 hover:border-[#533afd]/30 transition-all duration-300">
                    <p class="font-sans font-semibold text-[#533afd] text-sm">{{ $schedule->date->translatedFormat('l, d M Y') }}</p>
                    @if ($schedule->notes)
                        <p class="text-xs text-[#64748d] mt-1 italic">{{ $schedule->notes }}</p>
                    @endif

                    <ul class="mt-4 flex flex-wrap gap-2">
                        @forelse ($schedule->assignments as $assignment)
                            <li class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-semibold {{ $assignment->hasCheckedIn() ? 'bg-[#ecfdf5] text-[#065f46] border-[#a7f3d0]' : 'bg-[#f6f9fc] text-[#64748d] border-[#e3e8ee]' }}">
                                <span>{{ $assignment->resident?->name }}</span>
                                @if ($assignment->hasCheckedIn())
                                    <svg class="h-3.5 w-3.5 text-[#059669]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                        <polyline points="20 6 9 17 4 12"/>
                                    </svg>
                                @endif
                            </li>
                        @empty
                            <li class="text-xs text-[#64748d] italic">Belum ada warga yang ditugaskan.</li>
                        @endforelse
                    </ul>
                </div>
            @endforeach
        </div>
    @else
        <div class="rounded-lg bg-white border border-[#e3e8ee] p-6 text-center text-[#64748d] shadow-level1">
            Belum ada jadwal ronda yang terdaftar.
        </div>
    @endif

    <div class="flex flex-col gap-3 items-center">
        <a href="{{ route('portal.checkin') }}" class="w-full rounded-full bg-[#533afd] py-3.5 font-sans font-semibold text-white shadow-level1 hover:bg-[#4434d4] active:bg-[#2e2b8c] text-center transition-all duration-150">
            Check-in Ronda Hari Ini
        </a>
        <a href="{{ route('portal.home') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-[#64748d] hover:text-[#533afd] transition-colors group mt-1">
            <span class="transition-transform duration-300 group-hover:-translate-x-1">&larr;</span>
            Kembali ke portal
        </a>
    </div>
</div>
