<?php

use App\Services\AdminDashboardSummary;
use function Livewire\Volt\{computed, layout, title};

layout('components.layouts.app');
title('Dashboard Pengurus');

$summary = computed(fn () => app(AdminDashboardSummary::class)->forDate(today()));
$rupiah = fn (int $value) => 'Rp'.number_format($value, 0, ',', '.');

?>

<div class="space-y-7">
    <x-admin.page-header
        :title="'Selamat datang, '.auth()->user()->name"
        :description="now()->translatedFormat('l, d F Y').' · Ringkasan operasional Smart RT'"
    >
        <x-slot:actions>
            <x-admin.button href="{{ route('scan-sessions.index') }}">Buka sesi scan</x-admin.button>
        </x-slot:actions>
    </x-admin.page-header>

    <section aria-label="Ringkasan utama" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-admin.metric
            label="Rumah aktif"
            :value="$this->summary['metrics']['households']"
            description="Rumah yang digunakan dalam operasional RT"
            :href="route('households.index')"
        />
        <x-admin.metric
            label="Warga aktif"
            :value="$this->summary['metrics']['residents']"
            description="Warga aktif dengan identitas terdaftar"
            :href="route('residents.index')"
        />
        <x-admin.metric
            label="Kas bulan ini"
            :value="$this->rupiah($this->summary['metrics']['month_cash'])"
            description="Total transaksi aktif bulan berjalan"
            :href="route('kas.index')"
        />
        <x-admin.metric
            label="Perlu tindakan"
            :value="$this->summary['metrics']['action_count']"
            description="Gabungan pekerjaan operasional terbuka"
        />
    </section>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1.15fr)_minmax(22rem,0.85fr)]">
        <x-admin.panel :padding="false" aria-labelledby="action-queue-title">
            <div class="border-b border-hairline px-5 py-4 sm:px-6">
                <h2 id="action-queue-title" class="text-lg font-medium text-ink">Yang perlu ditangani</h2>
                <p class="mt-1 text-sm text-ink-mute">Prioritas berdasarkan data operasional saat ini.</p>
            </div>
            <div class="divide-y divide-hairline">
                @foreach ($this->summary['actions'] as $action)
                    <a href="{{ route($action['route'], $action['query']) }}" class="flex min-h-20 items-center gap-4 px-5 py-4 transition hover:bg-canvas-soft sm:px-6">
                        <x-admin.status-badge :tone="$action['tone']">{{ $action['count'] }}</x-admin.status-badge>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-medium text-ink">{{ $action['label'] }}</span>
                            <span class="mt-1 block text-xs text-ink-mute">{{ $action['description'] }}</span>
                        </span>
                        <svg aria-hidden="true" class="h-4 w-4 shrink-0 text-ink-mute" viewBox="0 0 20 20" fill="none">
                            <path d="M7 4.5 12.5 10 7 15.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </a>
                @endforeach
            </div>
        </x-admin.panel>

        <x-admin.panel aria-labelledby="cash-trend-title">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 id="cash-trend-title" class="text-lg font-medium text-ink">Arus kas 30 hari</h2>
                    <p class="mt-1 text-sm text-ink-mute">Transaksi aktif per hari.</p>
                </div>
                <x-admin.button variant="ghost" href="{{ route('kas.transactions') }}">Detail</x-admin.button>
            </div>

            @php($maxCash = max(1, collect($this->summary['cash_trend'])->max('total')))
            <div class="mt-6 flex h-44 items-end gap-1" aria-label="Grafik arus kas 30 hari">
                @foreach ($this->summary['cash_trend'] as $day)
                    @php($height = $day['total'] > 0 ? max(3, round(($day['total'] / $maxCash) * 100)) : 0)
                    <div class="group relative flex min-w-0 flex-1 items-end">
                        <div
                            class="w-full rounded-t-xs bg-primary/25 transition group-hover:bg-primary"
                            style="height: {{ $height }}%"
                            title="{{ $day['label'] }}: {{ $this->rupiah($day['total']) }}"
                        ></div>
                    </div>
                @endforeach
            </div>
            <div class="mt-3 flex justify-between text-xs text-ink-mute">
                <span>{{ collect($this->summary['cash_trend'])->first()['label'] }}</span>
                <span>{{ collect($this->summary['cash_trend'])->last()['label'] }}</span>
            </div>
        </x-admin.panel>
    </div>

    <x-admin.panel :padding="false" aria-labelledby="recent-activity-title">
        <div class="border-b border-hairline px-5 py-4 sm:px-6">
            <h2 id="recent-activity-title" class="text-lg font-medium text-ink">Aktivitas terbaru</h2>
            <p class="mt-1 text-sm text-ink-mute">Perubahan penting yang tercatat dalam audit log.</p>
        </div>

        @forelse ($this->summary['recent_activity'] as $activity)
            <div class="grid gap-1 border-b border-hairline px-5 py-4 last:border-b-0 sm:grid-cols-[minmax(0,1fr)_12rem_9rem] sm:px-6">
                <p class="text-sm font-medium text-ink">{{ $activity['label'] }}</p>
                <p class="text-sm text-ink-mute">{{ $activity['actor'] }}</p>
                <time class="tnum text-xs text-ink-mute sm:text-right" datetime="{{ $activity['time']->toIso8601String() }}">
                    {{ $activity['time']->diffForHumans() }}
                </time>
            </div>
        @empty
            <x-admin.empty-state title="Belum ada aktivitas" description="Perubahan administratif akan muncul di sini setelah dicatat." />
        @endforelse
    </x-admin.panel>
</div>
