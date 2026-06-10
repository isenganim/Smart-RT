<?php

use App\Enums\VoteStatus;
use App\Models\Vote;
use function Livewire\Volt\{computed, layout, title};

layout('components.layouts.public');
title('Voting Warga');
$votes = computed(fn () => Vote::query()->whereIn('status', [VoteStatus::AKTIF->value, VoteStatus::SELESAI->value])->latest()->take(20)->get());
?>

<div class="space-y-4">
    <h1 class="text-2xl font-bold text-white">Voting Warga</h1>
    @forelse($this->votes as $vote)
        <a href="{{ route('portal.vote', $vote) }}" class="block rounded-2xl border border-white/5 bg-slate-900 p-5 shadow-xl hover:border-emerald-500/30">
            <p class="font-semibold text-white">{{ $vote->question }}</p>
            <p class="mt-1 text-xs {{ $vote->isOpen() ? 'text-emerald-400' : 'text-slate-500' }}">{{ $vote->isOpen() ? 'Sedang berlangsung' : 'Sudah ditutup' }}</p>
        </a>
    @empty
        <p class="rounded-2xl border border-white/5 bg-slate-900 p-5 text-slate-400">Belum ada voting.</p>
    @endforelse
</div>
