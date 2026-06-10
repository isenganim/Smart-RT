<?php

use App\Models\Announcement;
use function Livewire\Volt\{computed, layout, title};

layout('components.layouts.public');
title('Pengumuman');

$announcements = computed(fn () => Announcement::query()->published()->take(50)->get());
?>

<div class="space-y-5">
    <h1 class="text-2xl font-bold text-white">Pengumuman RT</h1>
    @forelse ($this->announcements as $announcement)
        <article class="rounded-2xl border border-white/5 bg-slate-900 p-5 shadow-xl">
            <h2 class="font-semibold text-emerald-400">{{ $announcement->title }}</h2>
            <p class="mt-1 text-xs text-slate-500">{{ $announcement->published_at->translatedFormat('d M Y') }}</p>
            <p class="mt-3 whitespace-pre-line text-sm leading-relaxed text-slate-300">{{ $announcement->body }}</p>
        </article>
    @empty
        <p class="rounded-2xl border border-white/5 bg-slate-900 p-5 text-slate-400">Belum ada pengumuman.</p>
    @endforelse
    <a href="{{ route('portal.home') }}" class="block text-center text-sm font-semibold text-emerald-400">&larr; Kembali ke portal</a>
</div>
