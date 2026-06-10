<?php

use App\Enums\VoteStatus;
use App\Models\Vote;
use function Livewire\Volt\{computed, layout, title};

layout('components.layouts.public');
title('Voting Warga');
$votes = computed(fn () => Vote::query()->whereIn('status', [VoteStatus::AKTIF->value, VoteStatus::SELESAI->value])->latest()->take(20)->get());
?>

<div class="space-y-4">
    <h1 class="display-md text-[#0d253d]">Voting Warga</h1>
    @forelse($this->votes as $vote)
        <a href="{{ route('portal.vote', $vote) }}" class="group block rounded-lg border border-[#e3e8ee] bg-white p-5 shadow-level1 hover:border-[#533afd]/60 hover:shadow-level2 transition-all duration-150">
            <p class="font-sans font-semibold text-[#0d253d] group-hover:text-[#533afd] transition-colors leading-snug">{{ $vote->question }}</p>
            <div class="mt-3">
                @if ($vote->isOpen())
                    <span class="inline-flex items-center gap-1 rounded-full bg-[#b9b9f9]/20 border border-[#b9b9f9]/30 px-2.5 py-0.5 text-[10px] font-semibold text-[#533afd]">
                        <span class="h-1 w-1 rounded-full bg-[#533afd] animate-pulse"></span>
                        Sedang berlangsung
                    </span>
                @else
                    <span class="inline-flex items-center gap-1 rounded-full bg-[#f6f9fc] border border-[#e3e8ee] px-2.5 py-0.5 text-[10px] font-semibold text-[#64748d]">
                        Sudah ditutup
                    </span>
                @endif
            </div>
        </a>
    @empty
        <p class="rounded-lg border border-[#e3e8ee] bg-white p-5 text-[#64748d] shadow-level1">Belum ada voting.</p>
    @endforelse
</div>
