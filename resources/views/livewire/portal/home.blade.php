<?php

use function Livewire\Volt\{state, layout, title};

layout('components.layouts.public');
title('Portal Warga');

state([
    'services' => [
        ['label' => 'Jadwal Ronda', 'route' => 'portal.ronda', 'desc' => 'Lihat jadwal ronda warga.', 'ready' => true],
        ['label' => 'Check-in Ronda', 'route' => 'portal.checkin', 'desc' => 'Catat kehadiran ronda Anda hari ini.', 'ready' => true],
        ['label' => 'Cek Nomor HP', 'route' => 'portal.verify', 'desc' => 'Pastikan nomor HP Anda sudah terdaftar.', 'ready' => true],
        ['label' => 'Pengumuman', 'route' => null, 'desc' => 'Informasi terbaru dari RT.', 'ready' => false],
        ['label' => 'Lapor Warga', 'route' => null, 'desc' => 'Kirim laporan ke pengurus.', 'ready' => false],
    ],
]);

?>

<div class="space-y-6">
        <div class="rounded-2xl bg-slate-900 border border-white/5 p-6 shadow-xl relative overflow-hidden">
            <div class="absolute -right-10 -top-10 w-32 h-32 bg-emerald-500/10 rounded-full blur-2xl"></div>
            <h1 class="text-xl font-bold text-white">Selamat datang di Portal Warga</h1>
            <p class="mt-2 text-sm text-slate-400 leading-relaxed">
                Pilih layanan mandiri di bawah ini. Untuk aksi resmi seperti check-in ronda, Anda akan diminta memverifikasi nomor HP yang terdaftar.
            </p>
        </div>

        <div class="grid gap-3">
            @foreach ($services as $service)
                @if ($service['ready'] && $service['route'])
                    <a href="{{ route($service['route']) }}"
                       class="flex items-center justify-between rounded-2xl bg-slate-950/40 p-5 shadow-sm border border-white/5 hover:border-emerald-500/30 transition-all duration-300 hover:-translate-y-0.5 group">
                        <span>
                            <span class="block font-semibold text-white group-hover:text-emerald-400 transition-colors">{{ $service['label'] }}</span>
                            <span class="block text-xs text-slate-400 mt-1">{{ $service['desc'] }}</span>
                        </span>
                        <span class="text-emerald-500 group-hover:translate-x-1 transition-transform">&rarr;</span>
                    </a>
                @else
                    <div class="flex items-center justify-between rounded-2xl bg-slate-950/20 p-5 border border-white/5 opacity-60">
                        <span>
                            <span class="block font-medium text-slate-500">{{ $service['label'] }}</span>
                            <span class="block text-xs text-slate-600 mt-1">{{ $service['desc'] }}</span>
                        </span>
                        <span class="rounded-full bg-slate-800 px-2.5 py-0.5 text-[10px] font-semibold text-slate-500">Segera</span>
                    </div>
                @endif
            @endforeach
        </div>
</div>
