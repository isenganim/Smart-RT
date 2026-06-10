<?php

use function Livewire\Volt\{state, layout, title};

layout('components.layouts.public');
title('Portal Warga');

state([
    'services' => [
        [
            'label' => 'Jadwal Ronda',
            'route' => 'portal.ronda',
            'desc' => 'Lihat jadwal ronda warga.',
            'svg' => '<svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>'
        ],
        [
            'label' => 'Check-in Ronda',
            'route' => 'portal.checkin',
            'desc' => 'Catat kehadiran ronda Anda.',
            'svg' => '<svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><polyline points="16 11 18 13 22 9"/></svg>'
        ],
        [
            'label' => 'Scan Iuran (Petugas)',
            'route' => 'portal.scan',
            'desc' => 'Buka scan iuran dengan PIN.',
            'svg' => '<svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path d="M3 7V5a2 2 0 0 1 2-2h2M17 3h2a2 2 0 0 1 2 2v2M21 17v2a2 2 0 0 1-2 2h-2M7 21H5a2 2 0 0 1-2-2v-2"/><rect x="7" y="7" width="10" height="10" rx="1"/></svg>'
        ],
        [
            'label' => 'Cek Nomor HP',
            'route' => 'portal.verify',
            'desc' => 'Pastikan nomor HP terdaftar.',
            'svg' => '<svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/><path d="M8 11h6"/></svg>'
        ],
        [
            'label' => 'Pengumuman',
            'route' => 'portal.announcements',
            'desc' => 'Informasi terbaru dari RT.',
            'svg' => '<svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>'
        ],
        [
            'label' => 'Lapor Warga',
            'route' => 'portal.report',
            'desc' => 'Kirim laporan ke pengurus.',
            'svg' => '<svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path d="m3 11 18-5v12L3 13v-2zM11.6 8.5V17"/></svg>'
        ],
        [
            'label' => 'Surat Pengantar',
            'route' => 'portal.letter',
            'desc' => 'Ajukan surat pengantar RT.',
            'svg' => '<svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7z"/><path d="M14 2v4a2 2 0 0 0 2 2h4M10 9h4M10 13h4M10 17h4"/></svg>'
        ],
        [
            'label' => 'Voting',
            'route' => 'portal.votes',
            'desc' => 'Ikut voting warga RT.',
            'svg' => '<svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg>'
        ],
    ],
]);

?>

<div class="space-y-6">
    <!-- Warm Cream Interlude Card (card-cream-band) -->
    <div class="relative overflow-hidden rounded-lg border border-[#ebdcb9] bg-[#f5e9d4] p-6 shadow-level1">
        <h1 class="display-md text-[#0d253d]">Selamat datang di Portal Warga</h1>
        <p class="mt-2 text-sm text-[#273951] leading-relaxed">
            Silakan pilih layanan mandiri di bawah ini. Untuk aksi resmi seperti check-in ronda, Anda perlu memverifikasi nomor HP yang terdaftar.
        </p>
    </div>

    <!-- Feature Grid (card-feature-light) -->
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        @foreach ($services as $service)
            <a href="{{ route($service['route']) }}"
               class="group relative flex flex-col justify-between overflow-hidden rounded-lg border border-[#e3e8ee] bg-white p-5 shadow-level1 transition-all duration-300 hover:-translate-y-1 hover:border-[#533afd]/60 hover:shadow-level2 min-h-[136px]">
                <div class="flex items-start justify-between">
                    <span class="inline-flex items-center justify-center rounded-lg bg-[#b9b9f9]/20 p-2.5 text-[#533afd] ring-1 ring-[#533afd]/10 group-hover:bg-[#533afd]/10 transition-colors">
                        {!! $service['svg'] !!}
                    </span>
                    <span class="text-[#64748d] transition-transform duration-300 group-hover:translate-x-1 group-hover:text-[#533afd]">&rarr;</span>
                </div>
                <div class="mt-4">
                    <span class="block font-sans font-semibold text-[#0d253d] tracking-tight group-hover:text-[#533afd] transition-colors text-sm">
                        {{ $service['label'] }}
                    </span>
                    <span class="block text-xs text-[#64748d] mt-1 leading-relaxed">
                        {{ $service['desc'] }}
                    </span>
                </div>
            </a>
        @endforeach
    </div>
</div>
