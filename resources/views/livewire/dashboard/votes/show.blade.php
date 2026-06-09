<?php

use App\Models\Vote;
use App\Services\VotingService;
use function Livewire\Volt\{computed, layout, mount, state, title};

layout('components.layouts.app'); title('Hasil Voting');
state(['vote' => null]);
mount(fn (Vote $vote) => $this->vote = $vote->load('options'));
$tally = computed(fn () => app(VotingService::class)->tally($this->vote));
$total = computed(fn () => array_sum($this->tally));
?>
<div class="space-y-6"><div><a href="{{ route('votes.index') }}" class="text-sm text-emerald-600">&larr; Voting</a><h1 class="mt-1 text-2xl font-semibold text-white sm:text-slate-900">{{ $vote->question }}</h1><p class="text-sm text-slate-500">{{ $vote->status->label() }} · {{ $this->total }} suara</p></div><div class="space-y-3">@foreach($vote->options as $option)@php($count = $this->tally[$option->id] ?? 0)@php($pct = $this->total ? round($count / $this->total * 100) : 0)<div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200"><div class="flex justify-between text-sm"><span>{{ $option->label }}</span><span>{{ $count }} ({{ $pct }}%)</span></div><div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full bg-emerald-500" style="width: {{ $pct }}%"></div></div></div>@endforeach</div></div>
