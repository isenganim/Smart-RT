<?php

use App\Models\Vote;
use App\Services\VotingService;
use Illuminate\Support\Facades\RateLimiter;
use function Livewire\Volt\{computed, layout, mount, rules, state, title};

layout('components.layouts.public');
title('Voting');
state(['vote' => null, 'phone' => '', 'optionId' => null, 'done' => false, 'feedback' => null]);
mount(fn (Vote $vote) => $this->vote = $vote->load('options'));
rules(['phone' => ['required', 'string', 'max:30'], 'optionId' => ['required', 'integer']]);
$tally = computed(fn () => app(VotingService::class)->tally($this->vote));
$total = computed(fn () => array_sum($this->tally));
$showResults = computed(fn () => $this->done || ! $this->vote->isOpen());

$submit = function (VotingService $voting) {
    $this->validate();
    $key = 'portal-vote:'.request()->getClientIp();
    if (RateLimiter::tooManyAttempts($key, 5)) {
        $this->done = false; $this->feedback = 'Terlalu banyak percobaan. Coba lagi nanti.'; return;
    }
    RateLimiter::hit($key, 60);
    $result = $voting->cast($this->vote, (int) $this->optionId, $this->phone);
    if ($result->success()) {
        $this->done = true; $this->feedback = null; $this->reset('phone', 'optionId'); return;
    }
    $this->done = false; $this->feedback = $result->message;
};
?>

<div class="space-y-5">
    <div class="rounded-2xl border border-white/5 bg-slate-900 p-6 shadow-xl">
        <h1 class="text-xl font-bold text-white">{{ $vote->question }}</h1>
        <p class="mt-1 text-xs {{ $vote->isOpen() ? 'text-emerald-400' : 'text-slate-500' }}">{{ $vote->isOpen() ? 'Sedang berlangsung' : 'Sudah ditutup' }}</p>
        @if($this->showResults)
            <div class="mt-5 space-y-4">@foreach($vote->options as $option)@php($count = $this->tally[$option->id] ?? 0)@php($pct = $this->total ? round($count / $this->total * 100) : 0)<div><div class="flex justify-between text-sm text-slate-300"><span>{{ $option->label }}</span><span>{{ $count }} ({{ $pct }}%)</span></div><div class="mt-1 h-2 overflow-hidden rounded-full bg-slate-800"><div class="h-full bg-emerald-500" style="width: {{ $pct }}%"></div></div></div>@endforeach</div>
        @else
            <form wire:submit="submit" class="mt-5 space-y-5">
                <x-portal.phone-field model="phone" />
                <div class="space-y-2">@foreach($vote->options as $option)<label class="flex items-center gap-3 rounded-xl border border-white/10 p-3 text-slate-200"><input wire:model="optionId" type="radio" value="{{ $option->id }}"><span>{{ $option->label }}</span></label>@endforeach @error('optionId')<p class="text-sm text-red-400">{{ $message }}</p>@enderror</div>
                <button class="w-full rounded-xl bg-emerald-500 py-3 font-bold text-slate-950">Kirim Suara</button>
            </form>
        @endif
    </div>
    @if($done)<div class="rounded-2xl border border-emerald-500/20 bg-emerald-950/40 p-5 text-center text-emerald-300"><strong>Suara Anda tercatat</strong></div>@elseif($feedback)<div class="rounded-2xl border border-amber-500/20 bg-amber-950/40 p-5 text-center text-amber-300">{{ $feedback }}</div>@endif
</div>
