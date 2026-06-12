<?php

use App\Enums\VoteStatus;
use App\Models\Vote;
use App\Services\VotingService;
use Illuminate\Support\Facades\RateLimiter;
use function Livewire\Volt\{computed, layout, mount, rules, state, title};

layout('components.layouts.public');
title('Pemungutan Suara');
state(['vote' => null, 'phone' => '', 'optionId' => null, 'done' => false, 'feedback' => null]);
mount(function (Vote $vote) {
    abort_unless(in_array($vote->status, [VoteStatus::AKTIF, VoteStatus::SELESAI], true), 404);
    $this->vote = $vote->load('options');
});
rules(['phone' => ['required', 'string', 'max:30'], 'optionId' => ['required', 'integer']]);
$tally = computed(fn () => app(VotingService::class)->tally($this->vote));
$total = computed(fn () => array_sum($this->tally));
$showResults = computed(fn () => $this->done || ! $this->vote->isOpen());

$submit = function (VotingService $voting) {
    $this->validate();
    $key = 'portal-vote:'.$this->vote->id.':'.request()->getClientIp();
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
    <div class="rounded-lg border border-[#e3e8ee] bg-white p-6 shadow-level1 text-[#0d253d]">
        <h1 class="display-md text-[#0d253d] leading-snug">{{ $vote->question }}</h1>
        <div class="mt-2">
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

        @if($this->showResults)
            <div class="mt-5 space-y-4">
                @foreach($vote->options as $option)
                    @php($count = $this->tally[$option->id] ?? 0)
                    @php($pct = $this->total ? round($count / $this->total * 100) : 0)
                    <div>
                        <div class="flex justify-between text-sm">
                            <span class="font-medium text-[#273951]">{{ $option->label }}</span>
                            <span class="font-semibold text-[#533afd] tnum">{{ $count }} ({{ $pct }}%)</span>
                        </div>
                        <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-[#e3e8ee]">
                            <div class="h-full bg-[#533afd]" style="width: {{ $pct }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <form wire:submit="submit" class="mt-5 space-y-5">
                <x-portal.phone-field model="phone" />
                <div class="space-y-2">
                    @foreach($vote->options as $option)
                        <label class="flex items-center gap-3 rounded-sm border border-[#e3e8ee] p-3 text-[#273951] bg-[#f6f9fc]/50 cursor-pointer hover:border-[#533afd] transition-all">
                            <input wire:model="optionId" type="radio" value="{{ $option->id }}" class="text-[#533afd] focus:ring-[#533afd]">
                            <span class="font-medium text-sm">{{ $option->label }}</span>
                        </label>
                    @endforeach 
                    @error('optionId')
                        <p class="text-sm text-[#ea2261] font-medium">{{ $message }}</p>
                    @enderror
                </div>
                <button class="w-full rounded-full bg-[#533afd] py-3.5 font-sans font-semibold text-white shadow-level1 hover:bg-[#4434d4] active:bg-[#2e2b8c] transition-all duration-150">
                    Kirim Suara
                </button>
            </form>
        @endif
    </div>

    @if($done)
        <div class="rounded-lg border border-[#a7f3d0] bg-[#ecfdf5] p-5 text-center text-[#065f46] shadow-level1 font-semibold">
            Suara Anda tercatat. Terima kasih!
        </div>
    @elseif($feedback)
        <div class="rounded-lg border border-[#fca5a5] bg-[#fef2f2] p-5 text-center text-[#991b1b] shadow-level1">
            {{ $feedback }}
        </div>
    @endif
</div>
