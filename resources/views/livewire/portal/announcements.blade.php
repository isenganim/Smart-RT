<?php

use App\Models\Announcement;
use App\Support\AnnouncementHtml;
use function Livewire\Volt\{computed, layout, title};

layout('components.layouts.public');
title('Pengumuman');

$announcements = computed(fn () => Announcement::query()->published()->take(50)->get());
?>

<div class="space-y-5">
    <h1 class="display-md text-[#0d253d]">Pengumuman RT</h1>
    @forelse ($this->announcements as $announcement)
        <article class="rounded-lg border border-[#e3e8ee] bg-white p-5 shadow-level1">
            <h2 class="font-sans font-semibold text-[#533afd] text-base leading-snug">{{ $announcement->title }}</h2>
            <p class="mt-1 text-xs text-[#64748d]">{{ $announcement->published_at->translatedFormat('d M Y') }}</p>
            <div class="announcement-content mt-3">{!! app(AnnouncementHtml::class)->sanitize($announcement->body) !!}</div>
        </article>
    @empty
        <p class="rounded-lg border border-[#e3e8ee] bg-white p-5 text-[#64748d] shadow-level1">Belum ada pengumuman.</p>
    @endforelse
    <a href="{{ route('portal.home') }}" class="block text-center text-sm font-semibold text-[#64748d] hover:text-[#533afd] transition-colors">Kembali ke portal</a>
</div>
